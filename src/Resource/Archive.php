<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * Archive resource — /v1/archives
 *
 * Access archived invoice documents (PDF + Factur-X + XML).
 * Requires Archive addon.
 */
class Archive
{
    private const BASE_PATH = '/v1/archives';

    /**
     * List archived invoices with cursor-based pagination.
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
     * Retrieve an archived invoice by its invoice ID.
     *
     * @param string $invoiceId Invoice ID (inv_xxx)
     * @return array<string, mixed>
     */
    public static function retrieve(string $invoiceId): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $invoiceId);
    }
}
