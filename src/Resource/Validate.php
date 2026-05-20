<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;

/**
 * Validate resource — /v1/validate
 *
 * Synchronous structural validation of business identifiers (SIRET,
 * VAT number, IBAN, BIC) and document payloads (invoice line items
 * against EN16931 / CIUS-FR Schematron rules). Useful client-side to
 * surface errors before hitting the write endpoints — Validate calls
 * never mutate any resource.
 */
class Validate
{
    private const BASE_PATH = '/v1/validate';

    /**
     * Run a single validation request.
     *
     * The shape depends on `kind`:
     *  - `siret` / `vat` / `iban` / `bic` accept a `value` string.
     *  - `invoice` accepts a full invoice payload and runs Schematron
     *    against EN16931 + CIUS-FR.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function run(array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH, $params);
    }
}
