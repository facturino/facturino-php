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

    public function testHealthRetrieveRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Health::retrieve();
    }

    public function testBillingPauseRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Billing::pause(['months' => 2]);
    }

    /**
     * Invoke a private static method on the Billing resource for white-box
     * assertions on the wire payload it builds.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function invokeBillingNormalizer(string $method, array $params): array
    {
        $ref = new \ReflectionMethod(\Facturino\Resource\Billing::class, $method);
        $ref->setAccessible(true);

        /** @var array<string, mixed> $result */
        $result = $ref->invoke(null, $params);

        return $result;
    }

    public function testCheckoutWirePayloadOnlyContainsPlanIdAndUrls(): void
    {
        $wire = self::invokeBillingNormalizer('normalizeCheckoutParams', [
            'plan' => 'pro',
            'cycle' => 'annual',
            'cancelAtPeriodEnd' => true,
            'successUrl' => 'https://app/ok',
            'cancelUrl' => 'https://app/ko',
        ]);

        $this->assertSame('pro', $wire['planId']);
        $this->assertSame('https://app/ok', $wire['successUrl']);
        $this->assertSame('https://app/ko', $wire['cancelUrl']);
        $this->assertArrayNotHasKey('plan', $wire);
        $this->assertArrayNotHasKey('cycle', $wire);
        $this->assertArrayNotHasKey('annual', $wire);
        $this->assertArrayNotHasKey('cancelAtPeriodEnd', $wire);
    }

    public function testCheckoutKeepsExplicitPlanId(): void
    {
        $wire = self::invokeBillingNormalizer('normalizeCheckoutParams', [
            'planId' => 'cabinet_50',
            'successUrl' => 'https://app/ok',
            'cancelUrl' => 'https://app/ko',
        ]);

        $this->assertSame('cabinet_50', $wire['planId']);
        $this->assertArrayNotHasKey('plan', $wire);
    }

    public function testUpdateSubscriptionMapsPlanAndCycleToWire(): void
    {
        $wire = self::invokeBillingNormalizer('normalizeSubscriptionParams', [
            'plan' => 'pro',
            'cycle' => 'annual',
            'cancelAtPeriodEnd' => true,
        ]);

        $this->assertSame('pro', $wire['planId']);
        $this->assertTrue($wire['annual']);
        $this->assertArrayNotHasKey('plan', $wire);
        $this->assertArrayNotHasKey('cycle', $wire);
        $this->assertArrayNotHasKey('cancelAtPeriodEnd', $wire);
    }

    public function testUpdateSubscriptionMonthlyCycleBecomesAnnualFalse(): void
    {
        $wire = self::invokeBillingNormalizer('normalizeSubscriptionParams', [
            'planId' => 'essential',
            'cycle' => 'monthly',
        ]);

        $this->assertSame('essential', $wire['planId']);
        $this->assertFalse($wire['annual']);
        $this->assertArrayNotHasKey('cycle', $wire);
    }

    public function testUpdateSubscriptionRespectsExplicitAnnualBool(): void
    {
        $wire = self::invokeBillingNormalizer('normalizeSubscriptionParams', [
            'planId' => 'pro',
            'annual' => true,
        ]);

        $this->assertSame('pro', $wire['planId']);
        $this->assertTrue($wire['annual']);
        $this->assertArrayNotHasKey('cycle', $wire);
    }
}
