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
    public const VERSION = '1.0.0';
    public const API_BASE = 'https://facturino.com/api';

    private static ?string $apiKey = null;
    private static ?string $apiBase = null;

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

    /**
     * Reset all global configuration (primarily for tests).
     */
    public static function reset(): void
    {
        self::$apiKey = null;
        self::$apiBase = null;
    }
}
