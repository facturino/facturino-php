<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;

/**
 * Validate resource — /v1/validate
 *
 * Dry-run conformity check of an invoice draft against the same EN16931 /
 * CIUS-FR rules as Invoice::create, without persisting anything. Use it to
 * surface conformity warnings before creating an invoice. To check a
 * customer's SIRET/VAT, use Customer::lookup (SIRENE/VIES).
 */
class Validate
{
    private const BASE_PATH = '/v1/validate';

    /**
     * Validate an invoice payload without creating it.
     *
     * Accepts the same fields as Invoice::create (customerId, buyer, lines,
     * dates, payment...).
     *
     * @param array<string, mixed> $params  Invoice payload to validate.
     * @return array<string, mixed>  ['valid' => bool, 'warnings' => [...], 'schemaVersion' => string]
     */
    public static function run(array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH, $params);
    }
}
