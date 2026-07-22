<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * CreditNote resource — /v1/credit-notes
 */
class CreditNote
{
    private const BASE_PATH = '/v1/credit-notes';

    /**
     * Create a draft credit note.
     *
     * @param array<string, mixed> $params          Credit note data
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
     * List credit notes with cursor-based pagination.
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
     * Retrieve a single credit note.
     *
     * @param string $id Credit note ID (crn_xxx)
     * @return array<string, mixed>
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }

    /**
     * Update a draft credit note. Only draft credit notes can be edited.
     *
     * @param string               $id      Credit note ID
     * @param array<string, mixed> $params  Fields to update
     * @return array<string, mixed>
     */
    public static function update(string $id, array $params): array
    {
        return HttpClient::request('PATCH', self::BASE_PATH . '/' . $id, $params);
    }

    /**
     * Soft-delete a draft credit note.
     *
     * @param string $id Credit note ID
     * @return array<string, mixed>
     */
    public static function delete(string $id): array
    {
        return HttpClient::request('DELETE', self::BASE_PATH . '/' . $id);
    }

    /**
     * Finalize a credit note. Assigns a number and locks the document.
     *
     * @param string $id Credit note ID
     * @return array<string, mixed>
     */
    public static function finalize(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/finalize');
    }

    /**
     * Send a credit note to the PA.
     *
     * @param string $id Credit note ID
     * @return array<string, mixed>
     */
    public static function send(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/send');
    }

    /**
     * Send the credit note to its customer by email with the PDF
     * (and optionally the Factur-X XML).
     *
     * @param string $id Credit note ID
     * @param array<string, mixed> $params Optional: recipientEmail, customMessage, includeXml, customSubject
     * @return array<string, mixed>
     */
    public static function email(string $id, array $params = []): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/email', $params);
    }

    /**
     * Record the disbursement of a finalized credit note back to the customer.
     * Writes a negative `refund` payment on the linked invoice. Returns
     * { id, object: 'refund', creditNoteId, invoiceId, amount }.
     *
     * @param string $id Credit note ID
     * @param array<string, mixed> $params Optional: amount (integer centimes), method, refundedAt
     * @param string|null $idempotencyKey Idempotency key
     * @return array<string, mixed>
     */
    public static function refund(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        $headers = [];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/refund', $params, null, $headers);
    }

    /**
     * Get the PDF for a credit note.
     *
     * @param string $id Credit note ID
     * @return array<string, mixed>
     */
    public static function getPdf(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id . '/pdf');
    }

    /**
     * Get the Factur-X PDF/A-3 for a credit note.
     *
     * @param string $id Credit note ID
     * @return array<string, mixed>
     */
    public static function getFacturx(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id . '/facturx');
    }

    /**
     * Get the raw CII or UBL XML for a credit note.
     *
     * @param string $id Credit note ID
     * @param string $format "cii" (default) or "ubl"
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
}
