<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;

/**
 * Account resource — /v1/account
 *
 * Account introspection. Returns the authenticated user, the active
 * company, the current plan and the scopes attached to the API key in
 * use. Equivalent to Stripe's whoami endpoint — call this on
 * integration startup so callers can display the connected company and
 * the environment (`fac_test_` vs `fac_live_`).
 */
class Account
{
    private const BASE_PATH = '/v1/account';

    /**
     * Retrieve the account context (user, company, plan, livemode,
     * scopes) associated with the API key used by this client.
     *
     * @return array<string, mixed>
     */
    public static function retrieve(): array
    {
        return HttpClient::request('GET', self::BASE_PATH);
    }

    /**
     * Request a full data export (RGPD article 20). The export is prepared
     * synchronously and returned as ['object' => 'export', 'id' => ...,
     * 'expires_at' => ...]. Pass the `id` to `downloadExport()` for a
     * short-lived signed URL.
     *
     * @return array<string, mixed>
     */
    public static function requestExport(): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/export');
    }

    /**
     * Return a short-lived (5 min) signed URL for a previously-prepared
     * RGPD export.
     *
     * @return array<string, mixed>
     */
    public static function downloadExport(string $exportId): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/exports/' . $exportId . '/download');
    }
}
