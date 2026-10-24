<?php

declare(strict_types=1);

namespace Facturino\Tests;

use Facturino\Facturino;
use Facturino\Model;
use PHPUnit\Framework\TestCase;

/** Product and quote contract tests against the loopback recorder. */
final class ProductQuoteContractTest extends TestCase
{
    private LoopbackServer $server;

    protected function setUp(): void
    {
        Facturino::reset();
        $this->server = new LoopbackServer();
        Facturino::setApiKey('fac_test_supply');
        Facturino::setApiBase($this->server->baseUrl());
    }

    protected function tearDown(): void
    {
        $this->server->stop();
        Facturino::reset();
    }

    /** @return array<string, mixed> */
    private function fixture(string $name): array
    {
        $bodies = json_decode(file_get_contents(__DIR__ . '/fixtures/sdk/supply-category.json'), true, 512, JSON_THROW_ON_ERROR);
        return $bodies[$name];
    }

    public function testProductCreateAndUpdatePassOptionalFields(): void
    {
        $response = $this->fixture('product');
        $params = $response;
        unset($params['id'], $params['object'], $params['active']);
        $this->server->respondWith(201, json_encode($response, JSON_THROW_ON_ERROR));

        $product = \Facturino\Product::create($params, 'product-supply');

        $request = $this->server->lastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertSame('/v1/products', $request['path']);
        $this->assertSame('product-supply', $request['headers']['Idempotency-Key']);
        $this->assertSame($params, json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame($response, $product);

        $changes = [
            'reference' => 'TRAIN-REVISED', 'category' => 'training', 'active' => false,
            'kind' => 'goods', 'supplyCategory' => 'goods', 'tags' => [],
            'vatexCode' => 'VATEX-FR-CGI295',
        ];
        $updated = array_replace($response, $changes);
        $this->server->respondWith(200, json_encode($updated, JSON_THROW_ON_ERROR));

        $product = \Facturino\Product::update('prod_supply', $changes);

        $request = $this->server->lastRequest();
        $this->assertSame('PATCH', $request['method']);
        $this->assertSame('/v1/products/prod_supply', $request['path']);
        $this->assertSame($changes, json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertFalse($product['active']);
        $this->assertSame([], $product['tags']);
        $this->assertSame($updated, $product);
    }

    public function testProductRetrievePreservesHistoricalOmissionsAndLegacyVatex(): void
    {
        $body = $this->fixture('historicalProduct');
        $this->server->respondWith(200, json_encode($body, JSON_THROW_ON_ERROR));

        $product = \Facturino\Product::retrieve('prod_historical');

        $request = $this->server->lastRequest();
        $this->assertSame('GET', $request['method']);
        $this->assertSame('/v1/products/prod_historical', $request['path']);
        $this->assertArrayNotHasKey('kind', $product);
        $this->assertArrayNotHasKey('supplyCategory', $product);
        $this->assertSame('VATEX-FR-261', $product['vatexCode']);
        $this->assertSame($body, $product);
    }

    public function testQuoteCreateAndUpdatePassSupplyCategory(): void
    {
        $response = $this->fixture('quote');
        $line = $response['items'][0];
        unset($line['id']);
        $params = ['customerId' => 'cus_supply', 'lines' => [$line]];
        $this->server->respondWith(201, json_encode($response, JSON_THROW_ON_ERROR));

        $quote = \Facturino\Quote::create($params, 'quote-supply');

        $request = $this->server->lastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertSame('/v1/quotes', $request['path']);
        $this->assertSame('quote-supply', $request['headers']['Idempotency-Key']);
        $this->assertSame($params, json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame($response, $quote);

        $line['supplyCategory'] = 'future_supply';
        $changes = ['lines' => [$line]];
        $response['items'][0]['supplyCategory'] = 'future_supply';
        $this->server->respondWith(200, json_encode($response, JSON_THROW_ON_ERROR));

        $quote = \Facturino\Quote::update('quo_supply', $changes);

        $request = $this->server->lastRequest();
        $this->assertSame('PATCH', $request['method']);
        $this->assertSame('/v1/quotes/quo_supply', $request['path']);
        $this->assertSame($changes, json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame($response, $quote);
    }

    public function testQuoteRetrieveAndConvertPreserveSupplyCategory(): void
    {
        $quoteBody = $this->fixture('quote');
        $this->server->respondWith(200, json_encode($quoteBody, JSON_THROW_ON_ERROR));

        $quote = \Facturino\Quote::retrieve('quo_supply');

        $this->assertSame('GET', $this->server->lastRequest()['method']);
        $this->assertSame('/v1/quotes/quo_supply', $this->server->lastRequest()['path']);
        $this->assertSame($quoteBody, $quote);

        $draftBody = $this->fixture('convertedDraft');
        $this->server->respondWith(201, json_encode($draftBody, JSON_THROW_ON_ERROR));

        $draft = \Facturino\Quote::convert('quo_supply');

        $request = $this->server->lastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertSame('/v1/quotes/quo_supply/convert', $request['path']);
        $this->assertSame($draftBody, $draft);
        $this->assertSame($quote['items'][0]['supplyCategory'], $draft['commercialDraft']['lines'][0]['supplyCategory']);
        $this->assertSame($draftBody, Model\Invoice::fromArray($draft)->jsonSerialize());
    }

    public function testHistoricalQuoteWritesAndReadsDoNotInventSupplyCategory(): void
    {
        $quoteBody = $this->fixture('historicalQuote');
        $line = $quoteBody['items'][0];
        unset($line['id']);
        $params = ['customerId' => 'cus_supply', 'lines' => [$line]];
        $this->server->respondWith(201, json_encode($quoteBody, JSON_THROW_ON_ERROR));

        $quote = \Facturino\Quote::create($params);
        $this->assertSame($params, json_decode($this->server->lastRequest()['body'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame($quoteBody, $quote);

        $this->server->respondWith(200, json_encode($quoteBody, JSON_THROW_ON_ERROR));
        \Facturino\Quote::update('quo_historical', ['lines' => [$line]]);
        $this->assertSame(['lines' => [$line]], json_decode($this->server->lastRequest()['body'], true, 512, JSON_THROW_ON_ERROR));

        $this->assertSame($quoteBody, \Facturino\Quote::retrieve('quo_historical'));
        $draftBody = $this->fixture('convertedDraft');
        unset(
            $draftBody['items'][0]['kind'], $draftBody['items'][0]['supplyCategory'],
            $draftBody['commercialDraft']['lines'][0]['kind'], $draftBody['commercialDraft']['lines'][0]['supplyCategory']
        );
        $this->server->respondWith(201, json_encode($draftBody, JSON_THROW_ON_ERROR));

        $draft = \Facturino\Quote::convert('quo_historical');

        $this->assertArrayNotHasKey('supplyCategory', $draft['items'][0]);
        $this->assertArrayNotHasKey('supplyCategory', $draft['commercialDraft']['lines'][0]);
        $this->assertSame($draftBody, Model\Invoice::fromArray($draft)->jsonSerialize());
    }
}
