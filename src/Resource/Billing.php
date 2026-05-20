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
     * Update the active subscription — change plan, cycle, or
     * schedule a cancel-at-period-end.
     *
     * @param array<string, mixed> $params  plan, cycle, cancelAtPeriodEnd
     * @return array<string, mixed>
     */
    public static function updateSubscription(array $params): array
    {
        return HttpClient::request('PATCH', self::BASE_PATH . '/subscription', $params);
    }

    /**
     * Create a Stripe Checkout session for the first paid subscription.
     *
     * @param array<string, mixed> $params  plan, cycle, successUrl, cancelUrl
     * @return array<string, mixed>
     */
    public static function checkout(array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/checkout', $params);
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
     * @return array<string, mixed>
     */
    public static function pause(): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/pause');
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
}
