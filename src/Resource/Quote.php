<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * Quote resource — /v1/quotes
 *
 * Input lines accept optional `kind` (`goods` or `service`) and open-string
 * `supplyCategory`: goods, services, electronically_supplied_services, deposit
 * or ancillary_costs today. Responses expose the declaration on `items[]`.
 * A quote's indicative VAT never decides its supply category. Conversion copies
 * supplyCategory unchanged into the commercial draft; an omission stays
 * undeclared. Only kind: goods establishes goods without a supplyCategory.
 */
class Quote
{
    private const BASE_PATH = '/v1/quotes';

    /**
     * Create a draft quote.
     *
     * @param array{customerId: string, lines: list<array{description: string, quantity: string, unitPrice: int, vatRate: int, vatCode: string, unit: string, kind?: 'goods'|'service', supplyCategory?: string, ...}>, ...} $params Quote data
     * @param string|null          $idempotencyKey  Idempotency key
     * @return array{items?: list<array{kind?: 'goods'|'service', supplyCategory?: string, ...}>, ...}
     */
    public static function create(array $params, ?string $idempotencyKey = null): array
    {
        $headers = [];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return HttpClient::request('POST', self::BASE_PATH, $params, null, $headers);
    }

    /**
     * List quotes with cursor-based pagination.
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
     * Retrieve a single quote.
     *
     * @param string $id Quote ID (quo_xxx)
     * @return array{items?: list<array{kind?: 'goods'|'service', supplyCategory?: string, ...}>, ...}
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }

    /**
     * Update a draft quote. Only draft quotes can be edited.
     *
     * @param string               $id      Quote ID
     * @param array{lines?: list<array{description: string, quantity: string, unitPrice: int, vatRate: int, vatCode: string, unit: string, kind?: 'goods'|'service', supplyCategory?: string, ...}>, ...} $params Fields to update
     * @return array{items?: list<array{kind?: 'goods'|'service', supplyCategory?: string, ...}>, ...}
     */
    public static function update(string $id, array $params): array
    {
        return HttpClient::request('PATCH', self::BASE_PATH . '/' . $id, $params);
    }

    /**
     * Soft-delete a draft quote.
     *
     * @param string $id Quote ID
     * @return array<string, mixed>
     */
    public static function delete(string $id): array
    {
        return HttpClient::request('DELETE', self::BASE_PATH . '/' . $id);
    }

    /**
     * Send a quote. Assigns a number on first send.
     *
     * @param string $id Quote ID
     * @return array<string, mixed>
     */
    public static function send(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/send');
    }

    /**
     * Send the quote to its customer by email with the PDF attached.
     *
     * @param string $id Quote ID
     * @param array<string, mixed> $params Optional: recipientEmail, customMessage, customSubject
     * @return array<string, mixed>
     */
    public static function email(string $id, array $params = []): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/email', $params);
    }

    /**
     * Accept a quote.
     *
     * @param string $id Quote ID
     * @return array<string, mixed>
     */
    public static function accept(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/accept');
    }

    /**
     * Refuse a quote.
     *
     * @param string $id Quote ID
     * @return array<string, mixed>
     */
    public static function refuse(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/refuse');
    }

    /**
     * Convert an accepted quote to a draft invoice.
     *
     * The server copies each declared supplyCategory unchanged into the draft.
     * An absent category stays undeclared; no service category is invented.
     *
     * @param string $id Quote ID
     * @return array<string, mixed> The new draft invoice
     */
    public static function convert(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/convert');
    }

    /**
     * Clone a quote as a new draft.
     *
     * @param string $id Source quote ID
     * @return array<string, mixed> The new draft quote
     */
    public static function clone(string $id): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $id . '/clone');
    }

    /**
     * Get the PDF for a quote.
     *
     * @param string $id Quote ID
     * @return array<string, mixed>
     */
    public static function getPdf(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id . '/pdf');
    }

    /**
     * Get the signature proof for an accepted quote.
     *
     * @param string $id Quote ID
     * @return array<string, mixed>
     */
    public static function getSignatureProof(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id . '/signature-proof');
    }
}
