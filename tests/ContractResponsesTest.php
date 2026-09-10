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
            'creditNote' => Model\CreditNote::class, 'taxDecision' => Model\TaxDecision::class, 'event' => Model\WebhookEvent::class];
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
