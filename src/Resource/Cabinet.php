<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * Cabinet resource — /v1/cabinets
 *
 * Multi-company management for chartered-accountant cabinets (plans
 * `cabinet_50`, `cabinet_200`, `cabinet_500`). A cabinet groups managed
 * client companies under a single billing entity and exposes
 * cross-company dashboards, activity feeds and branding.
 *
 * Every endpoint requires a `cabinet_*` plan; integrations on `pro` or
 * below receive a `plan_limit_error`.
 */
class Cabinet
{
    private const BASE_PATH = '/v1/cabinets';

    /**
     * Paginated list of cabinets owned by the authenticated account.
     *
     * @param array<string, mixed> $params Query parameters
     * @return Collection
     */
    public static function all(array $params = []): Collection
    {
        $response = HttpClient::request('GET', self::BASE_PATH, null, $params);

        return new Collection($response, $params, self::BASE_PATH);
    }

    /**
     * Retrieve a cabinet by id.
     *
     * @return array<string, mixed>
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }

    /**
     * Create a new cabinet — only allowed under a `cabinet_*` plan.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function create(array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH, $params);
    }

    /**
     * Update the white-label branding (logo, colors, custom domain).
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function updateBranding(string $id, array $params): array
    {
        return HttpClient::request('PATCH', self::BASE_PATH . '/' . $id . '/branding', $params);
    }

    /**
     * Cross-company KPI dashboard (revenue, overdue, e-reporting status).
     *
     * @param array<string, mixed> $params Optional periodStart / periodEnd
     * @return array<string, mixed>
     */
    public static function dashboard(string $id, array $params = []): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id . '/dashboard', null, $params);
    }

    /**
     * Reverse-chronological activity feed across every managed company.
     *
     * @param array<string, mixed> $params
     * @return Collection
     */
    public static function activity(string $id, array $params = []): Collection
    {
        $path = self::BASE_PATH . '/' . $id . '/activity';
        $response = HttpClient::request('GET', $path, null, $params);

        return new Collection($response, $params, $path);
    }

    /**
     * Per-company breakdown of the active subscription invoice — useful
     * for cabinets that rebill subscription costs to their clients.
     *
     * @return array<string, mixed>
     */
    public static function billingSplit(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id . '/billing-split');
    }

    /**
     * List the companies managed under a cabinet.
     *
     * @param array<string, mixed> $params
     * @return Collection
     */
    public static function listCompanies(string $id, array $params = []): Collection
    {
        $path = self::BASE_PATH . '/' . $id . '/companies';
        $response = HttpClient::request('GET', $path, null, $params);

        return new Collection($response, $params, $path);
    }

    /**
     * Attach an existing company (by SIRET or by ID) under the cabinet.
     *
     * @param array<string, mixed> $params  companyId, siret, companyName
     * @return array<string, mixed>
     */
    public static function addCompany(string $id, array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/companies', $params);
    }

    /**
     * Invite a team member onto the cabinet (admin / accountant / viewer role).
     *
     * @param array<string, mixed> $params  email, role, displayName
     * @return array<string, mixed>
     */
    public static function inviteMember(string $id, array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/members', $params);
    }
}
