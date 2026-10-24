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

    public function testCreatePassesOpenLocalTaxExemptionsUnderBothSources(): void
    {
        $this->server->respondWith(201, self::FINAL_DECISION);

        foreach (['facturino', 'integration'] as $source) {
            foreach (['cgism_253', 'not_used_in_saint_martin', 'future_exemption'] as $exemption) {
                $params = $this->decisionParams();
                $params['taxSource'] = $source;
                $params['lines'][0]['localTaxExemption'] = $exemption;
                if ($source === 'integration') {
                    $params['lines'][0]['vatRate'] = 0;
                    $params['lines'][0]['vatCode'] = 'O';
                    $params['lines'][0]['vatexCode'] = 'VATEX-EU-O';
                    $params['lines'][0]['placeOfSupply'] = 'MF';
                }

                \Facturino\TaxDecision::create($params, 'local-tax-' . $source . '-' . $exemption);

                $request = $this->server->lastRequest();
                $this->assertSame('POST', $request['method']);
                $this->assertSame('/v1/tax-decisions', $request['path']);
                $this->assertSame($params, json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR));
            }
        }
    }

    public function testRetrievePreservesLocalTaxOnDecisionsAndInvoices(): void
    {
        $bodies = json_decode(file_get_contents(__DIR__ . '/fixtures/contract/localTax.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->server->respondWith(200, json_encode($bodies['taxDecision'], JSON_THROW_ON_ERROR));

        $decision = \Facturino\TaxDecision::retrieve('taxdec_local_tax');

        $this->assertSame('GET', $this->server->lastRequest()['method']);
        $this->assertSame('/v1/tax-decisions/taxdec_local_tax', $this->server->lastRequest()['path']);
        $this->assertSame($bodies['taxDecision'], $decision);

        $this->server->respondWith(200, json_encode($bodies['invoice'], JSON_THROW_ON_ERROR));
        $invoice = \Facturino\Invoice::retrieve('inv_local_tax');

        $this->assertSame('GET', $this->server->lastRequest()['method']);
        $this->assertSame('/v1/invoices/inv_local_tax', $this->server->lastRequest()['path']);
        $this->assertSame($bodies['invoice'], $invoice);
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
            "settledObligations": {
                "invoiceChannel": "none", "transactionReporting": "ereporting", "paymentReporting": null
            },
            "issues": [{"code": "vies_unavailable", "message": "VIES is unreachable."}]
        }');

        $decision = \Facturino\TaxDecision::create($this->decisionParams(), 'order-pending');

        $this->assertSame('pending_verification', $decision['status']);
        // null, never 0: absent is not "nothing to charge".
        $this->assertNull($decision['amountToCharge']);
        $this->assertNull($decision['totals']);
        $this->assertSame('vies_unavailable', $decision['issues'][0]['code']);
        // The three document axes stay null — an axis is never read off a
        // decision that did not conclude — while what French law settled anyway
        // is carried as a VALUE.
        $this->assertNull($decision['invoiceChannel']);
        $this->assertSame('ereporting', $decision['settledObligations']['transactionReporting']);
        $this->assertNull($decision['settledObligations']['paymentReporting']);
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

    public function testEuB2cDestinationTraceIsReadable(): void
    {
        // The rate a document bears must be auditable years later: registry
        // version, source, verification date and period travel with it.
        $this->server->respondWith(201, '{
            "id": "taxdec_eu1", "object": "tax_decision", "status": "final",
            "taxSource": "facturino",
            "euB2cDestination": {
                "coveredLineIds": ["line-1"],
                "ruleKinds": ["tbe_services"],
                "destinationMemberState": "DE",
                "destinationTerritoryId": "DE",
                "place": "destination",
                "basis": "threshold_exceeded",
                "reference": "Directive 2006/112/CE art. 59 quater",
                "detail": "cap passed",
                "threshold": {
                    "decidedOn": "ledger_cumulative", "capCents": 1000000,
                    "stateId": "2026_test", "year": "2026", "stateVersion": 4, "sequence": 7,
                    "reservationId": "claim_1", "coverageMode": "mixed_channels",
                    "cumulativeBeforeMinCents": 140000, "operationValueMinCents": 2900,
                    "cumulativeAfterMinCents": 142900
                },
                "option": null,
                "mechanism": {"kind": "oss_union", "memberState": "DE", "reference": "regime UE"},
                "rate": {
                    "registryVersion": "eu-standard-rates-2026-09-01",
                    "memberState": "DE", "territoryId": "DE", "regionId": null,
                    "centipercent": 1900, "validFrom": "2026-09-01", "validTo": null,
                    "source": "Commission europeenne", "verifiedAt": "2026-09-01"
                }
            }
        }');

        $decision = \Facturino\TaxDecision::create([
            'taxSource' => 'facturino',
            'customerId' => 'cus_8f2k4m9n',
            'effectiveAt' => '2026-09-15',
            'currency' => 'eur',
            'priceMode' => 'tax_exclusive',
            'lines' => [[
                'reference' => 'line-1', 'description' => 'Abonnement',
                'category' => 'electronically_supplied_services',
                'rateCategory' => 'standard', 'unitAmount' => 2900, 'quantity' => '1',
            ]],
        ], 'order-eu-1');

        $this->assertSame('destination', $decision['euB2cDestination']['place']);
        $this->assertSame(1900, $decision['euB2cDestination']['rate']['centipercent']);
        $this->assertSame(
            'eu-standard-rates-2026-09-01',
            $decision['euB2cDestination']['rate']['registryVersion']
        );
        $this->assertSame('oss_union', $decision['euB2cDestination']['mechanism']['kind']);
        // Le registre sur lequel la décision s'est appuyée, et la tranche prise.
        $this->assertSame('2026_test', $decision['euB2cDestination']['threshold']['stateId']);
        $this->assertSame(142900, $decision['euB2cDestination']['threshold']['cumulativeAfterMinCents']);
    }

    public function testGoodsMovementReachesAnIntegrationLine(): void
    {
        // The distance-sale rule is decided by a fact, never by an assumption.
        $this->server->respondWith(201, '{
            "id": "taxdec_int2", "object": "tax_decision", "status": "final",
            "taxSource": "integration"
        }');

        \Facturino\TaxDecision::create([
            'taxSource' => 'integration',
            'customerId' => 'cus_8f2k4m9n',
            'effectiveAt' => '2026-09-15',
            'currency' => 'eur',
            'priceMode' => 'tax_exclusive',
            'lines' => [[
                'reference' => 'line-1', 'description' => 'Chaise', 'category' => 'goods',
                'goodsMovement' => 'dispatched_to_buyer_territory',
                'unitAmount' => 2900, 'quantity' => '1', 'vatRate' => 1900, 'vatCode' => 'S',
            ]],
        ], 'order-goods-1');

        $body = json_decode($this->server->lastRequest()['body'], true);
        $this->assertSame('dispatched_to_buyer_territory', $body['lines'][0]['goodsMovement']);
    }

    public function testThePlaceOfSupplyRuleReachesAnIntegrationLine(): void
    {
        // Optional, `general` when absent, and the rule the declared place is
        // checked under.
        $this->server->respondWith(201, '{
            "id": "taxdec_int3", "object": "tax_decision", "status": "final",
            "taxSource": "integration"
        }');

        \Facturino\TaxDecision::create([
            'taxSource' => 'integration',
            'customerId' => 'cus_8f2k4m9n',
            'effectiveAt' => '2026-09-15',
            'currency' => 'eur',
            'priceMode' => 'tax_exclusive',
            'lines' => [
                [
                    'reference' => 'line-1', 'description' => 'Prestation', 'category' => 'services',
                    'placeOfSupplyRule' => 'general', 'placeOfSupply' => 'FR-MET',
                    'unitAmount' => 2900, 'quantity' => '1', 'vatRate' => 2000, 'vatCode' => 'S',
                ],
                [
                    'reference' => 'line-2', 'description' => 'Prestation', 'category' => 'services',
                    'unitAmount' => 2900, 'quantity' => '1', 'vatRate' => 2000, 'vatCode' => 'S',
                ],
            ],
        ], 'order-rule-1');

        $body = json_decode($this->server->lastRequest()['body'], true);
        $this->assertSame('general', $body['lines'][0]['placeOfSupplyRule']);
        $this->assertSame('FR-MET', $body['lines'][0]['placeOfSupply']);
        // Absent stays absent: the API reads it as `general`.
        $this->assertArrayNotHasKey('placeOfSupplyRule', $body['lines'][1]);
    }

    public function testASaleTakenBackOutReadsAsAReleaseWithNegativeAmounts(): void
    {
        $this->server->respondWith(200, '{
            "object": "list", "url": "/v1/eu-threshold-ledgers/2026/entries",
            "data": [{
                "id": "rev_0123456789abcdef0123456789abcdef", "sequence": 4,
                "kind": "reservation_released", "amountMin": -12000, "amountMax": -12000,
                "taxDecisionId": "taxdec_c6_fixture"
            }],
            "has_more": false, "next_cursor": null
        }');

        $page = \Facturino\EuThresholdLedger::listEntries('2026');

        $entry = $page['data'][0];
        $this->assertSame('reservation_released', $entry['kind']);
        $this->assertLessThan(0, $entry['amountMin']);
        $this->assertLessThan(0, $entry['amountMax']);
    }

    public function testEuThresholdLedgerRoundTrips(): void
    {
        // Les totaux courants vivent dans leur propre registre annuel,
        // append-only, et portent DEUX compteurs séparés.
        $this->server->respondWith(201, '{
            "object": "eu_threshold_ledger", "id": "2026_test", "year": "2026",
            "status": "open", "review": null,
            "capCents": 1000000, "evidenceCapCents": 10000000,
            "acquiredMin": 100000, "acquiredEvidenceMin": 60000,
            "reservedMin": 0, "reservedMax": 0,
            "remainingMin": 900000, "evidenceRemainingMin": 9940000,
            "adjustmentTotal": 0, "correctionTotal": 0,
            "reservations": [], "entries": [],
            "entriesHasMore": false, "entriesNextCursor": null
        }');
        $ledger = \Facturino\EuThresholdLedger::open([
            'year' => '2026',
            'previousYearAmount' => 250000,
            'currentYearOpening' => 100000,
            'previousYearEvidenceAmount' => 150000,
            'currentYearEvidenceOpening' => 60000,
            'coverageMode' => 'mixed_channels',
            'externalCompleteThroughDate' => '2026-01-01',
        ]);
        $this->assertSame(900000, $ledger['remainingMin']);
        // Acquis et réservé se lisent à part : une tranche tenue peut disparaître.
        $this->assertSame(100000, $ledger['acquiredMin']);
        $this->assertSame(0, $ledger['reservedMax']);
        // Le second compteur a son propre plafond et son propre reste.
        $this->assertSame(10000000, $ledger['evidenceCapCents']);
        $this->assertSame(9940000, $ledger['evidenceRemainingMin']);
        $this->assertSame('/v1/eu-threshold-ledgers', $this->server->lastRequest()['path']);

        $this->server->respondWith(201, '{
            "object": "eu_threshold_ledger", "id": "2026_test", "year": "2026",
            "adjustmentTotal": 40000, "reservations": [], "entries": []
        }');
        $adjusted = \Facturino\EuThresholdLedger::adjust('2026', [
            'reference' => 'adj-marketplace-08',
            'amount' => 40000,
            'evidenceAmount' => 25000,
            'externalCompleteThroughDate' => '2026-09-15',
            'reason' => 'Marketplace sales, August',
        ]);
        $this->assertSame(40000, $adjusted['adjustmentTotal']);
        $this->assertSame(
            '/v1/eu-threshold-ledgers/2026/adjustments',
            $this->server->lastRequest()['path']
        );
        $body = json_decode($this->server->lastRequest()['body'], true);
        $this->assertSame('adj-marketplace-08', $body['reference']);
        $this->assertSame(25000, $body['evidenceAmount']);
    }

    public function testTheTwoCountersMayLegitimatelyDiverge(): void
    {
        // Aucun des deux ne borne l'autre : leurs périmètres diffèrent.
        $this->server->respondWith(201, '{
            "object": "eu_threshold_ledger", "acquiredMin": 10000, "acquiredEvidenceMin": 2000000
        }');
        $ledger = \Facturino\EuThresholdLedger::open([
            'year' => '2026',
            'previousYearAmount' => 10000,
            'currentYearOpening' => 10000,
            'previousYearEvidenceAmount' => 4000000,
            'currentYearEvidenceOpening' => 2000000,
            'coverageMode' => 'facturino_only',
            'externalCompleteThroughDate' => '2026-01-01',
        ]);
        $this->assertGreaterThan($ledger['acquiredMin'], $ledger['acquiredEvidenceMin']);
        $body = json_decode($this->server->lastRequest()['body'], true);
        $this->assertGreaterThan($body['previousYearAmount'], $body['previousYearEvidenceAmount']);
    }

    public function testAMovementPublishesItsRemainingBalance(): void
    {
        // Un mouvement rend ce qu'il a apporté, une fois.
        $this->server->respondWith(200, '{
            "object": "list", "url": "/v1/eu-threshold-ledgers/2026/entries",
            "data": [{
                "id": "opening", "kind": "opening", "amountMin": 100000,
                "correctable": true, "correctedMin": 30000, "correctionCount": 1,
                "remainingMin": 70000, "remainingEvidenceMin": 50000
            }],
            "has_more": false, "next_cursor": null
        }');
        $page = \Facturino\EuThresholdLedger::listEntries('2026');
        $this->assertSame(70000, $page['data'][0]['remainingMin']);
        $this->assertSame(30000, $page['data'][0]['correctedMin']);
    }

    public function testEuThresholdMovementsArePaginatedWithACursor(): void
    {
        $this->server->respondWith(200, '{
            "object": "list", "url": "/v1/eu-threshold-ledgers/2026/entries",
            "data": [{"id": "adj_abc", "sequence": 1, "kind": "external_adjustment"}],
            "has_more": true, "next_cursor": "adj_abc"
        }');
        $page = \Facturino\EuThresholdLedger::listEntries('2026', ['limit' => 1]);
        $this->assertSame('adj_abc', $page['next_cursor']);
        $this->assertSame('/v1/eu-threshold-ledgers/2026/entries', $this->server->lastRequest()['path']);
    }

    public function testAnAmountComesBackOnlyThroughAQualifiedCorrection(): void
    {
        // Art. 90 §1 réduit la base d'une opération IDENTIFIÉE : la correction
        // nomme le mouvement corrigé, sa qualification et sa preuve. Il n'existe
        // aucun montant négatif générique dans cette ressource.
        $this->server->respondWith(201, '{
            "object": "eu_threshold_ledger", "correctionTotal": 20000
        }');
        $corrected = \Facturino\EuThresholdLedger::correct('2026', [
            'reference' => 'cor-credit-note-12',
            'correctsEntryId' => 'adj_abc',
            'kind' => 'credit_note',
            'amount' => 20000,
            'evidenceAmount' => 10000,
            'relatedResourceType' => 'credit_note',
            'relatedResourceId' => 'crn_123',
            'evidenceReference' => 'AV-2026-0012',
            'reason' => 'Avoir total sur une vente comptee en aout',
        ]);
        $this->assertSame(20000, $corrected['correctionTotal']);
        $this->assertSame(
            '/v1/eu-threshold-ledgers/2026/corrections',
            $this->server->lastRequest()['path']
        );
        $body = json_decode($this->server->lastRequest()['body'], true);
        $this->assertSame('adj_abc', $body['correctsEntryId']);
        $this->assertSame('AV-2026-0012', $body['evidenceReference']);
    }

    public function testALedgerUnderReviewStopsDeciding(): void
    {
        $this->server->respondWith(200, '{
            "object": "eu_threshold_ledger", "status": "review_required",
            "review": {"code": "declared_by_administrator", "detail": "x", "openedAt": "y"}
        }');
        $reviewed = \Facturino\EuThresholdLedger::review('2026', ['reason' => 'Chiffre conteste']);
        $this->assertSame('review_required', $reviewed['status']);
        $this->assertSame('/v1/eu-threshold-ledgers/2026/review', $this->server->lastRequest()['path']);

        $this->server->respondWith(200, '{
            "object": "eu_threshold_ledger", "status": "open", "review": null
        }');
        $settled = \Facturino\EuThresholdLedger::resolveReview('2026', [
            'reconciledVersion' => 4,
            'reconciledAcquiredMin' => 100000,
            'reconciledAcquiredEvidenceMin' => 60000,
            'evidenceReference' => 'RECON-2026-09',
            'reason' => 'Corrige',
        ]);
        $this->assertSame('open', $settled['status']);
        // Un commentaire seul ne rouvre jamais un registre : les chiffres
        // vérifiés voyagent avec la demande.
        $body = json_decode($this->server->lastRequest()['body'], true);
        $this->assertSame(4, $body['reconciledVersion']);
        $this->assertSame(100000, $body['reconciledAcquiredMin']);
        $this->assertSame('RECON-2026-09', $body['evidenceReference']);
        $this->assertSame(
            '/v1/eu-threshold-ledgers/2026/review/resolve',
            $this->server->lastRequest()['path']
        );
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

    public function testFinalizeSendsNoBodyWithoutACollection(): void
    {
        $this->server->respondWith(200, '{"id": "inv_1", "status": "finalized", "number": "FAC-00001"}');

        \Facturino\Invoice::finalize('inv_1');

        $request = $this->server->lastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertStringContainsString('/v1/invoices/inv_1/finalize', $request['path']);
        $this->assertSame('', $request['body']);
    }

    public function testFinalizeIssuesAnAlreadyCollectedInvoiceAsSettled(): void
    {
        $this->server->respondWith(200, '{"id": "inv_1", "status": "paid", "number": "FAC-00001"}');

        $invoice = \Facturino\Invoice::finalize('inv_1', [
            'amount' => 120000,
            'method' => 'card',
            'reference' => 'ch_3Kj9aLZ',
            'paidAt' => '2026-05-10T12:00:00.000Z',
        ]);

        $this->assertSame('paid', $invoice['status']);
        $body = json_decode($this->server->lastRequest()['body'], true);
        $this->assertSame(120000, $body['payment']['amount']);
        $this->assertSame('card', $body['payment']['method']);
        $this->assertSame('2026-05-10T12:00:00.000Z', $body['payment']['paidAt']);
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
