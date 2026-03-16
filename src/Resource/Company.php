<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;

/**
 * Company resource — /v1/companies
 */
class Company
{
    private const BASE_PATH = '/v1/companies';

    /**
     * Retrieve a company.
     *
     * @param string $id Company ID
     * @return array<string, mixed>
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }

    /**
     * Update a company.
     *
     * @param string               $id      Company ID
     * @param array<string, mixed> $params  Fields to update
     * @return array<string, mixed>
     */
    public static function update(string $id, array $params): array
    {
        return HttpClient::request('PATCH', self::BASE_PATH . '/' . $id, $params);
    }

    /**
     * Upload CGV (General Terms and Conditions) PDF.
     *
     * @param string $id       Company ID
     * @param string $content  Base64-encoded PDF content
     * @return array<string, mixed>
     */
    public static function uploadCgv(string $id, string $content): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/cgv', [
            'content' => $content,
        ]);
    }
}
