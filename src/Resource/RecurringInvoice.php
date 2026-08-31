<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * RecurringInvoice resource — /v1/recurring-invoices
 */
class RecurringInvoice
{
    private const BASE_PATH = '/v1/recurring-invoices';

    /**
     * Create a recurring invoice schedule.
     *
     * `taxInputs` (`taxSource`, `priceMode` plus commercial lines) is
     * required: every occurrence is decided on ITS OWN generation date. A
     * recurrence never stores a decision — one taken today would apply this
     * quarter's rules to next year's invoice — so no `taxDecisionId` is ever
     * reused. `templateInvoice` carries document-only template data (payment
     * terms, notes); never VAT.
     *
     *   ['customerId' => 'cus_1', 'frequency' => 'monthly',
     *    'taxInputs' => ['taxSource' => 'facturino',
     *      'priceMode' => 'tax_exclusive', 'lines' => [[
     *        'reference' => 'abo-pro', 'description' => 'Abonnement Pro',
     *        'category' => 'electronically_supplied_services',
     *        'rateCategory' => 'standard', 'unitAmount' => 2900,
     *        'quantity' => '1', 'unit' => 'month']]],
     *    'templateInvoice' => ['paymentTermsDays' => 30]]
     *
     * @param array<string, mixed> $params          Schedule data
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
     * List recurring invoices with cursor-based pagination.
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
     * Retrieve a recurring invoice.
     *
     * @param string $id Recurring invoice ID (rin_xxx)
     * @return array<string, mixed>
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }

    /**
     * Update a recurring invoice.
     *
     * @param string               $id      Recurring invoice ID
     * @param array<string, mixed> $params  Fields to update
     * @return array<string, mixed>
     */
    public static function update(string $id, array $params): array
    {
        return HttpClient::request('PATCH', self::BASE_PATH . '/' . $id, $params);
    }

    /**
     * Delete a recurring invoice (soft-delete).
     *
     * @param string $id Recurring invoice ID
     * @return array<string, mixed>
     */
    public static function delete(string $id): array
    {
        return HttpClient::request('DELETE', self::BASE_PATH . '/' . $id);
    }

    /**
     * Activate (resume) a recurring invoice.
     *
     * @param string $id Recurring invoice ID
     * @return array<string, mixed>
     */
    public static function resume(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/resume');
    }

    /**
     * Pause (deactivate) a recurring invoice.
     *
     * @param string $id Recurring invoice ID
     * @return array<string, mixed>
     */
    public static function pause(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/pause');
    }
}
