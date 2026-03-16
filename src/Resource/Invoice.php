<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * Invoice resource — /v1/invoices
 *
 * Amounts are integers in centimes (10000 = 100.00 EUR).
 * VAT rates are integers in centipercent (2000 = 20.00%).
 */
class Invoice
{
    private const BASE_PATH = '/v1/invoices';

    /**
     * Create a draft invoice.
     *
     * @param array<string, mixed>  $params           Invoice data
     * @param string|null           $idempotencyKey   Idempotency key for safe retries
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
     * List invoices with cursor-based pagination.
     *
     * @param array<string, mixed> $params  Query parameters (limit, starting_after, ending_before, status)
     * @return Collection
     */
    public static function all(array $params = []): Collection
    {
        $response = HttpClient::request('GET', self::BASE_PATH, null, $params);

        return new Collection($response, $params, self::BASE_PATH);
    }

    /**
     * Retrieve a single invoice.
     *
     * @param string $id Invoice ID (inv_xxx)
     * @return array<string, mixed>
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }

    /**
     * Update a draft invoice. Only draft invoices can be edited.
     *
     * @param string               $id      Invoice ID
     * @param array<string, mixed> $params  Fields to update
     * @return array<string, mixed>
     */
    public static function update(string $id, array $params): array
    {
        return HttpClient::request('PATCH', self::BASE_PATH . '/' . $id, $params);
    }

    /**
     * Soft-delete a draft invoice. Only draft invoices can be deleted.
     *
     * @param string $id Invoice ID
     * @return array<string, mixed>
     */
    public static function delete(string $id): array
    {
        return HttpClient::request('DELETE', self::BASE_PATH . '/' . $id);
    }

    /**
     * Finalize an invoice. Assigns a number and locks the invoice.
     * This action is irreversible.
     *
     * @param string $id Invoice ID
     * @return array<string, mixed>
     */
    public static function finalize(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/finalize');
    }

    /**
     * Send an invoice to the PA (Plateforme Agreee).
     * Returns 202 Accepted — the actual submission is asynchronous.
     *
     * @param string $id Invoice ID
     * @return array<string, mixed>
     */
    public static function send(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/send');
    }

    /**
     * Cancel an invoice. Note: not supported under French e-invoicing — use credit notes.
     *
     * @param string $id Invoice ID
     * @return array<string, mixed>
     */
    public static function cancel(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/cancel');
    }

    /**
     * Send a payment reminder for an invoice.
     *
     * @param string $id Invoice ID
     * @return array<string, mixed>
     */
    public static function remind(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/remind');
    }

    /**
     * Clone an invoice as a new draft.
     *
     * @param string $id Source invoice ID
     * @return array<string, mixed>
     */
    public static function clone(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/clone');
    }

    /**
     * Get the PDF for an invoice.
     *
     * Returns either a signed URL (if already generated) or a job object
     * (202 Accepted) for async generation.
     *
     * @param string               $id      Invoice ID
     * @param array<string, mixed> $params  Query parameters (e.g., template)
     * @return array<string, mixed>
     */
    public static function getPdf(string $id, array $params = []): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id . '/pdf', null, $params);
    }

    /**
     * Get the Factur-X PDF/A-3 for an invoice.
     *
     * Returns either a signed URL or a job object (202 Accepted).
     *
     * @param string               $id      Invoice ID
     * @param array<string, mixed> $params  Query parameters (e.g., template)
     * @return array<string, mixed>
     */
    public static function getFacturx(string $id, array $params = []): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id . '/facturx', null, $params);
    }

    /**
     * Get the XML (CII or UBL) for an invoice.
     *
     * @param string $id      Invoice ID
     * @param string $format  'cii' (default) or 'ubl'
     * @return string Raw XML content
     */
    public static function getXml(string $id, string $format = 'cii'): string
    {
        $result = HttpClient::request(
            'GET',
            self::BASE_PATH . '/' . $id . '/xml',
            null,
            ['format' => $format],
            [],
            true,
        );

        return is_string($result) ? $result : '';
    }

    /**
     * Get the lightweight status of an invoice.
     *
     * @param string $id Invoice ID
     * @return array<string, mixed>
     */
    public static function getStatus(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id . '/status');
    }

    /**
     * Verify the hash chain integrity of an invoice.
     *
     * @param string $id Invoice ID
     * @return array<string, mixed>
     */
    public static function verify(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id . '/verify');
    }

    /**
     * List lifecycle events for an invoice.
     *
     * @param string $id Invoice ID
     * @return array<string, mixed>
     */
    public static function listEvents(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id . '/events');
    }

    /**
     * Get the audit trail for an invoice.
     *
     * @param string               $id      Invoice ID
     * @param array<string, mixed> $params  Query parameters (limit, starting_after, action)
     * @return array<string, mixed>
     */
    public static function getAuditTrail(string $id, array $params = []): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id . '/audit-trail', null, $params);
    }

    /**
     * Generate an audit trail PDF (async job).
     *
     * @param string $id Invoice ID
     * @return array<string, mixed> Job object
     */
    public static function generateAuditTrailPdf(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/audit-trail/pdf');
    }

    /**
     * Create a Stripe payment link for an invoice (Pro+ only).
     *
     * @param string               $id      Invoice ID
     * @param array<string, mixed> $params  Parameters (success_url, cancel_url)
     * @return array<string, mixed>
     */
    public static function createPaymentLink(string $id, array $params = []): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/payment-link', $params);
    }

    /**
     * Create a secure payment token for an invoice (Pro+ only).
     *
     * @param string $id Invoice ID
     * @return array<string, mixed>
     */
    public static function createPaymentToken(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/payment-token');
    }
}
