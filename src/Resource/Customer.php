<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * Customer resource — /v1/customers
 */
class Customer
{
    private const BASE_PATH = '/v1/customers';

    /**
     * Create a customer.
     *
     * Each entry of `contacts[]` may carry a `role` of `billing`,
     * `technical` or `main`. The `billing` contact receives invoices
     * by default.
     *
     * @param array<string, mixed> $params          Customer data
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
     * List customers with cursor-based pagination.
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
     * Retrieve a single customer.
     *
     * @param string $id Customer ID (cus_xxx)
     * @return array<string, mixed>
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }

    /**
     * Update a customer.
     *
     * Each entry of `contacts[]` may carry a `role` of `billing`,
     * `technical` or `main`. The `billing` contact receives invoices
     * by default.
     *
     * @param string               $id      Customer ID
     * @param array<string, mixed> $params  Fields to update
     * @return array<string, mixed>
     */
    public static function update(string $id, array $params): array
    {
        return HttpClient::request('PATCH', self::BASE_PATH . '/' . $id, $params);
    }

    /**
     * Soft-delete a customer.
     *
     * @param string $id Customer ID
     * @return array<string, mixed>
     */
    public static function delete(string $id): array
    {
        return HttpClient::request('DELETE', self::BASE_PATH . '/' . $id);
    }

    /**
     * Look up a customer by SIRET or name via the Sirene API.
     *
     * @param array<string, mixed> $params  Lookup parameters (siret or query)
     * @return array<string, mixed>
     */
    public static function lookup(array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/lookup', $params);
    }

    /**
     * Import customers from a CSV string (max 1000 rows).
     *
     * @param string $csv  CSV content (header row + one customer per line).
     * @return array<string, mixed> Job object (202 Accepted).
     */
    public static function importCsv(string $csv): array
    {
        /** @var array<string, mixed> */
        return HttpClient::request('POST', self::BASE_PATH . '/import', ['csv' => $csv]);
    }

    /**
     * Export the customer list as raw CSV (Content-Type text/csv).
     *
     * @return string CSV content.
     */
    public static function exportCsv(): string
    {
        /** @var string */
        return HttpClient::request('GET', self::BASE_PATH . '/export', null, null, [], true);
    }
}
