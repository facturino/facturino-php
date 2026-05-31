<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;

/**
 * Health resource — /v1/health
 *
 * Lightweight, unauthenticated-friendly status probe for the Facturino
 * API. Use it for connectivity checks, readiness gates, and uptime
 * monitoring. No account data is exposed; the payload only reports the
 * service status and current API version.
 */
class Health
{
    private const BASE_PATH = '/v1/health';

    /**
     * Return the current service status snapshot.
     *
     * @return array<string, mixed>
     */
    public static function retrieve(): array
    {
        return HttpClient::request('GET', self::BASE_PATH);
    }
}
