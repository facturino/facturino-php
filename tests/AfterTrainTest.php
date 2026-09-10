<?php

declare(strict_types=1);
namespace Facturino\Tests;

use Facturino\Model;
use PHPUnit\Framework\TestCase;

final class AfterTrainTest extends TestCase
{
    public function testSellerRoutingAndPaymentAttribution(): void
    {
        $body = json_decode(file_get_contents(__DIR__ . '/fixtures/contract/after-train.json'), true, 512, JSON_THROW_ON_ERROR);
        $tracking = Model\InvoiceEinvoicing::fromArray($body['einvoicing']);
        $this->assertSame('0225:908456726_90845672600029', $tracking->senderRoutingIdentifier);
        $this->assertSame($tracking->senderRoutingIdentifier, $tracking->submissionArtefact->sellerRoutingIdentifier);
        foreach (['paymentEvent', 'legacyPaymentEvent', 'unattributedPaymentEvent'] as $key) {
            $event = Model\WebhookEvent::fromArray($body[$key]);
            $this->assertSame($body[$key], $event->jsonSerialize());
            if ($key === 'paymentEvent') {
                $this->assertSame('pay_example', $event->data->paymentId);
                $this->assertSame('pending', $event->data->fr212->state);
            } else {
                $this->assertNull($event->data->paymentId);
                $this->assertNull($event->data->fr212);
            }
        }
    }
}
