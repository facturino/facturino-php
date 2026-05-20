<?php

declare(strict_types=1);

namespace Facturino\Tests;

use Facturino\Facturino;
use Facturino\Exception\AuthenticationException;
use PHPUnit\Framework\TestCase;

/**
 * Smoke tests for the Account resource. Network access is gated by the
 * lazy curl call so the unit tests only assert that:
 *  - the convenience class \Facturino\Account exposes retrieve()
 *  - calling retrieve() without a key raises AuthenticationException
 *
 * Full integration coverage runs against the live Functions emulator
 * (cf. facturino/scripts/test-api-flows in the main repo).
 */
final class AccountTest extends TestCase
{
    protected function setUp(): void
    {
        Facturino::reset();
    }

    protected function tearDown(): void
    {
        Facturino::reset();
    }

    public function testRetrieveWithoutApiKeyThrowsAuthenticationException(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No API key provided');

        \Facturino\Account::retrieve();
    }

    public function testRetrieveIsExposedAsStaticMethod(): void
    {
        $this->assertTrue(
            method_exists(\Facturino\Account::class, 'retrieve'),
            '\\Facturino\\Account::retrieve() must be callable as a static convenience alias'
        );
        $this->assertTrue(
            method_exists(\Facturino\Resource\Account::class, 'retrieve'),
            '\\Facturino\\Resource\\Account::retrieve() must exist'
        );
    }
}
