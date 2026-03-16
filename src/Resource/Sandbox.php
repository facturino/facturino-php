<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;

/**
 * Sandbox resource — /v1/sandbox
 *
 * Test-mode-only utilities for resetting data and simulating status changes.
 * Requires a fac_test_ API key.
 */
class Sandbox
{
    private const BASE_PATH = '/v1/sandbox';

    /**
     * Reset all test data (soft-delete) and reload fixtures.
     *
     * @return array<string, mixed>
     */
    public static function resetData(): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/reset');
    }

    /**
     * Simulate a PA status change on an invoice.
     *
     * @param string $invoiceId  Invoice ID
     * @param string $status     Target status (deposited, transmitted, approved, etc.)
     * @return array<string, mixed>
     */
    public static function simulateStatus(string $invoiceId, string $status): array
    {
        return HttpClient::request(
            'POST',
            self::BASE_PATH . '/simulate-status/' . $invoiceId,
            ['status' => $status],
        );
    }

    /**
     * Reset all test data and reload default fixtures.
     *
     * Equivalent to resetData() — provided for readability when the intent
     * is to populate a clean sandbox with sample data.
     *
     * @return array<string, mixed>
     */
    public static function createFixtures(): array
    {
        return self::resetData();
    }
}
