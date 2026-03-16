<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * Member resource — /v1/members
 *
 * Company team members. Only owners and admins can manage members.
 */
class Member
{
    private const BASE_PATH = '/v1/members';

    /**
     * List members of the current company.
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
     * Retrieve a member.
     *
     * @param string $id Member ID (mem_xxx)
     * @return array<string, mixed>
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }

    /**
     * Invite a new member to the company.
     *
     * @param array<string, mixed> $params          Member data (email, role, displayName)
     * @param string|null          $idempotencyKey  Idempotency key
     * @return array<string, mixed>
     */
    public static function invite(array $params, ?string $idempotencyKey = null): array
    {
        $headers = [];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return HttpClient::request('POST', self::BASE_PATH, $params, null, $headers);
    }

    /**
     * Update a member's role.
     *
     * @param string               $id      Member ID
     * @param array<string, mixed> $params  Update data (role)
     * @return array<string, mixed>
     */
    public static function updateRole(string $id, array $params): array
    {
        return HttpClient::request('PATCH', self::BASE_PATH . '/' . $id, $params);
    }

    /**
     * Revoke (remove) a member from the company.
     *
     * @param string $id Member ID
     * @return array<string, mixed>
     */
    public static function revoke(string $id): array
    {
        return HttpClient::request('DELETE', self::BASE_PATH . '/' . $id);
    }
}
