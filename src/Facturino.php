<?php

declare(strict_types=1);

namespace Facturino;

/**
 * Main entry point for the Facturino SDK.
 *
 * Usage:
 *   \Facturino\Facturino::setApiKey('fac_test_xxx');
 *   $invoice = \Facturino\Invoice::create([...]);
 */
final class Facturino
{
    public const VERSION = '2.10.0';
    public const API_VERSION = '2026-09-01';
    public const API_BASE = 'https://facturino.com/api';

    private static ?string $apiKey = null;
    private static ?string $apiBase = null;
    private static bool $autoIdempotency = true;
    private static float $retryBudget = 60.0;

    /**
     * Set the API key used for all requests.
     *
     * @param string $apiKey A key starting with fac_test_ or fac_live_
     */
    public static function setApiKey(string $apiKey): void
    {
        self::$apiKey = $apiKey;
    }

    /**
     * Get the currently configured API key.
     */
    public static function getApiKey(): ?string
    {
        return self::$apiKey;
    }

    /**
     * Override the API base URL (useful for testing).
     */
    public static function setApiBase(string $apiBase): void
    {
        self::$apiBase = rtrim($apiBase, '/');
    }

    /**
     * Get the API base URL.
     */
    public static function getApiBase(): string
    {
        return self::$apiBase ?? self::API_BASE;
    }

    /** Generate one stable key per POST call; unkeyed POST calls never retry. */
    public static function setAutoIdempotency(bool $enabled): void { self::$autoIdempotency = $enabled; }
    public static function getAutoIdempotency(): bool { return self::$autoIdempotency; }

    /** Maximum cumulative retry waiting seconds (default 60). Longer Retry-After returns the HTTP error. */
    public static function setRetryBudget(float $seconds): void
    {
        if (!is_finite($seconds) || $seconds < 0) { throw new \InvalidArgumentException('Retry budget must be finite and non-negative.'); }
        self::$retryBudget = $seconds;
    }
    public static function getRetryBudget(): float { return self::$retryBudget; }

    /**
     * Reset all global configuration (primarily for tests).
     */
    public static function reset(): void
    {
        self::$apiKey = null;
        self::$apiBase = null;
        self::$autoIdempotency = true;
        self::$retryBudget = 60.0;
    }
}
