<?php

declare(strict_types=1);

namespace Facturino\Util;

use Facturino\HttpClient;

/**
 * Paginated list with auto-pagination support via Iterator.
 *
 * Uses cursor-based pagination (starting_after).
 * Automatically fetches subsequent pages when iterated.
 *
 * Usage:
 *   $collection = Invoice::all(['limit' => 10]);
 *   foreach ($collection as $invoice) {
 *       echo $invoice['id'];
 *   }
 */
final class Collection implements \IteratorAggregate, \Countable
{
    /** @var string API path for subsequent page fetches */
    private string $requestPath;

    /** @var list<array<string, mixed>> Current page data */
    private array $data;

    /** @var bool Whether more pages exist */
    private bool $hasMore;

    /** @var string|null Cursor for the next page */
    private ?string $nextCursor;

    /** @var array<string, mixed> Original query parameters */
    private array $params;

    /**
     * @param array<string, mixed> $response     Paginated API response
     * @param array<string, mixed> $params       Original query parameters
     * @param string               $requestPath  Original request path (e.g. /v1/invoices)
     */
    public function __construct(array $response, array $params = [], string $requestPath = '')
    {
        $this->requestPath = $requestPath ?: ($response['url'] ?? '');
        $this->data = $response['data'] ?? [];
        $this->hasMore = $response['has_more'] ?? false;
        $this->nextCursor = $response['next_cursor'] ?? null;
        $this->params = $params;
    }

    /**
     * Get the current page of data.
     *
     * @return list<array<string, mixed>>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Whether more pages are available.
     */
    public function hasMore(): bool
    {
        return $this->hasMore;
    }

    /**
     * Get the next page cursor.
     */
    public function getNextCursor(): ?string
    {
        return $this->nextCursor;
    }

    /**
     * Auto-paginating iterator that fetches subsequent pages on demand.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function getIterator(): \Generator
    {
        yield from $this->data;

        $hasMore = $this->hasMore;
        $nextCursor = $this->nextCursor;
        $path = $this->requestPath;
        $params = $this->params;

        while ($hasMore && $nextCursor !== null) {
            $queryParams = array_merge($params, ['starting_after' => $nextCursor]);
            $response = HttpClient::request('GET', $path, null, $queryParams);

            $pageData = $response['data'] ?? [];
            yield from $pageData;

            $hasMore = $response['has_more'] ?? false;
            $nextCursor = $response['next_cursor'] ?? null;

            // Safety: break if we got an empty page
            if (empty($pageData)) {
                break;
            }
        }
    }

    /**
     * Count of items on the current page (not total).
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Convert the first page to a plain array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'object' => 'list',
            'url' => $this->requestPath,
            'data' => $this->data,
            'has_more' => $this->hasMore,
            'next_cursor' => $this->nextCursor,
        ];
    }
}
