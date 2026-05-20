<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;

/**
 * Setting resource — /v1/companies/{companyId}/settings
 *
 * Per-company configuration that does not belong to a single resource:
 * accounting accounts (FEC mapping) and the automatic reminder
 * schedule. Every method takes the target `$companyId` so a single API
 * key can administer settings on each of the companies it is scoped to.
 */
class Setting
{
    /**
     * Accounting configuration (FEC accounts, journal codes, VAT regime).
     *
     * @return array<string, mixed>
     */
    public static function retrieveAccounting(string $companyId): array
    {
        return HttpClient::request('GET', '/v1/companies/' . $companyId . '/settings/accounting');
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function updateAccounting(string $companyId, array $params): array
    {
        return HttpClient::request(
            'PATCH',
            '/v1/companies/' . $companyId . '/settings/accounting',
            $params,
        );
    }

    /**
     * Automatic dunning reminder schedule (J+7 / J+15 / J+30 by default).
     *
     * @return array<string, mixed>
     */
    public static function retrieveReminders(string $companyId): array
    {
        return HttpClient::request('GET', '/v1/companies/' . $companyId . '/settings/reminders');
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function updateReminders(string $companyId, array $params): array
    {
        return HttpClient::request(
            'PATCH',
            '/v1/companies/' . $companyId . '/settings/reminders',
            $params,
        );
    }
}
