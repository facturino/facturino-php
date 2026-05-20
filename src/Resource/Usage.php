<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;

/**
 * Usage resource — /v1/usage
 *
 * Current-period consumption metrics for the authenticated account
 * (invoices issued, storage used, PA submissions, API calls). Useful
 * for in-app dashboards and proactive plan-upgrade nudges before a
 * quota hit triggers a 402 from the API.
 */
class Usage
{
    private const BASE_PATH = '/v1/usage';

    /**
     * Return the current usage snapshot — plan limits and consumption
     * so far for each metered dimension. No historical data is
     * exposed here; use `/v1/reporting/revenue` for trend lines.
     *
     * @return array<string, mixed>
     */
    public static function retrieve(): array
    {
        return HttpClient::request('GET', self::BASE_PATH);
    }
}
