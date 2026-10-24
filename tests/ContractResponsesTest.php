<?php

declare(strict_types=1);
namespace Facturino\Tests;

use Facturino\Model;
use PHPUnit\Framework\TestCase;

final class ContractResponsesTest extends TestCase
{
    public function testActualServerResponsesRetainEveryFieldIncludingNull(): void
    {
        $models = ['invoice' => Model\Invoice::class, 'payment' => Model\Payment::class, 'customer' => Model\Customer::class,
            'creditNote' => Model\CreditNote::class, 'taxDecision' => Model\TaxDecision::class, 'event' => Model\WebhookEvent::class,
            'legal-category' => Model\TaxDecision::class];
        foreach ($models as $name => $class) {
            $body = json_decode(file_get_contents(__DIR__ . '/fixtures/contract/' . $name . '.json'), true, 512, JSON_THROW_ON_ERROR);
            $model = $class::fromArray($body);
            $this->assertSame($body, $model->jsonSerialize());
            foreach (array_keys($body) as $key) {
                $this->assertTrue(property_exists($model, $key), $class . '.' . $key);
            }
            $this->assertArrayNotHasKey('not_a_field', $model->rawData);
        }
    }

    /** @return array<string, mixed> */
    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__ . '/fixtures/contract/' . $name . '.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function supplyCategoryFixture(string $name): array
    {
        $bodies = json_decode(file_get_contents(__DIR__ . '/fixtures/sdk/supply-category.json'), true, 512, JSON_THROW_ON_ERROR);
        return $bodies[$name];
    }

    /**
     * Product and quote resources return arrays through this response decoder.
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function decodeSdkResponse(array $body): array
    {
        $decode = new \ReflectionMethod(\Facturino\HttpClient::class, 'decodeResponse');
        return $decode->invoke(null, json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function testProductResponsesParseAndSerializeOpenSupplyCategories(): void
    {
        foreach (['goods', 'services', 'electronically_supplied_services', 'deposit', 'ancillary_costs', 'future_supply'] as $category) {
            $body = $this->supplyCategoryFixture('product');
            $body['supplyCategory'] = $category;
            $body['kind'] = $category === 'goods' ? 'goods' : 'service';
            $product = $this->decodeSdkResponse($body);

            $this->assertSame($category, $product['supplyCategory']);
            $this->assertSame($body['kind'], $product['kind']);
            $this->assertSame('VATEX-FR-CGI261-4', $product['vatexCode']);
            $this->assertSame(['online'], $product['tags']);
            $this->assertSame($body, $product);
            $this->assertSame($body, json_decode(json_encode($product, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
        }
    }

    public function testQuoteResponsesParseAndSerializeOpenSupplyCategories(): void
    {
        foreach (['goods', 'services', 'electronically_supplied_services', 'deposit', 'ancillary_costs', 'future_supply'] as $category) {
            $body = $this->supplyCategoryFixture('quote');
            $body['items'][0]['supplyCategory'] = $category;
            $quote = $this->decodeSdkResponse($body);

            $this->assertSame($category, $quote['items'][0]['supplyCategory']);
            $this->assertSame('1', $quote['items'][0]['quantity']);
            $this->assertSame($body, $quote);
            $this->assertSame($body, json_decode(json_encode($quote, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
        }
    }

    public function testHistoricalProductsAndQuotesKeepSupplyFieldsAbsent(): void
    {
        $productBody = $this->supplyCategoryFixture('historicalProduct');
        $quoteBody = $this->supplyCategoryFixture('historicalQuote');
        $product = $this->decodeSdkResponse($productBody);
        $quote = $this->decodeSdkResponse($quoteBody);

        $this->assertArrayNotHasKey('kind', $product);
        $this->assertArrayNotHasKey('supplyCategory', $product);
        $this->assertSame('VATEX-FR-261', $product['vatexCode']);
        $this->assertArrayNotHasKey('kind', $quote['items'][0]);
        $this->assertArrayNotHasKey('supplyCategory', $quote['items'][0]);
        $this->assertSame($productBody, json_decode(json_encode($product, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame($quoteBody, json_decode(json_encode($quote, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testConvertedDraftSupplyDeclarationsAndOmissionsRoundTrip(): void
    {
        $body = $this->supplyCategoryFixture('convertedDraft');
        foreach (['electronically_supplied_services', 'future_supply'] as $category) {
            $body['items'][0]['supplyCategory'] = $category;
            $body['commercialDraft']['lines'][0]['supplyCategory'] = $category;
            $model = Model\Invoice::fromArray($body);

            $this->assertSame($category, $model->items[0]['supplyCategory']);
            $this->assertSame($category, $model->commercialDraft['lines'][0]['supplyCategory']);
            $this->assertSame($body, json_decode(json_encode($model, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
        }

        unset($body['items'][0]['supplyCategory'], $body['commercialDraft']['lines'][0]['supplyCategory']);
        $historical = Model\Invoice::fromArray($body);
        $this->assertArrayNotHasKey('supplyCategory', $historical->items[0]);
        $this->assertArrayNotHasKey('supplyCategory', $historical->commercialDraft['lines'][0]);
        $this->assertSame($body, $historical->jsonSerialize());
    }

    public function testUndeclaredSupplyCategoryKeepsPendingNullAndNoAmounts(): void
    {
        foreach (['facturino', 'integration'] as $source) {
            $body = $this->supplyCategoryFixture('pendingDecision');
            $body['taxSource'] = $source;
            $model = Model\TaxDecision::fromArray($body);

            $this->assertSame('pending_verification', $model->status);
            $this->assertArrayHasKey('category', $model->lines[0]);
            $this->assertNull($model->lines[0]['category']);
            foreach (['amountHT', 'amountVAT', 'amountTTC'] as $field) {
                $this->assertArrayNotHasKey($field, $model->lines[0]);
            }
            $this->assertNull($model->totals);
            $this->assertNull($model->amountToCharge);
            $this->assertSame('supply_category_not_declared', $model->issues[0]['code']);
            $this->assertSame('li_supply', $model->issues[0]['lineId']);
            $this->assertSame([], $model->notices);
            $this->assertSame($body, json_decode(json_encode($model, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
        }
    }

    public function testSameCompleteServiceResultHydratesItsFinalNotice(): void
    {
        foreach (['facturino', 'integration'] as $source) {
            $body = $this->supplyCategoryFixture('finalDecision');
            $body['taxSource'] = $source;
            $model = Model\TaxDecision::fromArray($body);

            $this->assertSame('final', $model->status);
            $this->assertSame('services', $model->lines[0]['category']);
            $this->assertSame(12000, $model->amountToCharge);
            $this->assertSame([], $model->issues);
            $this->assertInstanceOf(Model\TaxDecisionNotice::class, $model->notices[0]);
            $this->assertSame('supply_category_not_declared', $model->notices[0]->code);
            $this->assertSame('li_supply', $model->notices[0]->lineId);
            $this->assertSame($body['notices'][0], $model->notices[0]->jsonSerialize());
            $this->assertSame($body, json_decode(json_encode($model, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
        }
    }

    public function testHistoricalDecisionCategoriesStayUnchangedAndOpen(): void
    {
        $body = $this->fixture('taxDecision');
        $historical = Model\TaxDecision::fromArray($body);
        $this->assertSame('services', $historical->lines[0]['category']);
        $this->assertSame($body, $historical->jsonSerialize());

        $body['lines'][0]['category'] = 'future_supply';
        $model = Model\TaxDecision::fromArray($body);
        $this->assertSame('future_supply', $model->lines[0]['category']);
        $this->assertSame($body, $model->jsonSerialize());
    }

    public function testLegalCategoryFixtureHydratesEveryField(): void
    {
        $body = $this->fixture('legal-category');
        $base = $this->fixture('taxDecision');
        $base['legalCategory'] = $body['legalCategory'];
        $this->assertSame($base, $body);

        $model = Model\TaxDecision::fromArray($body);
        $category = $model->legalCategory;
        $this->assertInstanceOf(Model\TaxDecisionLegalCategory::class, $category);
        foreach (array_keys($body['legalCategory']) as $key) {
            $this->assertTrue(property_exists($category, $key), Model\TaxDecisionLegalCategory::class . '.' . $key);
        }
        $this->assertSame('sirene', $category->source);
        $this->assertSame('123456789', $category->siren);
        $this->assertSame('found', $category->status);
        $this->assertSame('5710', $category->legalCategoryCode);
        $this->assertSame('2026-09-15', $category->checkedAt);
        $this->assertSame($body['legalCategory'], $category->jsonSerialize());
        $this->assertSame($body, $model->jsonSerialize());
    }

    public function testLegalCategoryPreservesNullAndFutureStrings(): void
    {
        foreach (['not_found', 'unavailable', 'future_status'] as $status) {
            $body = $this->fixture('legal-category');
            $body['legalCategory']['status'] = $status;
            $body['legalCategory']['legalCategoryCode'] = null;
            $body['legalCategory']['futureField'] = 'preserved';
            $model = Model\TaxDecision::fromArray($body);
            $category = $model->legalCategory;
            $this->assertSame($status, $category->status);
            $this->assertNull($category->legalCategoryCode);
            $this->assertSame($body['legalCategory'], $category->jsonSerialize());
            $this->assertSame($body, $model->jsonSerialize());
        }

        $body['legalCategory']['legalCategoryCode'] = 'future_category';
        $this->assertSame('future_category', Model\TaxDecision::fromArray($body)->legalCategory->legalCategoryCode);
    }

    public function testHistoricalDecisionsDoNotInventALegalCategory(): void
    {
        $body = $this->fixture('taxDecision');
        $model = Model\TaxDecision::fromArray($body);
        $this->assertNull($model->legalCategory);
        $this->assertArrayNotHasKey('legalCategory', $model->jsonSerialize());
        $this->assertSame($body, $model->jsonSerialize());
    }

    public function testCustomerTaxStatusIsOptionalAndHydratesBothValues(): void
    {
        $body = $this->fixture('customer');
        $historical = Model\Customer::fromArray($body);
        $this->assertNull($historical->taxStatus);
        $this->assertArrayNotHasKey('taxStatus', $historical->jsonSerialize());

        foreach (['taxable', 'non_taxable'] as $taxStatus) {
            $body['taxStatus'] = $taxStatus;
            $model = Model\Customer::fromArray($body);
            $this->assertSame($taxStatus, $model->taxStatus);
            $this->assertSame($body, $model->jsonSerialize());
        }
    }

    public function testLocalTaxContractResponsesRetainEveryFieldIncludingNull(): void
    {
        $bodies = $this->fixture('localTax');
        $models = ['customer' => Model\Customer::class, 'taxDecision' => Model\TaxDecision::class, 'invoice' => Model\Invoice::class];
        foreach ($models as $name => $class) {
            $model = $class::fromArray($bodies[$name]);
            $this->assertSame($bodies[$name], $model->jsonSerialize());
            foreach (array_keys($bodies[$name]) as $key) {
                $this->assertTrue(property_exists($model, $key), $class . '.' . $key);
            }
        }

        $decision = Model\TaxDecision::fromArray($bodies['taxDecision']);
        $invoice = Model\Invoice::fromArray($bodies['invoice']);
        $this->assertSame('taxable', Model\Customer::fromArray($bodies['customer'])->taxStatus);
        $registration = $decision->sellerProfile['localTaxRegistrations'][0];
        $this->assertSame('MF', $registration['territory']);
        $this->assertSame('MF-registration-fixture', $registration['reference']);
        $this->assertSame('2026-09-01', $registration['effectiveFrom']);
        $this->assertArrayHasKey('effectiveTo', $registration);
        $this->assertNull($registration['effectiveTo']);
        $localTax = $decision->lines[0]['localTax'];
        $this->assertSame('tgca_mf', $localTax['code']);
        $this->assertSame(400, $localTax['rateCentipercent']);
        $this->assertSame(400, $localTax['amount']);
        $this->assertSame('CGIsm art. 252', $localTax['reference']);
        $this->assertArrayHasKey('exemption', $localTax);
        $this->assertNull($localTax['exemption']);
        $this->assertSame($localTax, $invoice->items[0]['localTax']);
        $this->assertSame(400, $decision->totals['totalLocalTax']);
        $this->assertSame(400, $invoice->totals['totalLocalTax']);
    }

    public function testLocalTaxStringsAcceptFutureValuesAndDatedRegistrations(): void
    {
        $bodies = $this->fixture('localTax');
        $bodies['taxDecision']['sellerProfile']['localTaxRegistrations'][0]['territory'] = 'future_territory';
        $bodies['taxDecision']['sellerProfile']['localTaxRegistrations'][0]['effectiveTo'] = '2026-12-31';
        $bodies['taxDecision']['sellerProfile']['localTaxRegistrations'][] = [
            'territory' => 'MF', 'reference' => 'MF-next-fixture',
            'effectiveFrom' => '2027-01-01', 'effectiveTo' => null,
        ];
        $models = ['taxDecision' => [Model\TaxDecision::class, 'lines'], 'invoice' => [Model\Invoice::class, 'items']];
        foreach ($models as $name => [$class, $lineField]) {
            $body = $bodies[$name];
            $body[$lineField][0]['localTax']['code'] = 'future_local_tax';
            $body[$lineField][0]['localTax']['exemption'] = 'future_exemption';
            $model = $class::fromArray($body);
            $this->assertSame('future_local_tax', $model->{$lineField}[0]['localTax']['code']);
            $this->assertSame('future_exemption', $model->{$lineField}[0]['localTax']['exemption']);
            $this->assertSame($body, $model->jsonSerialize());
        }

        $decision = Model\TaxDecision::fromArray($bodies['taxDecision']);
        $this->assertCount(2, $decision->sellerProfile['localTaxRegistrations']);
        $this->assertSame('future_territory', $decision->sellerProfile['localTaxRegistrations'][0]['territory']);
        $this->assertSame('2026-12-31', $decision->sellerProfile['localTaxRegistrations'][0]['effectiveTo']);
        $this->assertNull($decision->sellerProfile['localTaxRegistrations'][1]['effectiveTo']);
    }

    public function testExemptLocalTaxKeepsZeroAmountsAndTotals(): void
    {
        $bodies = $this->fixture('localTax');
        $models = ['taxDecision' => [Model\TaxDecision::class, 'lines'], 'invoice' => [Model\Invoice::class, 'items']];
        foreach ($models as $name => [$class, $lineField]) {
            $body = $bodies[$name];
            $body[$lineField][0]['localTax']['rateCentipercent'] = 0;
            $body[$lineField][0]['localTax']['amount'] = 0;
            $body[$lineField][0]['localTax']['exemption'] = 'cgism_253';
            $body[$lineField][0][$name === 'taxDecision' ? 'amountTTC' : 'lineTotal'] = 10000;
            $body['totals']['totalLocalTax'] = 0;
            $body['totals']['totalTTC'] = 10000;
            if ($name === 'taxDecision') {
                $body['amountToCharge'] = 10000;
            } else {
                $body['totals']['amountDue'] = 10000;
            }
            $model = $class::fromArray($body);
            $this->assertSame(0, $model->{$lineField}[0]['localTax']['rateCentipercent']);
            $this->assertSame(0, $model->{$lineField}[0]['localTax']['amount']);
            $this->assertSame('cgism_253', $model->{$lineField}[0]['localTax']['exemption']);
            $this->assertSame(0, $model->totals['totalLocalTax']);
            $this->assertSame($body, $model->jsonSerialize());
        }
    }

    public function testHistoricalResponsesDoNotInventOptionalLocalTaxFields(): void
    {
        $decisionBody = $this->fixture('taxDecision');
        $invoiceBody = $this->fixture('invoice');
        $decision = Model\TaxDecision::fromArray($decisionBody);
        $invoice = Model\Invoice::fromArray($invoiceBody);
        $this->assertArrayNotHasKey('localTaxRegistrations', $decision->sellerProfile);
        $this->assertArrayNotHasKey('localTax', $decision->lines[0]);
        $this->assertArrayNotHasKey('localTax', $invoice->items[0]);
        $this->assertArrayNotHasKey('totalLocalTax', $decision->totals);
        $this->assertArrayNotHasKey('totalLocalTax', $invoice->totals);
        $this->assertSame($decisionBody, $decision->jsonSerialize());
        $this->assertSame($invoiceBody, $invoice->jsonSerialize());
    }

    public function testNonFinalDecisionsKeepNullTotalsWithLocalRegistrations(): void
    {
        $body = $this->fixture('localTax')['taxDecision'];
        $body['status'] = 'pending_verification';
        $body['totals'] = null;
        $body['amountToCharge'] = null;
        unset($body['lines'][0]['localTax']);
        $model = Model\TaxDecision::fromArray($body);

        $this->assertNull($model->totals);
        $this->assertNull($model->amountToCharge);
        $this->assertArrayNotHasKey('localTax', $model->lines[0]);
        $this->assertSame($body['sellerProfile']['localTaxRegistrations'], $model->sellerProfile['localTaxRegistrations']);
        $this->assertSame($body, $model->jsonSerialize());
    }

    /** @return array<string, mixed> */
    private function noticeWithDetail(): array
    {
        return [
            'code' => 'vat_number_territory_mismatch',
            'detailCode' => 'vat_number_ignored_national_registration',
            'message' => 'The VAT number was set aside: the national registration establishes the business status.',
            'reference' => 'Implementing Regulation (EU) 282/2011 art. 18',
            'lineId' => 'line-1',
        ];
    }

    /** @return array<string, mixed> */
    private function bareNotice(): array
    {
        return [
            'code' => 'destination_mechanism_missing',
            'message' => 'Destination VAT is invoiced. Register with the one-stop shop or in the member state.',
        ];
    }

    public function testTheCorpusCarriesTheNoticeAndRetainedVatNumberFields(): void
    {
        $decision = $this->fixture('taxDecision');
        // Always present: an empty list, never a missing key.
        $this->assertSame([], $decision['notices']);
        $this->assertArrayHasKey('retainedVatNumber', $decision['customer']);
        $this->assertNull($decision['customer']['retainedVatNumber']);
        $this->assertSame([], $this->fixture('invoice')['taxSnapshot']['notices']);
        $this->assertSame([], $this->fixture('creditNote')['taxSnapshot']['notices']);

        $model = Model\TaxDecision::fromArray($decision);
        $this->assertSame([], $model->notices);
        $this->assertSame([], $model->issues);
        $this->assertNull($model->customer['retainedVatNumber']);
    }

    public function testAFinalDecisionHydratesItsNoticesAndTheDeclaredVatNumber(): void
    {
        $body = $this->fixture('taxDecision');
        $body['customer']['natureBasis'] = 'declared_business_with_registration';
        $body['customer']['vatNumber'] = 'DE811128135';
        $body['customer']['retainedVatNumber'] = null;
        $body['notices'] = [$this->noticeWithDetail(), $this->bareNotice()];

        $model = Model\TaxDecision::fromArray($body);

        $this->assertSame('final', $model->status);
        $this->assertCount(2, $model->notices);
        $this->assertContainsOnlyInstancesOf(Model\TaxDecisionNotice::class, $model->notices);
        $first = $model->notices[0];
        $this->assertSame('vat_number_territory_mismatch', $first->code);
        $this->assertSame('vat_number_ignored_national_registration', $first->detailCode);
        $this->assertSame('Implementing Regulation (EU) 282/2011 art. 18', $first->reference);
        $this->assertSame('line-1', $first->lineId);
        $this->assertNotNull($first->message);
        // The optional fields stay null, and the raw payload keeps what was sent.
        $second = $model->notices[1];
        $this->assertSame('destination_mechanism_missing', $second->code);
        $this->assertNull($second->detailCode);
        $this->assertNull($second->reference);
        $this->assertNull($second->lineId);
        $this->assertSame($this->bareNotice(), $second->jsonSerialize());
        $this->assertSame('DE811128135', $model->customer['vatNumber']);
        $this->assertNull($model->customer['retainedVatNumber']);
        // The original payload, notices included, is preserved.
        $this->assertSame($body, $model->jsonSerialize());
    }

    public function testArticle18PresumptionRetainsNoVatNumber(): void
    {
        $body = $this->fixture('taxDecision');
        $body['customer']['nature'] = 'consumer';
        $body['customer']['natureBasis'] = 'declared_business_not_evidenced';
        $body['customer']['vatNumber'] = null;
        $body['customer']['retainedVatNumber'] = null;
        $body['lines'][0]['category'] = 'electronically_supplied_services';
        $body['lines'][0]['effectiveCategory'] = 'electronically_supplied_services';
        $body['notices'] = [[
            'code' => 'business_status_not_evidenced',
            'message' => 'No VAT number was communicated. Services follow non-taxable-person rules under art. 18(2), second subparagraph.',
        ]];

        $customer = Model\TaxDecision::fromArray($body)->customer;

        $this->assertSame('declared_business_not_evidenced', $customer['natureBasis']);
        $this->assertNull($customer['vatNumber']);
        $this->assertNull($customer['retainedVatNumber']);
    }

    public function testAPendingDecisionReadsTheIssueDetail(): void
    {
        $body = $this->fixture('taxDecision');
        $body['status'] = 'pending_verification';
        $body['totals'] = null;
        $body['amountToCharge'] = null;
        $body['issues'] = [[
            'code' => 'business_status_not_evidenced',
            'detailCode' => 'einvoicing_buyer_registration_required',
            'message' => 'The buyer SIRET is required for the electronic invoice.',
            'reference' => 'CGI art. 242 nonies A',
            'lineId' => 'line-1',
        ], ['code' => 'vies_unavailable', 'message' => 'VIES is unreachable.']];

        $model = Model\TaxDecision::fromArray($body);

        $this->assertSame('einvoicing_buyer_registration_required', $model->issues[0]['detailCode']);
        $this->assertSame('CGI art. 242 nonies A', $model->issues[0]['reference']);
        $this->assertSame('line-1', $model->issues[0]['lineId']);
        $this->assertArrayNotHasKey('detailCode', $model->issues[1]);
        $this->assertSame($body, $model->jsonSerialize());
    }

    public function testDocumentsKeepTheNoticesFrozenWithTheirDecision(): void
    {
        $invoice = $this->fixture('invoice');
        $invoice['taxSnapshot']['notices'] = [$this->noticeWithDetail(), $this->bareNotice()];
        $creditNote = $this->fixture('creditNote');
        $creditNote['taxSnapshot']['notices'] = [$this->noticeWithDetail()];

        $this->assertSame(
            [$this->noticeWithDetail(), $this->bareNotice()],
            Model\Invoice::fromArray($invoice)->taxSnapshot['notices']
        );
        $this->assertSame(
            [$this->noticeWithDetail()],
            Model\CreditNote::fromArray($creditNote)->taxSnapshot['notices']
        );
        // The frozen entries have the shape of a decision notice.
        $frozen = Model\TaxDecisionNotice::fromArray(Model\Invoice::fromArray($invoice)->taxSnapshot['notices'][0]);
        $this->assertSame('vat_number_ignored_national_registration', $frozen->detailCode);
        $this->assertSame('line-1', $frozen->lineId);
    }

    public function testEveryEventFamilyHasATypedViewWithoutInventingIdentifiers(): void
    {
        $events = json_decode(file_get_contents(__DIR__ . '/fixtures/contract/events.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($events as $body) {
            $event = Model\WebhookEvent::fromArray($body);
            $this->assertNotNull($event->dataFamily);
            $this->assertSame($body, $event->jsonSerialize());
            foreach (array_keys($body['data']) as $key) {
                $this->assertTrue(property_exists($event->data, $key), $body['type'] . '.' . $key);
            }
            if ($body['type'] === 'payment.created') {
                $this->assertNull($event->data->id);
                $this->assertArrayNotHasKey('id', $event->data->rawData);
                $this->assertSame('pay_fixture', $event->data->paymentId);
            }
        }
    }
}
