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
     * @param string $id Job ID (job_xxx)
     * @return array<string, mixed>
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }
}
