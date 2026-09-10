<?php

declare(strict_types=1);

namespace Facturino\Tests;

use Facturino\Facturino;
use Facturino\Model;
use Facturino\Util\Webhook;
use PHPUnit\Framework\TestCase;

final class ContractTest extends TestCase
{
    private function fixture(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/fixtures/contract-2.7.0.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testTypedModelsKeepTheCodedVerdictAndNulls(): void
    {
        $fixture = $this->fixture();
        $invoice = Model\Invoice::fromArray($fixture['invoice']);
        $this->assertSame('FAC2026-00090', $invoice->number);
        $this->assertSame(Model\PaRejectionCategory::AddressingError, $invoice->einvoicing->rejectionCategory);
        $this->assertSame(Model\PaRejectionSource::Platform, $invoice->einvoicing->rejectionSource);
        $this->assertNull($invoice->einvoicing->rejectionNote);
        $this->assertNull($invoice->einvoicing->buyerReachableAt);
        $this->assertSame('0225:73282932000074', $invoice->einvoicing->submissionArtefact->routingIdentifier);
        $this->assertSame(Model\PaRejectionCategory::Other, $invoice->einvoicing->previousSubmissions[0]->rejectionCategory);
        $this->assertSame('L’adresse de réception doit être confirmée.', $invoice->einvoicing->previousSubmissions[0]->rejectionNote);
        $this->assertSame($fixture['invoice'], $invoice->jsonSerialize());
        $this->assertCount(11, Model\PaRejectionCategory::cases());
        $this->assertCount(3, Model\PaRejectionSource::cases());
        $payment = Model\Payment::fromArray($fixture['payment']);
        $this->assertSame('awaiting_deposit', $payment->fr212->state);
        $this->assertNull($payment->fr212->sentAt);
        $this->assertSame('Invoice identifier is rejected.', Model\Payment::fromArray($fixture['blockedPayment'])->fr212->lastErrorReason);
        $this->assertSame('FAC2026-00090', Model\CreditNote::fromArray($fixture['creditNote'])->relatedInvoiceNumber);
        $this->assertSame('buyer_nature_suspect', Model\Customer::fromArray($fixture['customer'])->warnings[0]->code);
        $this->assertSame('customerId', Model\TaxDecision::fromArray($fixture['taxDecision'])->warnings[0]->param);
    }

    public function testSignedEventsAndTypedProjectionPreservePayload(): void
    {
        foreach ($this->fixture()['events'] as $example) {
            $payload = json_encode($example, JSON_THROW_ON_ERROR);
            $timestamp = time();
            $signature = hash_hmac('sha256', $timestamp . '.' . $payload, 'whsec_contract_fixture');
            $raw = Webhook::constructEvent($payload, 't=' . $timestamp . ',v1=' . $signature, 'whsec_contract_fixture');
            $event = Model\WebhookEvent::fromArray($raw);
            $this->assertSame($example, $event->jsonSerialize());
            $this->assertSame('2026-09-01', $event->apiVersion);
            $this->assertNull($event->request);
            $this->assertSame($example['data']['rejectionReason'], $event->data->rejectionReason);
        }
    }

    public function testReadAndTargetedReplayKeepTheExistingArrayApi(): void
    {
        $server = new LoopbackServer();
        Facturino::setApiKey('fac_test_contract');
        Facturino::setApiBase($server->baseUrl());
        try {
            $server->respondWith(200, json_encode($this->fixture()['invoice'], JSON_THROW_ON_ERROR));
            $this->assertSame($this->fixture()['invoice'], \Facturino\Invoice::retrieve('inv_00090'));
            $receipt = ['id' => 'evt_example', 'object' => 'event', 'retryScheduled' => true, 'endpointId' => 'we_example'];
            $server->respondWith(200, json_encode($receipt, JSON_THROW_ON_ERROR));
            $result = \Facturino\Event::retry('evt_example', 'we_example');
            $this->assertSame($receipt, $result);
            $this->assertTrue(Model\EventRetryResult::fromArray($result)->retryScheduled);
            $this->assertSame(['endpointId' => 'we_example'], json_decode($server->lastRequest()['body'], true));
        } finally {
            $server->stop();
            Facturino::reset();
        }
    }
}
