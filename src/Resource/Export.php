<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;

/**
 * Export resource — /v1/exports
 *
 * FEC exports (art. A.47 A-1 LPF) and RGPD data exports.
 */
class Export
{
    private const BASE_PATH = '/v1/exports';

    /**
     * Generate a FEC export (async job, Pro plan only).
     *
     * @param array<string, mixed> $params  Export parameters (period_start, period_end, send_to_accountant, accountant_email)
     * @return array<string, mixed> Job object
     */
    public static function generateFec(array $params = []): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/fec', $params);
    }

    /**
     * Generate a full RGPD data export (async job).
     *
     * @return array<string, mixed> Job object
     */
    public static function exportRgpd(): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/full');
    }
}
