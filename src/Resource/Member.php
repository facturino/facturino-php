<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * Member resource — /v1/companies/{companyId}/members
 *
 * Company team members. Only owners and admins can manage members.
 */
class Member
{
    /**
     * Build the base path for a company's members.
     *
     * @param string $companyId Company ID
     * @return string
     */
    private static function basePath(string $companyId): string
    {
        return '/v1/companies/' . $companyId . '/members';
    }

    /**
     * List members of a company.
     *
     * @param string               $companyId  Company ID
     * @param array<string, mixed> $params     Query parameters
     * @return Collection
     */
    public static function all(string $companyId, array $params = []): Collection
    {
        $path = self::basePath($companyId);
        $response = HttpClient::request('GET', $path, null, $params);

        return new Collection($response, $params, $path);
    }

    /**
     * Retrieve a member.
     *
     * @param string $companyId  Company ID
     * @param string $id         Member ID (mem_xxx)
     * @return array<string, mixed>
     */
    public static function retrieve(string $companyId, string $id): array
    {
        return HttpClient::request('GET', self::basePath($companyId) . '/' . $id);
    }

    /**
     * Invite a new member to the company.
     *
     * @param string               $companyId       Company ID
     * @param array<string, mixed> $params          Member data (email, role, displayName)
     * @param string|null          $idempotencyKey  Idempotency key
     * @return array<string, mixed>
     */
    public static function invite(string $companyId, array $params, ?string $idempotencyKey = null): array
    {
        $headers = [];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return HttpClient::request('POST', self::basePath($companyId), $params, null, $headers);
    }

    /**
     * Update a member's role.
     *
     * @param string               $companyId  Company ID
     * @param string               $id         Member ID
     * @param array<string, mixed> $params     Update data (role)
     * @return array<string, mixed>
     */
    public static function updateRole(string $companyId, string $id, array $params): array
    {
        return HttpClient::request('PATCH', self::basePath($companyId) . '/' . $id, $params);
    }

    /**
     * Revoke (remove) a member from the company.
     *
     * @param string $companyId  Company ID
     * @param string $id         Member ID
     * @return array<string, mixed>
     */
    public static function revoke(string $companyId, string $id): array
    {
        return HttpClient::request('DELETE', self::basePath($companyId) . '/' . $id);
    }
}
