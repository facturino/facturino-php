<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * WebhookEndpoint resource — /v1/webhook-endpoints
 */
class WebhookEndpoint
{
    private const BASE_PATH = '/v1/webhook-endpoints';

    /**
     * Create a webhook endpoint.
     *
     * @param array<string, mixed> $params          Endpoint data (url, events, description)
     * @param string|null          $idempotencyKey  Idempotency key
     * @return array<string, mixed>
     */
    public static function create(array $params, ?string $idempotencyKey = null): array
    {
        $headers = [];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return HttpClient::request('POST', self::BASE_PATH, $params, null, $headers);
    }

    /**
     * List webhook endpoints.
     *
     * @param array<string, mixed> $params  Query parameters
     * @return Collection
     */
    public static function all(array $params = []): Collection
    {
        $response = HttpClient::request('GET', self::BASE_PATH, null, $params);

        return new Collection($response, $params, self::BASE_PATH);
    }

    /**
     * Retrieve a webhook endpoint.
     *
     * @param string $id Webhook endpoint ID (we_xxx)
     * @return array<string, mixed>
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }

    /**
     * Update a webhook endpoint.
     *
     * @param string               $id      Endpoint ID
     * @param array<string, mixed> $params  Fields to update
     * @return array<string, mixed>
     */
    public static function update(string $id, array $params): array
    {
        return HttpClient::request('PATCH', self::BASE_PATH . '/' . $id, $params);
    }

    /**
     * Delete a webhook endpoint.
     *
     * @param string $id Endpoint ID
     * @return array<string, mixed>
     */
    public static function delete(string $id): array
    {
        return HttpClient::request('DELETE', self::BASE_PATH . '/' . $id);
    }

    /**
     * Send a test event to a webhook endpoint.
     *
     * @param string $id Endpoint ID
     * @return array<string, mixed>
     */
    public static function test(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/test');
    }
}
