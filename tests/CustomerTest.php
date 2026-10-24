<?php

declare(strict_types=1);

namespace Facturino\Tests;

use Facturino\Facturino;
use PHPUnit\Framework\TestCase;

/** Customer contract tests against the loopback recorder. */
final class CustomerTest extends TestCase
{
    private LoopbackServer $server;

    protected function setUp(): void
    {
        Facturino::reset();
        $this->server = new LoopbackServer();
        Facturino::setApiKey('fac_test_customer');
        Facturino::setApiBase($this->server->baseUrl());
    }

    protected function tearDown(): void
    {
        $this->server->stop();
        Facturino::reset();
    }

    public function testCreatePostsBothTaxStatuses(): void
    {
        foreach (['taxable', 'non_taxable'] as $taxStatus) {
            $params = ['name' => 'Client Saint-Martin', 'type' => 'company', 'taxStatus' => $taxStatus];
            $response = ['id' => 'cus_local_tax', 'object' => 'customer'] + $params;
            $this->server->respondWith(201, json_encode($response, JSON_THROW_ON_ERROR));

            $customer = \Facturino\Customer::create($params);

            $request = $this->server->lastRequest();
            $this->assertSame('POST', $request['method']);
            $this->assertSame('/v1/customers', $request['path']);
            $this->assertSame($params, json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR));
            $this->assertSame($response, $customer);
        }
    }

    public function testUpdatePatchesBothTaxStatuses(): void
    {
        foreach (['taxable', 'non_taxable'] as $taxStatus) {
            $params = ['taxStatus' => $taxStatus];
            $response = ['id' => 'cus_local_tax', 'object' => 'customer', 'taxStatus' => $taxStatus];
            $this->server->respondWith(200, json_encode($response, JSON_THROW_ON_ERROR));

            $customer = \Facturino\Customer::update('cus_local_tax', $params);

            $request = $this->server->lastRequest();
            $this->assertSame('PATCH', $request['method']);
            $this->assertSame('/v1/customers/cus_local_tax', $request['path']);
            $this->assertSame($params, json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR));
            $this->assertSame($response, $customer);
        }
    }

    public function testRetrievePreservesTaxStatusOrItsHistoricalAbsence(): void
    {
        foreach (['taxable', 'non_taxable', null] as $taxStatus) {
            $response = ['id' => 'cus_local_tax', 'object' => 'customer'];
            if ($taxStatus !== null) {
                $response['taxStatus'] = $taxStatus;
            }
            $this->server->respondWith(200, json_encode($response, JSON_THROW_ON_ERROR));

            $customer = \Facturino\Customer::retrieve('cus_local_tax');

            $request = $this->server->lastRequest();
            $this->assertSame('GET', $request['method']);
            $this->assertSame('/v1/customers/cus_local_tax', $request['path']);
            $this->assertSame($response, $customer);
        }
    }

    public function testOmittedTaxStatusIsNotAddedToWrites(): void
    {
        $this->server->respondWith(201, '{"id":"cus_local_tax","object":"customer"}');
        $params = ['name' => 'Client Saint-Martin'];

        \Facturino\Customer::create($params);
        $this->assertSame($params, json_decode($this->server->lastRequest()['body'], true, 512, JSON_THROW_ON_ERROR));

        $this->server->respondWith(200, '{"id":"cus_local_tax","object":"customer"}');
        \Facturino\Customer::update('cus_local_tax', $params);
        $this->assertSame($params, json_decode($this->server->lastRequest()['body'], true, 512, JSON_THROW_ON_ERROR));
    }
}
