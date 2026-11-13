<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;

/**
 * Job resource — /v1/jobs
 *
 * Async job status polling (PDF generation, FEC export, etc.).
 */
class Job
{
    private const BASE_PATH = '/v1/jobs';

    /**
     * Retrieve a job's status.
     *
     * A job of type `invoice_export` also carries `included` (pieces in the
     * archive), `failed` (pieces that could not be generated) and `truncated`
     * (`true` when the archive was limited to 5 000 pieces).
     *
     * @param string $id Job ID (job_xxx)
     * @return array<string, mixed>
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }
}
