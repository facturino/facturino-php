<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * Company resource — /v1/companies
 */
class Company
{
    private const BASE_PATH = '/v1/companies';

    /**
     * List companies.
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
     * Create a new company under the authenticated user.
     *
     * Subject to the per-plan company quota (free / essential: 1,
     * pro: 3, cabinet_*: 50+); exceeding the quota returns a 402
     * `plan_limit_error`.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function create(array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH, $params);
    }

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
     * Update the invoicing settings (numbering format, default payment
     * terms, default VAT rate, footer mentions…) and the VAT regime.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function updateInvoicingSettings(string $id, array $params): array
    {
        return HttpClient::request(
            'PATCH',
            self::BASE_PATH . '/' . $id . '/invoicing-settings',
            $params,
        );
    }

    /**
     * Mark an onboarding milestone as reached (e.g.
     * `first_invoice_sent`, `pa_connected`, `bank_added`).
     *
     * @return array<string, mixed>
     */
    public static function addMilestone(string $id, string $milestone): array
    {
        return HttpClient::request(
            'POST',
            self::BASE_PATH . '/' . $id . '/milestones',
            ['milestone' => $milestone],
        );
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

    /**
     * Get CGV download URL (signed, 24h expiry).
     *
     * @param string $id Company ID
     * @return array<string, mixed>
     */
    public static function getCgv(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id . '/cgv');
    }

    /**
     * Remove CGV PDF.
     *
     * @param string $id Company ID
     * @return array<string, mixed>
     */
    public static function deleteCgv(string $id): array
    {
        return HttpClient::request('DELETE', self::BASE_PATH . '/' . $id . '/cgv');
    }

    /**
     * Connect a PA — the client provides their own PA account credentials (BYOPA).
     *
     * @param string               $id      Company ID
     * @param array<string, mixed> $params  provider, clientId, clientSecret, apiKey, customBaseUrl
     * @return array<string, mixed>
     */
    public static function connectPA(string $id, array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/pa-connection', $params);
    }

    /**
     * Disconnect the PA from a company.
     *
     * @param string $id Company ID
     * @return array<string, mixed>
     */
    public static function disconnectPA(string $id): array
    {
        return HttpClient::request('DELETE', self::BASE_PATH . '/' . $id . '/pa-connection');
    }

    /**
     * Test the PA connection (health check + credential validation).
     *
     * @param string $id Company ID
     * @return array<string, mixed>
     */
    public static function testPAConnection(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/pa-connection/test');
    }

    /**
     * Connect a Stripe account for online payments (Pro+ only).
     *
     * @param array<string, mixed> $params  Stripe connection parameters
     * @return array<string, mixed>
     */
    public static function connectStripe(array $params): array
    {
        return HttpClient::request('POST', '/v1/companies/stripe-connect', $params);
    }

    /**
     * Get the Stripe dashboard URL for the connected account.
     *
     * @return array<string, mixed>
     */
    public static function getStripeDashboard(): array
    {
        return HttpClient::request('GET', '/v1/companies/stripe-dashboard');
    }

    /**
     * Disconnect the Stripe account.
     *
     * @return array<string, mixed>
     */
    public static function disconnectStripe(): array
    {
        return HttpClient::request('DELETE', '/v1/companies/stripe-connect');
    }
}
