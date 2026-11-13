<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;

/**
 * Reporting resource — /v1/reporting
 *
 * VAT and revenue reports (Essential, Pro, Cabinet plans).
 */
class Reporting
{
    private const BASE_PATH = '/v1/reporting';

    /**
     * Get a VAT report for a given period.
     *
     * The report is returned as an array with camelCase keys, as the API sends
     * them: `object`, `period` (`start`, `end`), `vatBreakdown` (list of `rate`
     * as a decimal string, `taxableAmount`, `vatAmount`), `totalHT`, `totalVAT`,
     * `totalTTC` and `invoiceCount`. Amounts are integer centimes.
     *
     * @param array<string, mixed> $params  Query parameters (period_start, period_end)
     * @return array<string, mixed>
     */
    public static function vat(array $params = []): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/vat', null, $params);
    }

    /**
     * Get a revenue report for a given period.
     *
     * The report is returned as an array with camelCase keys, as the API sends
     * them: `object`, `period` (`start`, `end`), `revenue` (`invoiced`,
     * `creditNotes`, `net`), `payments` (`received`, `outstanding`),
     * `invoiceCount` and `creditNoteCount`. With `group_by`, `breakdown` lists
     * one item per month or quarter (`period`, `revenue`, `payments`,
     * `invoiceCount`, `creditNoteCount`). Amounts are integer centimes.
     *
     * @param array<string, mixed> $params  Query parameters (period_start, period_end, group_by)
     * @return array<string, mixed>
     */
    public static function revenue(array $params = []): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/revenue', null, $params);
    }
}
