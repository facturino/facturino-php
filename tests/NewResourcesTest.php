<?php

declare(strict_types=1);

namespace Facturino\Tests;

use Facturino\Facturino;
use Facturino\Exception\AuthenticationException;
use PHPUnit\Framework\TestCase;

/**
 * Smoke tests for the resources added to reach 100% API coverage:
 * Billing, Cabinet, Notification, Reference, Setting, Usage, Validate.
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
                ['retrieveSubscription', 'updateSubscription', 'checkout', 'portal',
                 'pause', 'resume', 'listInvoices', 'getInvoicePdf'],
            ],
            'Cabinet' => [
                'Cabinet',
                ['all', 'retrieve', 'create', 'updateBranding', 'dashboard',
                 'activity', 'billingSplit', 'listCompanies', 'addCompany', 'inviteMember'],
            ],
            'Notification' => [
                'Notification',
                ['all', 'markRead', 'markAllRead', 'retrievePreferences', 'updatePreferences'],
            ],
            'Reference' => [
                'Reference',
                ['listLegalForms', 'listNafCodes'],
            ],
            'Setting' => [
                'Setting',
                ['retrieveAccounting', 'updateAccounting', 'retrieveReminders', 'updateReminders'],
            ],
            'Usage' => [
                'Usage',
                ['retrieve'],
            ],
            'Validate' => [
                'Validate',
                ['run'],
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

    public function testCabinetCreateRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Cabinet::create(['name' => 'Cab', 'plan' => 'cabinet_50']);
    }

    public function testNotificationRetrievePreferencesRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Notification::retrievePreferences();
    }

    public function testReferenceListLegalFormsRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Reference::listLegalForms(['search' => 'SAS']);
    }

    public function testSettingRetrieveAccountingRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Setting::retrieveAccounting('comp_x');
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
}
