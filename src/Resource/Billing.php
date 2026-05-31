<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * Billing resource — /v1/billing
 *
 * Manage the Stripe-backed subscription, list and download platform
 * invoices, and open Customer-Portal / Checkout sessions.
 */
class Billing
{
    private const BASE_PATH = '/v1/billing';

    /**
     * Retrieve the current subscription (plan, cycle, status,
     * period bounds).
     *
     * @return array<string, mixed>
     */
    public static function retrieveSubscription(): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/subscription');
    }

    /**
     * Update the active subscription — change plan and/or billing cycle.
     *
     * Accepts the ergonomic `cycle` ('monthly'|'annual') and `plan` aliases,
     * but the wire payload only ever contains `planId` (required) and the
     * optional boolean `annual`. Cancelling at period end is handled through
     * the Stripe Customer Portal, not this endpoint.
     *
     * @param array<string, mixed> $params  planId (or plan, required), annual (or cycle, optional)
     * @return array<string, mixed>
     */
    public static function updateSubscription(array $params): array
    {
        return HttpClient::request(
            'PATCH',
            self::BASE_PATH . '/subscription',
            self::normalizeSubscriptionParams($params),
        );
    }

    /**
     * Create a Stripe Checkout session for the first paid subscription.
     *
     * @param array<string, mixed> $params  planId (or plan, required), successUrl (required), cancelUrl (required)
     * @return array<string, mixed>
     */
    public static function checkout(array $params): array
    {
        return HttpClient::request(
            'POST',
            self::BASE_PATH . '/checkout',
            self::normalizeCheckoutParams($params),
        );
    }

    /**
     * Create a Stripe Customer Portal session for self-service changes.
     *
     * @param array<string, mixed> $params  returnUrl
     * @return array<string, mixed>
     */
    public static function portal(array $params = []): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/portal', $params);
    }

    /**
     * Pause the active subscription (Pro+ plans).
     *
     * @param array<string, mixed> $params  months (required, integer 1..3)
     * @return array<string, mixed>
     */
    public static function pause(array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/pause', $params);
    }

    /**
     * Resume a paused subscription.
     *
     * @return array<string, mixed>
     */
    public static function resume(): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/resume');
    }

    /**
     * Paginated list of platform invoices issued to this account.
     *
     * @param array<string, mixed> $params  Query parameters
     * @return Collection
     */
    public static function listInvoices(array $params = []): Collection
    {
        $response = HttpClient::request('GET', self::BASE_PATH . '/invoices', null, $params);

        return new Collection($response, $params, self::BASE_PATH . '/invoices');
    }

    /**
     * Return a short-lived signed URL to download a platform invoice PDF.
     *
     * @param string $invoiceId Platform invoice id
     * @return array<string, mixed>
     */
    public static function getInvoicePdf(string $invoiceId): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/invoices/' . $invoiceId . '/pdf');
    }

    /**
     * Normalize Checkout params to the wire shape accepted by
     * POST /v1/billing/checkout: { planId, successUrl, cancelUrl }.
     *
     * The endpoint is strict, so the `cycle`, `annual` and
     * `cancelAtPeriodEnd` aliases are dropped and `plan` is renamed to
     * `planId`.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function normalizeCheckoutParams(array $params): array
    {
        if (array_key_exists('plan', $params) && !array_key_exists('planId', $params)) {
            $params['planId'] = $params['plan'];
        }
        unset($params['plan'], $params['cycle'], $params['annual'], $params['cancelAtPeriodEnd']);

        return $params;
    }

    /**
     * Normalize subscription-update params to the wire shape accepted by
     * PATCH /v1/billing/subscription: { planId, annual? }.
     *
     * The endpoint is strict: `plan` is renamed to `planId`, the ergonomic
     * `cycle` ('monthly'|'annual') is converted to the boolean `annual`, and
     * `cancelAtPeriodEnd` is dropped (period-end cancellation is handled via
     * the Stripe Customer Portal).
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function normalizeSubscriptionParams(array $params): array
    {
        if (array_key_exists('plan', $params) && !array_key_exists('planId', $params)) {
            $params['planId'] = $params['plan'];
        }
        if (array_key_exists('cycle', $params) && !array_key_exists('annual', $params)) {
            $params['annual'] = ($params['cycle'] === 'annual');
        }
        unset($params['plan'], $params['cycle'], $params['cancelAtPeriodEnd']);

        return $params;
    }
}
