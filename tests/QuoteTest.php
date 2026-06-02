<?php

declare(strict_types=1);

namespace Facturino\Tests;

use Facturino\Facturino;
use Facturino\Exception\AuthenticationException;
use PHPUnit\Framework\TestCase;

/**
 * Smoke tests for the quote action methods, mirroring the pattern used
 * for the other resource actions: assert the method is exposed both on
 * the convenience namespace class and the internal resource class, and
 * that invoking it without an API key reaches HttpClient and raises
 * AuthenticationException.
 */
final class QuoteTest extends TestCase
{
    protected function setUp(): void
    {
        Facturino::reset();
    }

    protected function tearDown(): void
    {
        Facturino::reset();
    }

    public function testCloneIsExposedOnBothClasses(): void
    {
        $this->assertTrue(
            method_exists(\Facturino\Quote::class, 'clone'),
            '\Facturino\Quote::clone() must be callable as a static convenience method',
        );
        $this->assertTrue(
            method_exists(\Facturino\Resource\Quote::class, 'clone'),
            '\Facturino\Resource\Quote::clone() must exist',
        );
    }

    public function testCloneRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Quote::clone('quo_test123');
    }

    public function testConvertRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Quote::convert('quo_test123');
    }
}
