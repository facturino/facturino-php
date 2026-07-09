<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * Billing resource — /v1/billing
 *
 * Read the Stripe-backed subscription and list / download the platform
 * invoices issued to this account. Subscription changes (plan, cycle,
 * checkout, pause, resume) are handled in the Facturino dashboard.
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
