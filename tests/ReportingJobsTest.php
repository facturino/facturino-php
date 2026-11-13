<?php

declare(strict_types=1);

namespace Facturino\Tests;

use Facturino\Exception\ApiException;
use Facturino\Facturino;
use PHPUnit\Framework\TestCase;

/**
 * Reports, invoice export jobs and the audit trail PDF, against the shapes the
 * API sends (camelCase reports; `invoice_export` jobs; 501 on the audit trail PDF).
 */
final class ReportingJobsTest extends TestCase
{
    private LoopbackServer $server;

    protected function setUp(): void
    {
        Facturino::reset();
        $this->server = new LoopbackServer();
        Facturino::setApiKey('fac_test_reporting');
        Facturino::setApiBase($this->server->baseUrl());
    }

    protected function tearDown(): void
    {
        $this->server->stop();
        Facturino::reset();
    }

    public function testReportsAreReadInCamelCaseWithNonZeroValues(): void
    {
        $vat = [
            'object' => 'vat_report',
            'period' => ['start' => '2026-01-01', 'end' => '2026-03-31'],
            'vatBreakdown' => [['rate' => '20.00', 'taxableAmount' => 10000, 'vatAmount' => 2000]],
            'totalHT' => 10000,
            'totalVAT' => 2000,
            'totalTTC' => 12000,
            'invoiceCount' => 3,
        ];
        $group = [
            'revenue' => ['invoiced' => 12000, 'creditNotes' => -2400, 'net' => 9600],
            'payments' => ['received' => 6000, 'outstanding' => 3600],
            'invoiceCount' => 3,
            'creditNoteCount' => 1,
        ];
        $revenue = [
            'object' => 'revenue_report',
            'period' => ['start' => '2026-01-01', 'end' => '2026-03-31'],
        ] + $group + ['breakdown' => [['period' => '2026-Q1'] + $group]];

        $this->server->respondWith(200, json_encode($vat, JSON_THROW_ON_ERROR));
        $vatReport = \Facturino\Reporting::vat(['period_start' => '2026-01-01', 'period_end' => '2026-03-31']);
        $this->server->respondWith(200, json_encode($revenue, JSON_THROW_ON_ERROR));
        $revenueReport = \Facturino\Reporting::revenue(['period_start' => '2026-01-01', 'period_end' => '2026-03-31', 'group_by' => 'quarter']);

        $this->assertSame(10000, $vatReport['vatBreakdown'][0]['taxableAmount']);
        $this->assertSame(2000, $vatReport['vatBreakdown'][0]['vatAmount']);
        $this->assertSame([10000, 2000, 12000, 3], [$vatReport['totalHT'], $vatReport['totalVAT'], $vatReport['totalTTC'], $vatReport['invoiceCount']]);
        $this->assertSame(-2400, $revenueReport['revenue']['creditNotes']);
        $this->assertSame([3, 1], [$revenueReport['invoiceCount'], $revenueReport['creditNoteCount']]);
        $this->assertSame(-2400, $revenueReport['breakdown'][0]['revenue']['creditNotes']);
        $this->assertSame(1, $revenueReport['breakdown'][0]['creditNoteCount']);
        $this->assertArrayNotHasKey('invoice_count', $revenueReport);
        $this->assertArrayNotHasKey('credit_notes', $revenueReport['revenue']);
    }

    public function testInvoiceExportJobKeepsIncludedFailedAndTruncated(): void
    {
        $job = [
            'id' => 'job_export',
            'object' => 'job',
            'type' => 'invoice_export',
            'status' => 'completed_with_errors',
            'progress' => 100,
            'result' => 'exports/job_export.zip',
            'error' => null,
            'included' => 4998,
            'failed' => 2,
            'truncated' => true,
            'created' => '2026-11-13T10:00:00.000Z',
        ];
        $this->server->respondWith(200, json_encode($job, JSON_THROW_ON_ERROR));

        $result = \Facturino\Job::retrieve('job_export');

        $this->assertSame('/v1/jobs/job_export', $this->server->lastRequest()['path']);
        $this->assertSame('invoice_export', $result['type']);
        $this->assertSame([4998, 2, true], [$result['included'], $result['failed'], $result['truncated']]);
    }

    public function testAuditTrailPdfNotImplementedIsRaisedAsAnApiExceptionWithoutRetry(): void
    {
        $this->server->respondWith(501, json_encode([
            'error' => [
                'type' => 'api_error',
                'code' => 'not_implemented',
                'message' => 'Audit trail PDF is not available yet.',
                'request_id' => 'req_501',
            ],
        ], JSON_THROW_ON_ERROR));

        try {
            \Facturino\Invoice::generateAuditTrailPdf('inv_1');
            $this->fail('Expected the 501 answer to be raised');
        } catch (ApiException $error) {
            $this->assertSame(501, $error->getHttpStatus());
            $this->assertSame('Audit trail PDF is not available yet.', $error->getMessage());
            $this->assertSame('req_501', $error->getRequestId());
        }

        $this->assertCount(1, $this->server->requests());
        $this->assertSame('/v1/invoices/inv_1/audit-trail/pdf', $this->server->lastRequest()['path']);
    }
}
