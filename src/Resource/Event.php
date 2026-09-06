<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * Event resource — /v1/events
 *
 * Webhook events dispatched by Facturino.
 */
class Event
{
    private const BASE_PATH = '/v1/events';

    /**
     * List events with cursor-based pagination.
     *
     * @param array<string, mixed> $params  Query parameters (limit, starting_after, type, delivered)
     * @return Collection
     */
    public static function all(array $params = []): Collection
    {
        $response = HttpClient::request('GET', self::BASE_PATH, null, $params);

        return new Collection($response, $params, self::BASE_PATH);
    }

    /**
     * Retrieve a single event.
     *
     * @param string $id Event ID (evt_xxx)
     * @return array<string, mixed>
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }

    /**
     * Retry delivery of a failed event.
     *
     * @param string $id Event ID
     * @return array<string, mixed>
     */
    /**
     * Retry the delivery. With $endpointId, replay the event to that endpoint
     * even if it was already delivered there (keep the receiver idempotent).
     */
    public static function retry(string $id, ?string $endpointId = null): array
    {
        $body = $endpointId !== null ? ['endpointId' => $endpointId] : null;

        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/retry', $body);
    }
}
