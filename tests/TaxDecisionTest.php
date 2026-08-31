<?php

declare(strict_types=1);

namespace Facturino\Tests;

use Facturino\Facturino;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for the tax-decisions resource and the documents backed by a
 * decision. They assert the exact path, headers and JSON the SDK produces,
 * against a loopback recorder — nothing leaves the machine.
 */
final class TaxDecisionTest extends TestCase
{
    private LoopbackServer $server;

    private const FINAL_DECISION = '{
        "id": "taxdec_9c1f", "object": "tax_decision", "status": "final",
        "taxSource": "facturino",
        "customerId": "cus_8f2k4m9n", "currency": "eur", "priceMode": "tax_exclusive",
        "totals": {"totalHT": 2900, "totalVAT": 580, "totalTTC": 3480},
        "amountToCharge": 3480, "invoiceChannel": "einvoicing",
        "transactionReporting": "none", "paymentReporting": "fr212",
        "foreignTaxReviewRequired": false, "vies": null, "issues": [],
        "retryOfTaxDecisionId": null, "expired": false
    }';

    protected function setUp(): void
    {
        Facturino::reset();
        $this->server = new LoopbackServer();
        Facturino::setApiKey('fac_test_abc123');
        Facturino::setApiBase($this->server->baseUrl());
    }

    protected function tearDown(): void
    {
        $this->server->stop();
        Facturino::reset();
    }

    /** @return array<string, mixed> */
    private function decisionParams(): array
    {
        return [
            'taxSource' => 'facturino',
            'customerId' => 'cus_8f2k4m9n',
            'effectiveAt' => '2026-09-15',
            'currency' => 'eur',
            'priceMode' => 'tax_exclusive',
            'lines' => [[
                'reference' => 'abo-pro',
                'description' => 'Abonnement Pro',
                'category' => 'electronically_supplied_services',
                'rateCategory' => 'standard',
                'unitAmount' => 2900,
                'quantity' => '1',
            ]],
        ];
    }

    public function testResourceIsExposedOnBothClasses(): void
    {
        foreach (['create', 'retrieve'] as $method) {
            $this->assertTrue(
                method_exists(\Facturino\TaxDecision::class, $method),
                "\\Facturino\\TaxDecision::{$method}() must be callable as a static convenience method",
            );
            $this->assertTrue(
                method_exists(\Facturino\Resource\TaxDecision::class, $method),
                "\\Facturino\\Resource\\TaxDecision::{$method}() must exist",
            );
        }
    }

    public function testCreatePostsToTheExactPathWithTheExactBody(): void
    {
        $this->server->respondWith(201, self::FINAL_DECISION);

        $decision = \Facturino\TaxDecision::create($this->decisionParams(), 'order-4711');

        $request = $this->server->lastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertSame('/v1/tax-decisions', $request['path']);
        $this->assertSame('order-4711', $request['headers']['Idempotency-Key']);
        $this->assertSame($this->decisionParams(), json_decode($request['body'], true));
        $this->assertSame(3480, $decision['amountToCharge']);
    }

    public function testCreateRefusesAnEmptyIdempotencyKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Idempotency-Key is required');

        \Facturino\TaxDecision::create($this->decisionParams(), '   ');
    }

    public function testCreateRefusesAKeyLongerThanTheApiAccepts(): void
    {
        // Checked locally: an over-long key fails without a round trip.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at most 255 characters');

        \Facturino\TaxDecision::create($this->decisionParams(), str_repeat('k', 256));
    }

    public function testCreateAcceptsAKeyOfExactlyTheMaximumLength(): void
    {
        $this->server->respondWith(201, self::FINAL_DECISION);

        \Facturino\TaxDecision::create($this->decisionParams(), str_repeat('k', 255));

        $this->assertSame(str_repeat('k', 255), $this->server->lastRequest()['headers']['Idempotency-Key']);
    }

    public function testCreateSendsTheCurrentDatedContractVersion(): void
    {
        $this->server->respondWith(201, self::FINAL_DECISION);

        \Facturino\TaxDecision::create($this->decisionParams(), 'order-version');

        $this->assertSame('2026-09-01', $this->server->lastRequest()['headers']['Facturino-Version']);
    }

    public function testCreateReturnsTheDecisionOnA200ReplayExactlyAsOnA201(): void
    {
        $this->server->respondWith(201, self::FINAL_DECISION);
        $created = \Facturino\TaxDecision::create($this->decisionParams(), 'order-replay');

        $this->server->respondWith(200, self::FINAL_DECISION);
        $replayed = \Facturino\TaxDecision::create($this->decisionParams(), 'order-replay');

        $this->assertSame($created, $replayed);
    }

    public function testCreateSurfacesA409AsAConflict(): void
    {
        $this->server->respondWith(409, json_encode(['error' => [
            'type' => 'invalid_request_error',
            'code' => 'conflict',
            'message' => 'This Idempotency-Key was already used with a different request body.',
        ]], JSON_THROW_ON_ERROR));

        $this->expectException(\Facturino\Exception\ConflictException::class);

        \Facturino\TaxDecision::create($this->decisionParams(), 'order-replay');
    }

    public function testQuantitiesStayStringsAndAmountsStayIntegers(): void
    {
        $this->server->respondWith(201, self::FINAL_DECISION);

        $params = $this->decisionParams();
        $params['lines'][0]['quantity'] = '2.500000';
        $params['lines'][0]['discount'] = ['type' => 'percent', 'value' => 2500];
        \Facturino\TaxDecision::create($params, 'order-quantity');

        $body = json_decode($this->server->lastRequest()['body'], true);
        // A float quantity would not survive: 0.1 + 0.2 is not 0.3.
        $this->assertIsString($body['lines'][0]['quantity']);
        $this->assertSame('2.500000', $body['lines'][0]['quantity']);
        $this->assertIsInt($body['lines'][0]['unitAmount']);
        // Centi-percent: 2500 is 25.00 %.
        $this->assertSame(['type' => 'percent', 'value' => 2500], $body['lines'][0]['discount']);
    }

    public function testEvidenceTravelsInCamelCaseWithoutRawSignal(): void
    {
        $this->server->respondWith(201, self::FINAL_DECISION);

        $params = $this->decisionParams();
        $params['locationEvidence'] = [[
            'kind' => 'ip_geolocation', 'country' => 'FR', 'postalCode' => '75002',
            'thirdParty' => true, 'source' => 'psp', 'collectedAt' => '2026-09-15',
            'reference' => 'ch_3Kj9aLZ',
        ]];
        $params['nonEuBusinessEvidence'] = [
            'kind' => 'vat_or_similar_number', 'reference' => 'CH-123456',
            'issuedByCountry' => 'CH', 'reasonableVerificationPerformed' => true,
            'collectedAt' => '2026-09-15',
        ];
        \Facturino\TaxDecision::create($params, 'order-evidence');

        $raw = $this->server->lastRequest()['body'];
        $body = json_decode($raw, true);
        $this->assertTrue($body['locationEvidence'][0]['thirdParty']);
        $this->assertTrue($body['nonEuBusinessEvidence']['reasonableVerificationPerformed']);
        // The territorial signal travels, never the raw one.
        foreach (['"ip"', '"payload"', '"iban"'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $raw);
        }
    }

    public function testRetrieveGetsTheDecisionById(): void
    {
        $this->server->respondWith(200, self::FINAL_DECISION);

        $decision = \Facturino\TaxDecision::retrieve('taxdec_9c1f');

        $request = $this->server->lastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertSame('/v1/tax-decisions/taxdec_9c1f', $request['path']);
        $this->assertSame('taxdec_9c1f', $decision['id']);
    }

    public function testNonFinalDecisionCarriesNullAmounts(): void
    {
        $this->server->respondWith(201, '{
            "id": "taxdec_pending", "object": "tax_decision",
            "status": "pending_verification", "totals": null, "amountToCharge": null,
            "invoiceChannel": null, "transactionReporting": null, "paymentReporting": null,
            "issues": [{"code": "vies_unavailable", "message": "VIES is unreachable."}]
        }');

        $decision = \Facturino\TaxDecision::create($this->decisionParams(), 'order-pending');

        $this->assertSame('pending_verification', $decision['status']);
        // null, never 0: absent is not "nothing to charge".
        $this->assertNull($decision['amountToCharge']);
        $this->assertNull($decision['totals']);
        $this->assertSame('vies_unavailable', $decision['issues'][0]['code']);
    }

    public function testRetryCarriesLineageAndLeavesTheOperationUnchanged(): void
    {
        $this->server->respondWith(201, self::FINAL_DECISION);

        $params = $this->decisionParams();
        $params['retryOfTaxDecisionId'] = 'taxdec_previous';
        $params['locationEvidence'] = [[
            'kind' => 'billing_address', 'country' => 'FR', 'thirdParty' => false,
            'source' => 'declared', 'collectedAt' => '2026-09-15',
        ]];
        \Facturino\TaxDecision::create($params, 'order-retry');

        $body = json_decode($this->server->lastRequest()['body'], true);
        $this->assertSame('taxdec_previous', $body['retryOfTaxDecisionId']);
        // Only the evidence is added; the commercial operation is identical.
        $this->assertSame($this->decisionParams()['lines'], $body['lines']);
    }

    public function testNoMutationMethodIsExposed(): void
    {
        foreach (['update', 'delete', 'del', 'cancel'] as $forbidden) {
            $this->assertFalse(
                method_exists(\Facturino\Resource\TaxDecision::class, $forbidden),
                "a tax decision is immutable; {$forbidden}() must not exist",
            );
        }
    }

    public function testInvoiceBackedByADecisionSendsNoVat(): void
    {
        $this->server->respondWith(201, '{
            "id": "inv_1", "object": "invoice", "status": "draft",
            "documentStatus": "draft", "transmissionStatus": "not_applicable",
            "paymentStatus": "unpaid", "taxSource": "facturino",
            "taxDecisionId": "taxdec_9c1f"
        }');

        $invoice = \Facturino\Invoice::create([
            'customerId' => 'cus_8f2k4m9n',
            'taxDecisionId' => 'taxdec_9c1f',
            'decisionLines' => [['taxLineRef' => 'abo-pro', 'unit' => 'month']],
            'buyer' => ['companyName' => 'ACME SAS'],
            'dates' => ['issued' => '2026-09-15', 'due' => '2026-10-15'],
            'payment' => ['terms' => '30 jours', 'termsDays' => 30, 'method' => 'transfer'],
        ]);

        $request = $this->server->lastRequest();
        $this->assertSame('/v1/invoices', $request['path']);
        $body = json_decode($request['body'], true);
        $this->assertSame('taxdec_9c1f', $body['taxDecisionId']);
        $this->assertSame([['taxLineRef' => 'abo-pro', 'unit' => 'month']], $body['decisionLines']);
        // The historical `lines` field never travels alongside a decision.
        $this->assertArrayNotHasKey('lines', $body);
        $this->assertStringNotContainsString('"vatRate"', $request['body']);

        $this->assertSame('facturino', $invoice['taxSource']);
        $this->assertSame('draft', $invoice['documentStatus']);
        $this->assertSame('unpaid', $invoice['paymentStatus']);
    }

    /**
     * @dataProvider explicitVatFieldProvider
     */
    public function testInvoiceCreateRefusesExplicitVatLinesLocally(string $field, array $value): void
    {
        // Checked locally: the refusal happens without a round trip. The EMPTY
        // array is refused too — presence of the key, not its content, is the
        // rule.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'{$field}' is not part of the invoice contract");

        \Facturino\Invoice::create([
            'customerId' => 'cus_8f2k4m9n',
            'taxDecisionId' => 'taxdec_9c1f',
            'decisionLines' => [['taxLineRef' => 'abo-pro', 'unit' => 'month']],
            'buyer' => ['companyName' => 'ACME SAS'],
            'dates' => ['issued' => '2026-09-15', 'due' => '2026-10-15'],
            'payment' => ['terms' => '30 jours', 'termsDays' => 30, 'method' => 'transfer'],
            $field => $value,
        ]);
    }

    /** @return array<string, array{string, array<int, mixed>}> */
    public static function explicitVatFieldProvider(): array
    {
        return [
            'lines' => ['lines', [['placeholder' => true]]],
            'items' => ['items', [['placeholder' => true]]],
            'empty lines' => ['lines', []],
            'empty items' => ['items', []],
        ];
    }

    public function testInvoiceCreateRefusesAMissingDecision(): void
    {
        // No route either: nothing must leave the machine.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'taxDecisionId' is required");

        \Facturino\Invoice::create([
            'customerId' => 'cus_8f2k4m9n',
            'decisionLines' => [['taxLineRef' => 'abo-pro', 'unit' => 'month']],
            'buyer' => ['companyName' => 'ACME SAS'],
            'dates' => ['issued' => '2026-09-15', 'due' => '2026-10-15'],
            'payment' => ['terms' => '30 jours', 'termsDays' => 30, 'method' => 'transfer'],
        ]);
    }

    public function testInvoiceCreateRefusesMissingDecisionLines(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'decisionLines' is required");

        \Facturino\Invoice::create([
            'customerId' => 'cus_8f2k4m9n',
            'taxDecisionId' => 'taxdec_9c1f',
            'buyer' => ['companyName' => 'ACME SAS'],
            'dates' => ['issued' => '2026-09-15', 'due' => '2026-10-15'],
            'payment' => ['terms' => '30 jours', 'termsDays' => 30, 'method' => 'transfer'],
        ]);
    }

    public function testDepositsAndScheduleTravelWithTheDecision(): void
    {
        $this->server->respondWith(201, '{"id": "inv_2", "object": "invoice", "status": "draft"}');

        \Facturino\Invoice::create([
            'customerId' => 'cus_8f2k4m9n',
            'taxDecisionId' => 'taxdec_9c1f',
            'decisionLines' => [['taxLineRef' => 'abo-pro', 'unit' => 'month']],
            'buyer' => ['companyName' => 'ACME SAS'],
            'dates' => ['issued' => '2026-09-15', 'due' => '2026-10-15'],
            'payment' => ['terms' => '30 jours', 'termsDays' => 30, 'method' => 'transfer'],
            'deposits' => [['invoiceId' => 'inv_dep']],
            'schedule' => [
                ['amount' => 1740, 'dueDate' => '2026-10-15'],
                ['amount' => 1740, 'dueDate' => '2026-11-15'],
            ],
        ]);

        // Both settle SERVER-SIDE against the decided amount; the SDK only
        // forwards them.
        $body = json_decode($this->server->lastRequest()['body'], true);
        $this->assertSame([['invoiceId' => 'inv_dep']], $body['deposits']);
        $this->assertSame([1740, 1740], array_column($body['schedule'], 'amount'));
    }

    public function testIntegrationSourceLinesCarryTheirSuppliedVat(): void
    {
        $this->server->respondWith(201, '{
            "id": "taxdec_int1", "object": "tax_decision", "status": "final",
            "taxSource": "integration", "amountToCharge": 3480
        }');

        $decision = \Facturino\TaxDecision::create([
            'taxSource' => 'integration',
            'customerId' => 'cus_8f2k4m9n',
            'effectiveAt' => '2026-09-15',
            'currency' => 'eur',
            'priceMode' => 'tax_exclusive',
            'lines' => [
                [
                    'reference' => 'conseil', 'description' => 'Conseil',
                    'category' => 'services', 'unitAmount' => 2900,
                    'quantity' => '1', 'vatRate' => 2000, 'vatCode' => 'S',
                ],
                [
                    'reference' => 'formation', 'description' => 'Formation exportée',
                    'category' => 'services', 'unitAmount' => 5000,
                    'quantity' => '1', 'vatRate' => 0, 'vatCode' => 'G',
                    'vatexCode' => 'VATEX-EU-G', 'placeOfSupply' => 'GB',
                ],
            ],
        ], 'order-integration-1');

        $body = json_decode($this->server->lastRequest()['body'], true);
        $this->assertSame('integration', $body['taxSource']);
        $this->assertSame(2000, $body['lines'][0]['vatRate']);
        $this->assertSame('VATEX-EU-G', $body['lines'][1]['vatexCode']);
        $this->assertSame('GB', $body['lines'][1]['placeOfSupply']);
        $this->assertSame('integration', $decision['taxSource']);
    }

    public function testBindTaxDecisionFiscalisesTheConvertedDraft(): void
    {
        $this->server->respondWith(200, '{
            "id": "inv_converted", "object": "invoice", "status": "draft",
            "taxSource": "facturino", "taxDecisionId": "taxdec_9c1f", "number": null
        }');

        $invoice = \Facturino\Invoice::bindTaxDecision('inv_converted', [
            'taxDecisionId' => 'taxdec_9c1f',
            'decisionLines' => [['taxLineRef' => 'l1', 'unit' => 'unit']],
        ]);

        $request = $this->server->lastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertStringContainsString('/v1/invoices/inv_converted/bind-tax-decision', $request['path']);
        $body = json_decode($request['body'], true);
        $this->assertSame('taxdec_9c1f', $body['taxDecisionId']);
        $this->assertSame('l1', $body['decisionLines'][0]['taxLineRef']);
        // Binding freezes the VAT; the invoice is issued by finalize().
        $this->assertSame('draft', $invoice['status']);
        $this->assertSame('taxdec_9c1f', $invoice['taxDecisionId']);
        $this->assertNull($invoice['number']);
    }

    public function testBindTaxDecisionRefusesLocallyWithoutADecision(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('taxDecisionId');

        \Facturino\Invoice::bindTaxDecision('inv_converted', [
            'decisionLines' => [['taxLineRef' => 'l1', 'unit' => 'unit']],
        ]);
    }

    public function testBindTaxDecisionRefusesLocallyAnEmptyPresentation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('decisionLines');

        \Facturino\Invoice::bindTaxDecision('inv_converted', [
            'taxDecisionId' => 'taxdec_9c1f',
            'decisionLines' => [],
        ]);
    }

    public function testInvoiceExposesTheThreeAxes(): void
    {
        $this->server->respondWith(200, '{
            "id": "inv_1", "object": "invoice", "status": "deposited",
            "documentStatus": "finalized", "transmissionStatus": "deposited",
            "transmissionDetail": null, "paymentStatus": "partially_paid"
        }');

        $invoice = \Facturino\Invoice::retrieve('inv_1');

        // A collection never moves the transmission axis; `status` stays
        // populated as their projection.
        $this->assertSame('deposited', $invoice['status']);
        $this->assertSame('finalized', $invoice['documentStatus']);
        $this->assertSame('deposited', $invoice['transmissionStatus']);
        $this->assertSame('partially_paid', $invoice['paymentStatus']);
    }

    public function testCreditNoteUsesCreditedLines(): void
    {
        $this->server->respondWith(201, '{
            "id": "crn_1", "object": "credit_note", "status": "draft",
            "taxSource": "facturino", "originalInvoiceId": "inv_1",
            "originalTaxDecisionId": "taxdec_9c1f"
        }');

        $creditNote = \Facturino\CreditNote::create([
            'relatedInvoiceId' => 'inv_1',
            'creditNoteType' => 'partial',
            'reasonCode' => 'quality',
            'creditedLines' => [['taxLineRef' => 'abo-pro', 'amountTTC' => 1200]],
        ]);

        $body = json_decode($this->server->lastRequest()['body'], true);
        $this->assertSame([['taxLineRef' => 'abo-pro', 'amountTTC' => 1200]], $body['creditedLines']);
        // The VAT is inherited from the invoice snapshot, never restated.
        $this->assertArrayNotHasKey('items', $body);
        $this->assertSame('taxdec_9c1f', $creditNote['originalTaxDecisionId']);
    }

    public function testRecurringScheduleSendsTaxInputs(): void
    {
        $this->server->respondWith(201, '{"id": "rin_1", "object": "recurring_invoice"}');

        \Facturino\RecurringInvoice::create([
            'customerId' => 'cus_1',
            'frequency' => 'monthly',
            'startDate' => '2026-09-01',
            'nextGenerationDate' => '2026-09-01',
            'taxInputs' => [
                'priceMode' => 'tax_exclusive',
                'lines' => [[
                    'reference' => 'abo-pro', 'description' => 'Abonnement Pro',
                    'category' => 'electronically_supplied_services',
                    'rateCategory' => 'standard', 'unitAmount' => 2900,
                    'quantity' => '1', 'unit' => 'month',
                ]],
            ],
            'templateInvoice' => ['paymentTermsDays' => 30],
        ]);

        $raw = $this->server->lastRequest()['body'];
        $body = json_decode($raw, true);
        $this->assertSame('tax_exclusive', $body['taxInputs']['priceMode']);
        $this->assertSame('1', $body['taxInputs']['lines'][0]['quantity']);
        // No decision id travels: each occurrence is decided on its own date.
        $this->assertStringNotContainsString('taxDecisionId', $raw);
        $this->assertArrayNotHasKey('items', $body['templateInvoice']);
    }

}
