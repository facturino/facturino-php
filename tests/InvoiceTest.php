<?php

declare(strict_types=1);

namespace Facturino\Tests;

use Facturino\Facturino;
use Facturino\Exception\AuthenticationException;
use Facturino\Exception\ApiException;
use PHPUnit\Framework\TestCase;

final class InvoiceTest extends TestCase
{
    protected function setUp(): void
    {
        Facturino::reset();
    }

    protected function tearDown(): void
    {
        Facturino::reset();
    }

    public function testSetApiKeyStoresKey(): void
    {
        $this->assertNull(Facturino::getApiKey());

        Facturino::setApiKey('fac_test_abcdef1234567890');

        $this->assertEquals('fac_test_abcdef1234567890', Facturino::getApiKey());
    }

    public function testSetApiBaseOverridesDefault(): void
    {
        $this->assertEquals('https://facturino.com/api', Facturino::getApiBase());

        Facturino::setApiBase('https://localhost:3000/api');

        $this->assertEquals('https://localhost:3000/api', Facturino::getApiBase());
    }

    public function testResetClearsAll(): void
    {
        Facturino::setApiKey('fac_test_key');
        Facturino::setApiBase('https://test.example.com');

        Facturino::reset();

        $this->assertNull(Facturino::getApiKey());
        $this->assertEquals('https://facturino.com/api', Facturino::getApiBase());
    }

    public function testCreateWithoutApiKeyThrowsAuthenticationException(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No API key provided');

        \Facturino\Invoice::create([
            'customerId' => 'cus_test123',
            'taxDecisionId' => 'taxdec_test123',
            'decisionLines' => [['taxLineRef' => 'l1', 'unit' => 'unit']],
        ]);
    }

    public function testRetrieveWithoutApiKeyThrowsAuthenticationException(): void
    {
        $this->expectException(AuthenticationException::class);

        \Facturino\Invoice::retrieve('inv_test123');
    }

    public function testAllWithoutApiKeyThrowsAuthenticationException(): void
    {
        $this->expectException(AuthenticationException::class);

        \Facturino\Invoice::all(['limit' => 10]);
    }

    public function testCreateWithInvalidUrlThrowsApiException(): void
    {
        Facturino::setApiKey('fac_test_key');
        Facturino::setApiBase('https://invalid.nonexistent.domain.example.com');

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Could not connect');

        \Facturino\Invoice::create([
            'customerId' => 'cus_test123',
            'taxDecisionId' => 'taxdec_test123',
            'decisionLines' => [['taxLineRef' => 'l1', 'unit' => 'unit']],
        ]);
    }

    public function testVersionConstant(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Facturino::VERSION);
    }

    public function testApiBaseTrailingSlashStripped(): void
    {
        Facturino::setApiBase('https://test.example.com/api/');
        $this->assertEquals('https://test.example.com/api', Facturino::getApiBase());
    }
}
