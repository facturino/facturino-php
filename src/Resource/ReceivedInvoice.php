<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * ReceivedInvoice resource — /v1/received-invoices
 *
 * Invoices received from suppliers via the PA (Plateforme Agreee).
 */
class ReceivedInvoice
{
    private const BASE_PATH = '/v1/received-invoices';

    /**
     * List received invoices with cursor-based pagination.
     *
     * @param array<string, mixed> $params  Query parameters (limit, starting_after, status)
     * @return Collection
     */
    public static function all(array $params = []): Collection
    {
        $response = HttpClient::request('GET', self::BASE_PATH, null, $params);

        return new Collection($response, $params, self::BASE_PATH);
    }

    /**
     * Retrieve a single received invoice.
     *
     * @param string $id Received invoice ID (rin_xxx)
     * @return array<string, mixed>
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }

    /**
     * Approve a received invoice (sends fr:205 lifecycle event to PA).
     *
     * @param string $id Received invoice ID
     * @return array<string, mixed>
     */
    public static function approve(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/approve');
    }

    /**
     * Refuse a received invoice (sends fr:206 lifecycle event to PA).
     *
     * @param string               $id      Received invoice ID
     * @param array<string, mixed> $params  Refusal data (reason is required)
     * @return array<string, mixed>
     */
    public static function refuse(string $id, array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/refuse', $params);
    }

    /**
     * Suspend a received invoice (sends fr:207 lifecycle event to PA).
     *
     * @param string $id Received invoice ID
     * @return array<string, mixed>
     */
    public static function suspend(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/suspend');
    }

    /**
     * Record a payment on a received invoice (sends fr:212 lifecycle event to PA).
     *
     * @param string               $id      Received invoice ID
     * @param array<string, mixed> $params  Payment data (amount in centimes, method, reference, paidAt)
     * @return array<string, mixed>
     */
    public static function recordPayment(string $id, array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/record-payment', $params);
    }
}
