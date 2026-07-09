<?php

declare(strict_types=1);

namespace Facturino\Tests;

use Facturino\Facturino;
use Facturino\Exception\AuthenticationException;
use PHPUnit\Framework\TestCase;

/**
 * Smoke tests for the resources added to reach 100% API coverage:
 * Billing, Reference, Usage, Validate, Health.
 *
 * Network access is gated by the lazy curl call so the unit tests
 * only assert that:
 *  - the convenience namespace classes \Facturino\<Name> are exposed
 *  - the corresponding \Facturino\Resource\<Name> classes exist
 *  - every documented static method is callable
 *  - calling any method without an API key raises
 *    AuthenticationException (proves the request reaches HttpClient)
 *
 * Full integration coverage runs against the live Functions emulator.
 */
final class NewResourcesTest extends TestCase
{
    protected function setUp(): void
    {
        Facturino::reset();
    }

    protected function tearDown(): void
    {
        Facturino::reset();
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function resourceMethodsProvider(): array
    {
        return [
            'Billing' => [
                'Billing',
                ['retrieveSubscription', 'listInvoices', 'getInvoicePdf'],
            ],
            'Reference' => [
                'Reference',
                ['listLegalForms', 'listNafCodes'],
            ],
            'Usage' => [
                'Usage',
                ['retrieve'],
            ],
            'Validate' => [
                'Validate',
                ['run'],
            ],
            'Health' => [
                'Health',
                ['retrieve'],
            ],
        ];
    }

    /**
     * @dataProvider resourceMethodsProvider
     * @param list<string> $methods
     */
    public function testEveryResourceExposesItsDocumentedMethods(
        string $name,
        array $methods,
    ): void {
        $convenience = '\\Facturino\\' . $name;
        $internal = '\\Facturino\\Resource\\' . $name;

        $this->assertTrue(class_exists($convenience), "Convenience class {$convenience} must exist");
        $this->assertTrue(class_exists($internal), "Internal class {$internal} must exist");

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($convenience, $method),
                "{$convenience}::{$method}() must be callable as a static convenience method",
            );
            $this->assertTrue(
                method_exists($internal, $method),
                "{$internal}::{$method}() must exist",
            );
        }
    }

    public function testBillingRetrieveSubscriptionRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Billing::retrieveSubscription();
    }

    public function testReferenceListLegalFormsRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Reference::listLegalForms(['search' => 'SAS']);
    }

    public function testUsageRetrieveRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Usage::retrieve();
    }

    public function testValidateRunRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Validate::run(['kind' => 'siret', 'value' => '44306184100047']);
    }

    public function testHealthRetrieveRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Health::retrieve();
    }

}
