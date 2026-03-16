<?php

declare(strict_types=1);

namespace Facturino\Util;

use Facturino\Exception\InvalidRequestException;

/**
 * Webhook signature verification (HMAC-SHA256).
 *
 * Signature format: t=<timestamp>,v1=<hmac_hex>
 * Signed payload: <timestamp>.<json_body>
 */
class Webhook
{
    private const DEFAULT_TOLERANCE = 300; // 5 minutes

    /**
     * Construct and verify a webhook event from the raw payload.
     *
     * @param string $payload          Raw request body (JSON string)
     * @param string $signatureHeader  Value of the Facturino-Signature header
     * @param string $secret           Webhook endpoint signing secret (whsec_xxx)
     * @param int    $tolerance        Maximum age of the event in seconds (default: 300)
     * @return array<string, mixed>    Decoded event object
     * @throws InvalidRequestException If verification fails
     */
    public static function constructEvent(
        string $payload,
        string $signatureHeader,
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
    ): array {
        self::verifySignature($payload, $signatureHeader, $secret, $tolerance);

        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidRequestException(
                'Invalid webhook payload: unable to parse JSON.',
                400,
                'webhook_error',
                'invalid_payload',
            );
        }

        if (!is_array($event)) {
            throw new InvalidRequestException(
                'Invalid webhook payload: expected object.',
                400,
                'webhook_error',
                'invalid_payload',
            );
        }

        return $event;
    }

    /**
     * Verify a Facturino-Signature header.
     *
     * @param string $payload          Raw JSON body
     * @param string $signatureHeader  Facturino-Signature header value
     * @param string $secret           Endpoint signing secret
     * @param int    $tolerance        Maximum age in seconds
     * @throws InvalidRequestException If verification fails
     */
    public static function verifySignature(
        string $payload,
        string $signatureHeader,
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
    ): void {
        $parts = explode(',', $signatureHeader);

        $timestamp = null;
        $signatures = [];

        foreach ($parts as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) !== 2) {
                continue;
            }

            [$key, $value] = $kv;

            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null) {
            throw new InvalidRequestException(
                'Unable to extract timestamp from Facturino-Signature header.',
                400,
                'webhook_error',
                'invalid_signature',
            );
        }

        if (empty($signatures)) {
            throw new InvalidRequestException(
                'No v1 signature found in Facturino-Signature header.',
                400,
                'webhook_error',
                'invalid_signature',
            );
        }

        // Anti-replay check
        $now = time();
        if (abs($now - $timestamp) > $tolerance) {
            throw new InvalidRequestException(
                'Webhook timestamp is outside the tolerance zone (' . $tolerance . 's). '
                . 'The event may be a replay attack or your server clock is skewed.',
                400,
                'webhook_error',
                'timestamp_expired',
            );
        }

        // Compute expected signature
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        // Timing-safe comparison against all v1 signatures
        $matched = false;
        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            throw new InvalidRequestException(
                'Webhook signature verification failed. '
                . 'Ensure you are using the correct endpoint secret.',
                400,
                'webhook_error',
                'signature_mismatch',
            );
        }
    }

    /**
     * Generate a signature header value (useful for testing).
     *
     * @param string $payload   JSON payload
     * @param string $secret    Signing secret
     * @param int|null $timestamp  Unix timestamp (defaults to current time)
     * @return string Facturino-Signature header value
     */
    public static function generateSignature(
        string $payload,
        string $secret,
        ?int $timestamp = null,
    ): string {
        $timestamp ??= time();
        $signedPayload = $timestamp . '.' . $payload;
        $hmac = hash_hmac('sha256', $signedPayload, $secret);

        return 't=' . $timestamp . ',v1=' . $hmac;
    }
}
