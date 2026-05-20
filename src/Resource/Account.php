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
     * Schedule the authenticated account for deletion in 30 days
     * (RGPD article 17). Returns 409 if a deletion is already
     * scheduled, and 400 while the account still has an active paid
     * subscription. Reversible during the grace period via
     * `cancelDeletion()`.
     *
     * @return array<string, mixed>
     */
    public static function scheduleDeletion(): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/schedule-deletion');
    }

    /**
     * Cancel a pending account deletion (within the 30-day grace window).
     *
     * @return array<string, mixed>
     */
    public static function cancelDeletion(): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/cancel-deletion');
    }

    /**
     * Request a full data export (RGPD article 20). Returns
     * immediately with an `exportId`; the file is prepared
     * asynchronously and the user receives an in-app notification
     * when ready. Pass the returned id to `downloadExport()` to fetch
     * a short-lived signed URL.
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

    /**
     * Update the per-channel email-notification preferences for the
     * authenticated user. Per-event channel preferences live under
     * `Notification::updatePreferences()` instead.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function updateNotifications(array $params): array
    {
        return HttpClient::request('PATCH', self::BASE_PATH . '/notifications', $params);
    }
}
