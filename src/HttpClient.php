<?php

declare(strict_types=1);

namespace Facturino;

use Facturino\Exception\ApiException;
use Facturino\Exception\AuthenticationException;
use Facturino\Exception\ConflictException;
use Facturino\Exception\InvalidRequestException;
use Facturino\Exception\RateLimitException;

/**
 * cURL-based HTTP client with retry logic and error handling.
 */
final class HttpClient
{
    private const MAX_ATTEMPTS = 4;
    private const INITIAL_RETRY_DELAY_MS = 500;
    private const RETRYABLE_STATUS_CODES = [429, 500, 502, 503];
    private const CONNECT_TIMEOUT = 10;
    private const TIMEOUT = 30;

    /**
     * Send an HTTP request to the Facturino API.
     *
     * @param string                    $method         HTTP method (GET, POST, PATCH, DELETE)
     * @param string                    $path           API path (e.g., /v1/invoices)
     * @param array<string, mixed>|null $params         Request body parameters (for POST/PATCH)
     * @param array<string, mixed>|null $queryParams    Query string parameters (for GET)
     * @param array<string, string>     $extraHeaders   Additional headers
     * @param bool                      $rawResponse    If true, return raw body string instead of decoded JSON
     * @return array<string, mixed>|string
     *
     * @throws AuthenticationException  If no API key is set or the key is invalid (401)
     * @throws RateLimitException       If the rate limit is exceeded (429)
     * @throws InvalidRequestException  If the request is invalid (4xx)
     * @throws ApiException             On network errors, server errors (5xx), or retry exhaustion
     */
    public static function request(
        string $method,
        string $path,
        ?array $params = null,
        ?array $queryParams = null,
        array $extraHeaders = [],
        bool $rawResponse = false,
    ): array|string {
        $apiKey = Facturino::getApiKey();
        if ($apiKey === null) {
            throw new AuthenticationException(
                'No API key provided. Set your API key with Facturino::setApiKey(\'fac_test_xxx\').'
            );
        }

        $method = strtoupper($method);
        $url = Facturino::getApiBase() . $path;

        if ($queryParams !== null) {
            $filtered = array_filter($queryParams, fn ($v) => $v !== null);
            if (!empty($filtered)) {
                // Serialize booleans as "true"/"false" rather than PHP's default
                // "1"/"" so they match the API's string-typed query filters.
                $filtered = array_map(
                    static fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : $v,
                    $filtered,
                );
                $url .= '?' . http_build_query($filtered);
            }
        }

        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'User-Agent: facturino-php/' . Facturino::VERSION,
            'Facturino-Version: ' . Facturino::API_VERSION,
            'Accept: application/json',
        ];

        $idempotencyKey = null;
        foreach ($extraHeaders as $key => $value) {
            if (strtolower($key) === 'idempotency-key') { $idempotencyKey = $value; }
        }
        if ($method === 'POST' && $idempotencyKey === null && Facturino::getAutoIdempotency()) {
            $idempotencyKey = bin2hex(random_bytes(16));
            $extraHeaders['Idempotency-Key'] = $idempotencyKey;
        }
        $canRetry = $method !== 'POST' || ($idempotencyKey !== null && $idempotencyKey !== '');
        $remainingBudget = Facturino::getRetryBudget() * 1000;
        foreach ($extraHeaders as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }

        $body = null;
        if ($params !== null && in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
            // Empty params must serialize to a JSON object `{}`, not an array `[]`
            // — the backend `schema.parse(req.body)` rejects an array ("Expected
            // object, received array" → 422) for no-param POSTs (remind, exports…).
            $body = json_encode($params === [] ? new \stdClass() : $params, JSON_THROW_ON_ERROR);
        }

        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_ATTEMPTS) {
            try {
                [$statusCode, $responseBody, $responseHeaders] = self::performCurlRequest($method, $url, $headers, $body);

                if ($statusCode >= 200 && $statusCode < 300) {
                    if ($rawResponse) {
                        return $responseBody;
                    }
                    if ($responseBody === '' || $statusCode === 204) {
                        return ['deleted' => true];
                    }
                    return self::decodeResponse($responseBody);
                }

                if ($canRetry && in_array($statusCode, self::RETRYABLE_STATUS_CODES, true) && $attempt + 1 < self::MAX_ATTEMPTS) {
                    $delay = self::computeRetryDelay($attempt, $responseHeaders);
                    if ($delay <= $remainingBudget) {
                        $remainingBudget -= $delay;
                        usleep($delay * 1000);
                        $attempt++;
                        continue;
                    }
                }

                self::handleErrorResponse($statusCode, $responseBody);
            } catch (ApiException $e) {
                if ($canRetry && $e->getHttpStatus() === 0 && $attempt + 1 < self::MAX_ATTEMPTS) {
                    $delay = self::computeRetryDelay($attempt, []);
                    if ($delay > $remainingBudget) { throw $e; }
                    $remainingBudget -= $delay;
                    usleep($delay * 1000);
                    $attempt++;
                    $lastException = $e;
                    continue;
                }
                throw $e;
            }
        }

        throw $lastException ?? new ApiException('Maximum retry attempts exceeded.');
    }

    /**
     * @return array{int, string, array<string, string>} [statusCode, responseBody, responseHeaders]
     */
    private static function performCurlRequest(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, string $header) use (&$responseHeaders): int {
            $len = strlen($header);
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        });

        $responseBody = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        // curl_close() is a no-op since PHP 8.0 (CurlHandle is garbage
        // collected) and deprecated since PHP 8.5.

        if ($curlErrno !== 0) {
            throw new ApiException(
                'Could not connect to the Facturino API: ' . $curlError,
                0,
                null,
                0
            );
        }

        return [(int) $statusCode, (string) $responseBody, $responseHeaders];
    }

    /**
     * @return int Delay in milliseconds
     */
    private static function computeRetryDelay(int $attempt, array $responseHeaders): int
    {
        // Respect Retry-After header if present
        if (isset($responseHeaders['retry-after'])) {
            $value = $responseHeaders['retry-after'];
            $retryAfter = is_numeric($value) ? (float) $value : (($date = strtotime($value)) === false ? -1 : max(0, $date - microtime(true)));
            if (is_finite($retryAfter) && $retryAfter >= 0) {
                return $retryAfter >= PHP_INT_MAX / 1000 ? PHP_INT_MAX : (int) ceil($retryAfter * 1000);
            }
        }

        // Exponential backoff with jitter
        $baseDelay = self::INITIAL_RETRY_DELAY_MS * (2 ** $attempt);
        $jitter = random_int(0, (int) ($baseDelay * 0.5));

        return $baseDelay + $jitter;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeResponse(string $body): array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException('Invalid JSON response from Facturino API: ' . substr($body, 0, 500));
        }

        if (!is_array($decoded)) {
            throw new ApiException('Unexpected response format from Facturino API.');
        }

        return $decoded;
    }

    private static function handleErrorResponse(int $statusCode, string $body): never
    {
        $decoded = [];
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Use raw body as message
        }

        $error = $decoded['error'] ?? [];
        $message = $error['message'] ?? 'Unknown API error (HTTP ' . $statusCode . ')';
        $type = $error['type'] ?? 'api_error';
        $code = $error['code'] ?? null;
        $param = $error['param'] ?? null;
        $docUrl = $error['doc_url'] ?? null;
        $requestId = $error['request_id'] ?? null;
        $hint = $error['hint'] ?? null;
        // Optional and additive: absent on a refusal that carries no detail.
        $issues = \is_array($error['issues'] ?? null) ? $error['issues'] : [];

        match (true) {
            $statusCode === 401 => throw new AuthenticationException($message, $statusCode, $requestId),
            $statusCode === 429 => throw new RateLimitException($message, $statusCode, $requestId),
            // A conflict is distinguishable without reading the status code by
            // hand — and stays an InvalidRequestException for existing callers.
            $statusCode === 409 => throw new ConflictException(
                $message,
                $statusCode,
                $type,
                $code,
                $param,
                $docUrl,
                $requestId,
                $hint,
                $issues,
            ),
            $statusCode >= 400 && $statusCode < 500 => throw new InvalidRequestException(
                $message,
                $statusCode,
                $type,
                $code,
                $param,
                $docUrl,
                $requestId,
                $hint,
                $issues,
            ),
            default => throw new ApiException($message, $statusCode, $requestId),
        };
    }
}
