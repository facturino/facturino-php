<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * ApiKey resource — /v1/api-keys
 *
 * Keys use prefix fac_test_ or fac_live_.
 * The full key is only returned on creation and roll — it is never stored.
 */
class ApiKey
{
    private const BASE_PATH = '/v1/api-keys';

    /**
     * Create a new API key. The full key is only visible in the creation response.
     *
     * @param array<string, mixed> $params          Key data (name, permissions)
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
     * List API keys (without the full key value).
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
     * Retrieve an API key (without the full key value).
     *
     * @param string $id Key ID (key_xxx)
     * @return array<string, mixed>
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }

    /**
     * Revoke an API key. This is immediate and irreversible.
     *
     * @param string $id Key ID
     * @return array<string, mixed>
     */
    public static function revoke(string $id): array
    {
        return HttpClient::request('DELETE', self::BASE_PATH . '/' . $id);
    }
}
