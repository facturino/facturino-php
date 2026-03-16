<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * Product resource — /v1/products
 */
class Product
{
    private const BASE_PATH = '/v1/products';

    /**
     * Create a product.
     *
     * @param array<string, mixed> $params          Product data
     * @param string|null          $idempotencyKey  Idempotency key
     * @return array<string, mixed>
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
     * @param array<string, mixed> $params  Query parameters
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
     * @return array<string, mixed>
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }

    /**
     * Update a product.
     *
     * @param string               $id      Product ID
     * @param array<string, mixed> $params  Fields to update
     * @return array<string, mixed>
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
     * Import products from CSV rows.
     *
     * @param array<string, mixed> $params  Import data (rows: array of product objects)
     * @return array<string, mixed> Job object
     */
    public static function importCsv(array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/import', $params);
    }

    /**
     * Export products as CSV (async job).
     *
     * @return array<string, mixed> Job object
     */
    public static function exportCsv(): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/export');
    }
}
