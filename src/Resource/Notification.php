<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * Notification resource — /v1/notifications & /v1/notification-preferences
 *
 * In-app notification feed for the authenticated user (paginated list,
 * mark single / all read) and per-event preferences (email / push /
 * in-app per notification type). The feed lives under
 * `/v1/notifications` and mirrors the dashboard "bell" icon;
 * per-event preferences are stored at the user level under
 * `/v1/notification-preferences`.
 */
class Notification
{
    private const BASE_PATH = '/v1/notifications';
    private const PREFERENCES_PATH = '/v1/notification-preferences';

    /**
     * Paginated list of notifications for the authenticated user.
     *
     * @param array<string, mixed> $params  limit, starting_after, ending_before, unread
     * @return Collection
     */
    public static function all(array $params = []): Collection
    {
        $response = HttpClient::request('GET', self::BASE_PATH, null, $params);

        return new Collection($response, $params, self::BASE_PATH);
    }

    /**
     * Mark a single notification as read.
     *
     * @return array<string, mixed>
     */
    public static function markRead(string $id): array
    {
        return HttpClient::request('PATCH', self::BASE_PATH . '/' . $id, ['read' => true]);
    }

    /**
     * Mark every unread notification as read.
     *
     * @return array<string, mixed>
     */
    public static function markAllRead(): array
    {
        return HttpClient::request('PATCH', self::BASE_PATH . '/mark-all-read');
    }

    /**
     * Retrieve the per-event notification preferences for the user.
     *
     * @return array<string, mixed>
     */
    public static function retrievePreferences(): array
    {
        return HttpClient::request('GET', self::PREFERENCES_PATH);
    }

    /**
     * Update per-event notification preferences. The body merges with
     * the existing preferences map.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function updatePreferences(array $params): array
    {
        return HttpClient::request('PATCH', self::PREFERENCES_PATH, $params);
    }
}
