<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;

/**
 * Account resource — /v1/account
 *
 * Account introspection. Returns the authenticated user, the active
 * company, the current plan and the scopes attached to the API key in
 * use. Equivalent to Stripe's whoami endpoint — call this on
 * integration startup so callers can display the connected company and
 * the environment (`fac_test_` vs `fac_live_`).
 */
class Account
{
    private const BASE_PATH = '/v1/account';

    /**
     * Retrieve the account context (user, company, plan, livemode,
     * scopes) associated with the API key used by this client.
     *
     * @return array<string, mixed>
     */
    public static function retrieve(): array
    {
        return HttpClient::request('GET', self::BASE_PATH);
    }
}
