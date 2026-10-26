<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * E-reporting resource — /v1/ereporting/declarations
 *
 * French e-reporting declarations. Types — transactions: b2c, international,
 * intra_eu, domestic_b2b; payments: payment_b2c, payment_international,
 * payment_intra_eu, payment_domestic_b2b. Lines carry category, amount, vatRate,
 * vatAmount (integer centimes / centipercent) and, optionally, date, issueDate,
 * invoiceNumber, country, partnerVat, partnerName, vatCategoryCode, vatexCode,
 * count, documentType ("380" invoice, "381" credit note), originalInvoiceNumber
 * and originalInvoiceDate (the invoice a credit note corrects — both are
 * required to transmit a unit credit note, DGFiP G1.32).
 *
 * Responses may carry read-only obligationBasis (legal or
 * voluntary_early_adoption), obligationDueFrom (the seller's legal wave
 * opening date, YYYY-MM-DD) and acknowledgedBy (administration for the PPF,
 * or platform). Each may be null or absent on historical responses.
 * Acknowledgement identifies receipt, independently of acceptance.
 */
class Ereporting
{
    private const BASE_PATH = '/v1/ereporting/declarations';

    /**
     * List e-reporting declarations with cursor-based pagination.
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
     * Retrieve a single declaration.
     *
     * @param string $id Declaration ID (rep_xxx)
     * @return array<string, mixed>
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }

    /**
     * Create a new e-reporting declaration.
     *
     * @param array<string, mixed> $params          Declaration data
     * @param string|null          $idempotencyKey  Idempotency key
     * @return array<string, mixed>
     */
    public static function createDeclaration(array $params, ?string $idempotencyKey = null): array
    {
        $headers = [];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return HttpClient::request('POST', self::BASE_PATH, $params, null, $headers);
    }

    /**
     * Submit a declaration to the PA.
     *
     * @param string $id Declaration ID
     * @return array<string, mixed>
     */
    public static function submitDeclaration(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/submit');
    }
}
