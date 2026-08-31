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
     * Create a draft invoice from an immutable tax decision.
     *
     * Every invoice references the decision that fixed its VAT and its
     * amounts; the invoice never restates a rate, so `lines`/`items` are
     * refused locally, before any HTTP call. A decision line carries
     * presentation only (`taxLineRef`, `unit`, optional `product`).
     *
     *   ['customerId' => 'cus_1', 'taxDecisionId' => 'taxdec_9c1f',
     *    'decisionLines' => [['taxLineRef' => 'abo-pro', 'unit' => 'month']],
     *    'buyer' => $buyer, 'dates' => $dates, 'payment' => $payment]
     *
     * `deposits` and `schedule` travel alongside the decision: both are
     * settled server-side against the DECIDED amount due.
     *
     * @param array<string, mixed>  $params           Invoice data
     * @param string|null           $idempotencyKey   Idempotency key for safe retries
     * @return array<string, mixed>
     */
    public static function create(array $params, ?string $idempotencyKey = null): array
    {
        foreach (['lines', 'items'] as $field) {
            // PRESENCE of the key is refused — an empty array included: it
            // states the intent to restate VAT on the invoice, which no
            // contract allows.
            if (array_key_exists($field, $params)) {
                throw new \InvalidArgumentException(sprintf(
                    "'%s' is not part of the invoice contract: an invoice never states its own VAT. Create a tax decision first, then reference it with 'taxDecisionId' and describe the document lines with 'decisionLines'.",
                    $field
                ));
            }
        }
        if (!isset($params['taxDecisionId']) || !is_string($params['taxDecisionId']) || $params['taxDecisionId'] === '') {
            throw new \InvalidArgumentException(
                "'taxDecisionId' is required: every invoice is created from an immutable tax decision (POST /v1/tax-decisions)."
            );
        }
        if (!isset($params['decisionLines']) || !is_array($params['decisionLines']) || $params['decisionLines'] === []) {
            throw new \InvalidArgumentException(
                "'decisionLines' is required and non-empty: each entry references a decision line by 'taxLineRef' and completes the document-only details (unit, product)."
            );
        }

        $headers = [];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return HttpClient::request('POST', self::BASE_PATH, $params, null, $headers);
    }

    /**
     * List invoices with cursor-based pagination.
     *
     * Pass `convertedFrom` (a `quo_` quote ID) to return only the
     * invoices issued from that quote.
     *
     * @param array<string, mixed> $params  Query parameters (limit, starting_after, status, convertedFrom)
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
     * Pass `expand` (comma-separated) to inline related resources:
     * `customer`, `items.product` and `credit_notes`. With
     * `expand=credit_notes` the response gains `expanded.credit_notes`
     * (array of credit notes) and `expanded.net_balance` (string).
     *
     * @param string               $id      Invoice ID (inv_xxx)
     * @param array<string, mixed> $params  Query parameters (e.g., expand)
     * @return array<string, mixed>
     */
    public static function retrieve(string $id, array $params = []): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id, null, $params);
    }

    /**
     * Update a draft invoice's non-fiscal fields.
     *
     * Only drafts can be updated, and only `dates`, `payment`, `notes`,
     * `purchaseOrderNumber` and `metadata`: the commercial operation and its
     * VAT belong to the decision, which is immutable. To change the operation,
     * take a new decision and create a new draft.
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
     * Bind a FINAL tax decision to a commercial draft that already exists —
     * typically the one `Quote::convert()` produced.
     *
     * This closes the quote cycle on ONE document:
     *
     * ```php
     * $converted = \Facturino\Quote::convert($quoteId);
     * $decision  = \Facturino\TaxDecision::create($input, $idempotencyKey);
     * \Facturino\Invoice::bindTaxDecision($converted['invoiceId'], [
     *     'taxDecisionId' => $decision['id'],
     *     'decisionLines' => [['taxLineRef' => 'l1', 'unit' => 'unit']],
     * ]);
     * \Facturino\Invoice::finalize($converted['invoiceId']);
     * ```
     *
     * The invoice stays a DRAFT: binding freezes the VAT, `finalize()` issues
     * it. Idempotent on the decision — replaying the same call returns the same
     * invoice.
     *
     * @param string               $id             Commercial draft ID (inv_xxx)
     * @param array<string, mixed> $params         taxDecisionId + decisionLines
     * @param string|null          $idempotencyKey Optional Idempotency-Key header
     * @return array<string, mixed>
     */
    public static function bindTaxDecision(string $id, array $params, ?string $idempotencyKey = null): array
    {
        if (!isset($params['taxDecisionId']) || !is_string($params['taxDecisionId']) || $params['taxDecisionId'] === '') {
            throw new \InvalidArgumentException(
                "'taxDecisionId' is required: a commercial draft is fiscalised by binding a FINAL tax decision to it."
            );
        }
        if (!isset($params['decisionLines']) || !is_array($params['decisionLines']) || $params['decisionLines'] === []) {
            throw new \InvalidArgumentException(
                "'decisionLines' is required and non-empty: each entry references a decision line by 'taxLineRef' and completes the document-only details (unit, product)."
            );
        }

        $headers = [];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/bind-tax-decision', $params, null, $headers);
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
     * Send a finalized invoice to its customer by email with the PDF
     * (and optionally the Factur-X XML) attached.
     *
     * Returns ``{"status": "sent", ...}`` once dispatched, or
     * ``{"status": "pending", "jobId": "job_..."}`` when the PDF is
     * still being rendered. In the latter case poll the returned job
     * and call ``email()`` again once the PDF is ready.
     *
     * @param string               $id     Invoice ID
     * @param array<string, mixed> $params Optional: recipientEmail, customMessage, includeXml, customSubject
     * @return array<string, mixed>
     */
    public static function email(string $id, array $params = []): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/email', $params);
    }

    /**
     * Cancel a draft invoice (status `draft` only). Finalized invoices
     * are immutable under French law (CGI art. 289); to refund or
     * correct one, issue a credit note via ``CreditNote::create()``.
     *
     * @param string $id Invoice ID
     * @return array<string, mixed>
     */
    public static function cancel(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/cancel');
    }

    /**
     * Send a payment reminder email for an overdue invoice.
     *
     * @param string               $id     Invoice ID
     * @param array<string, mixed> $params Optional: level (1, 2, 3), message
     * @return array<string, mixed>
     */
    public static function remind(string $id, array $params = []): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/remind', $params);
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

    /**
     * Generate a signed client-portal link for a finalized invoice.
     *
     * The URL points to a public, branded portal where the customer
     * can view the invoice, download the PDF and trigger the payment
     * flow. The embedded token grants read-only access to a single
     * invoice and expires after the configured lifetime. Returns
     * `invalid_status_transition` if the invoice is still in draft.
     *
     * @param string $id Invoice ID
     * @return array<string, mixed>
     */
    public static function createPortalLink(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/portal-link');
    }

    /**
     * Record a supplier invoice received outside the platform.
     *
     * @param array<string, mixed> $params  ['senderName' => string, 'senderSiret' => ?string,
     *                                       'amount' => int (cents), 'reference' => ?string]
     * @return array<string, mixed>  The recorded received invoice (rec_ id).
     */
    public static function createIncoming(array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/incoming', $params);
    }

    /**
     * List incoming invoices with cursor-based pagination.
     *
     * @param array<string, mixed> $params  Query parameters
     * @return Collection
     */
    public static function listIncoming(array $params = []): Collection
    {
        $response = HttpClient::request('GET', self::BASE_PATH . '/incoming', null, $params);

        return new Collection($response, $params, self::BASE_PATH . '/incoming');
    }
}
