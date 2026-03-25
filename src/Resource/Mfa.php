<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;

/**
 * MFA resource — /v1/auth/mfa
 *
 * TOTP-based multi-factor authentication (Pro plan).
 */
class Mfa
{
    private const BASE_PATH = '/v1/auth/mfa';

    /**
     * Initialize TOTP setup. Returns the secret and provisioning URI.
     *
     * @return array<string, mixed>
     */
    public static function setup(): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/setup');
    }

    /**
     * Verify a TOTP code and enable MFA.
     *
     * @param array<string, mixed> $params  Verification data (code: 6-digit string)
     * @return array<string, mixed>
     */
    public static function verify(array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/verify', $params);
    }

    /**
     * Disable MFA. Requires a valid TOTP code for confirmation.
     *
     * @param array<string, mixed> $params  Confirmation data (code: 6-digit string)
     * @return array<string, mixed>
     */
    public static function disable(array $params): array
    {
        return HttpClient::request('DELETE', self::BASE_PATH, $params);
    }

    /**
     * Generate backup codes. MFA must already be enabled.
     *
     * @return array<string, mixed>
     */
    public static function backupCodes(): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/backup-codes');
    }
}
