<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * Product resource — /v1/products
 *
 * Products store and return optional `kind` (`goods` or `service`) and
 * `supplyCategory`. The latter is an open string: `goods`, `services`,
 * `electronically_supplied_services`, `deposit` and `ancillary_costs` are the
 * current values. Missing historical fields stay absent; no category is assumed.
 * `kind` classifies the document; `supplyCategory` declares its fiscal nature.
 * Deposits and ancillary costs follow the document's principal operation.
 *
 * vatexCode is an open string from the official CIUS-FR list (88 codes).
 * Legacy VATEX-FR-261 and VATEX-FR-AUTOLIQ inputs are stored and returned as
 * VATEX-FR-CGI261-4 and VATEX-FR-AE. Documents issued earlier keep their code.
 */
class Product
{
    private const BASE_PATH = '/v1/products';

    /**
     * Create a product.
     *
     * Optional kind and supplyCategory are stored and returned, alongside
     * tags and the open-string vatexCode.
     *
     * @param array{name: string, unitPrice: int, vatRate: int, vatCode: string, unit: string, description?: string, reference?: string, category?: string, tags?: list<string>, vatexCode?: string, kind?: 'goods'|'service', supplyCategory?: string, ...} $params Product data
     * @param string|null          $idempotencyKey  Idempotency key
     * @return array{kind?: 'goods'|'service', supplyCategory?: string, vatexCode?: string, ...}
     */
    public static function create(array $params, ?string $idempotencyKey = null): array
    {
        $headers = [];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return HttpClient::request('POST', self::BASE_PATH, $params, null, $headers);
    }

    /**
     * List products with cursor-based pagination.
     *
     * Optional filters: `q` (name prefix search), `category` (string)
     * and `active` (bool).
     *
     * @param array<string, mixed> $params  Query parameters (limit, starting_after, q, category, active)
     * @return Collection
     */
    public static function all(array $params = []): Collection
    {
        $response = HttpClient::request('GET', self::BASE_PATH, null, $params);

        return new Collection($response, $params, self::BASE_PATH);
    }

    /**
     * Retrieve a single product.
     *
     * @param string $id Product ID (prod_xxx)
     * @return array{kind?: 'goods'|'service', supplyCategory?: string, vatexCode?: string, ...}
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }

    /**
     * Update a product.
     *
     * reference, category and active are accepted alongside the price, VAT,
     * tags, optional kind and open-string supplyCategory and vatexCode.
     * Omitted fields are left unchanged; false keeps a product inactive.
     *
     * @param string               $id      Product ID
     * @param array{name?: string, description?: string, unitPrice?: int, vatRate?: int, vatCode?: string, unit?: string, reference?: string, category?: string, active?: bool, tags?: list<string>, vatexCode?: string, kind?: 'goods'|'service', supplyCategory?: string, ...} $params Fields to update
     * @return array{kind?: 'goods'|'service', supplyCategory?: string, vatexCode?: string, ...}
     */
    public static function update(string $id, array $params): array
    {
        return HttpClient::request('PATCH', self::BASE_PATH . '/' . $id, $params);
    }

    /**
     * Soft-delete a product.
     *
     * @param string $id Product ID
     * @return array<string, mixed>
     */
    public static function delete(string $id): array
    {
        return HttpClient::request('DELETE', self::BASE_PATH . '/' . $id);
    }

    /**
     * Import products from a CSV string (max 1000 rows).
     *
     * @param string $csv  CSV content (header row + one product per line).
     * @return array<string, mixed> Job object (202 Accepted).
     */
    public static function importCsv(string $csv): array
    {
        /** @var array<string, mixed> */
        return HttpClient::request('POST', self::BASE_PATH . '/import', ['csv' => $csv]);
    }

    /**
     * Export the product catalog as raw CSV (Content-Type text/csv).
     *
     * @return string CSV content.
     */
    public static function exportCsv(): string
    {
        /** @var string */
        return HttpClient::request('GET', self::BASE_PATH . '/export', null, null, [], true);
    }
}
