<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * Payment resource — sub-resource of Invoice.
 *
 * Payments are stored at /v1/invoices/:invoiceId/payments.
 * Amount is in integer centimes (10000 = 100.00 EUR).
 */
class Payment
{
    /**
     * Record a payment on an invoice.
     *
     * @param string               $invoiceId       Invoice ID (inv_xxx)
     * @param array<string, mixed> $params          Payment data (amount, method, reference, paidAt)
     * @param string|null          $idempotencyKey  Idempotency key
     * @return array<string, mixed>
     */
    public static function create(string $invoiceId, array $params, ?string $idempotencyKey = null): array
    {
        $headers = [];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return HttpClient::request(
            'POST',
            '/v1/invoices/' . $invoiceId . '/payments',
            $params,
            null,
            $headers,
        );
    }

    /**
     * List payments for an invoice.
     *
     * @param string               $invoiceId  Invoice ID
     * @param array<string, mixed> $params     Query parameters (limit, starting_after)
     * @return Collection
     */
    public static function all(string $invoiceId, array $params = []): Collection
    {
        $path = '/v1/invoices/' . $invoiceId . '/payments';
        $response = HttpClient::request('GET', $path, null, $params);

        return new Collection($response, $params, $path);
    }

    /**
     * Cancel a recorded payment.
     *
     * The payment is kept for the audit trail (status "cancelled") and the
     * invoice is re-settled from the reversal. Rejected once the payment has
     * been reported to the tax authority.
     *
     * @param string $invoiceId  Invoice ID (inv_xxx)
     * @param string $paymentId  Payment ID (pay_xxx)
     * @return array<string, mixed>
     */
    public static function cancel(string $invoiceId, string $paymentId): array
    {
        return HttpClient::request(
            'POST',
            '/v1/invoices/' . $invoiceId . '/payments/' . $paymentId . '/cancel',
        );
    }
}
