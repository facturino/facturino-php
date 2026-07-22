<?php

declare(strict_types=1);

namespace Facturino\Tests;

use Facturino\Facturino;
use Facturino\Exception\AuthenticationException;
use PHPUnit\Framework\TestCase;

/**
 * Smoke tests for the methods backfilled on existing resources to
 * reach actual 100% API coverage:
 *
 *  - Account: requestExport, downloadExport
 *  - Company: create, addMilestone
 *  - Invoice: createPortalLink
 */
final class MissingMethodsTest extends TestCase
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
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function methodProvider(): array
    {
        return [
            'Account::requestExport'           => [\Facturino\Account::class, 'requestExport'],
            'Account::downloadExport'          => [\Facturino\Account::class, 'downloadExport'],
            'Company::create'                  => [\Facturino\Company::class, 'create'],
            'Company::addMilestone'            => [\Facturino\Company::class, 'addMilestone'],
            'Invoice::createPortalLink'        => [\Facturino\Invoice::class, 'createPortalLink'],
            'Payment::cancel'                  => [\Facturino\Payment::class, 'cancel'],
            'CreditNote::refund'               => [\Facturino\CreditNote::class, 'refund'],
        ];
    }

    /**
     * @dataProvider methodProvider
     */
    public function testBackfilledMethodExists(string $class, string $method): void
    {
        $this->assertTrue(
            method_exists($class, $method),
            "{$class}::{$method}() must be exposed",
        );
    }

    public function testAccountDownloadExportRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Account::downloadExport('rgpdexp_abc');
    }

    public function testCompanyCreateRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Company::create([
            'name' => 'ACME SAS',
            'siret' => '44306184100047',
            'address' => [
                'line1' => '12 rue de Rivoli',
                'postalCode' => '75001',
                'city' => 'Paris',
                'countryCode' => 'FR',
            ],
            'vatRegime' => 'normal',
        ]);
    }

    public function testCompanyAddMilestoneRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Company::addMilestone('comp_x', 'first_invoice_sent');
    }

    public function testInvoiceCreatePortalLinkRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Invoice::createPortalLink('inv_x');
    }

    public function testPaymentCancelRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\Payment::cancel('inv_x', 'pay_x');
    }

    public function testCreditNoteRefundRequiresApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        \Facturino\CreditNote::refund('crn_x', ['amount' => 12000, 'method' => 'transfer']);
    }
}
