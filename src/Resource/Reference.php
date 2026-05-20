<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;
use Facturino\Util\Collection;

/**
 * Reference resource — /v1/reference
 *
 * Static lookup tables maintained by INSEE that integrations need to
 * power their own company / customer forms:
 *
 *  - `legalForms` — French legal-form codes (SARL, SAS, EI…)
 *  - `nafCodes`   — French NAF activity codes (Rev. 2, 2008)
 *
 * Both lists are stable and cacheable for the lifetime of the
 * integration's process.
 */
class Reference
{
    private const BASE_PATH = '/v1/reference';

    /**
     * List INSEE legal forms (4-digit codes + sigles + labels).
     *
     * @param array<string, mixed> $params  search, limit
     * @return Collection
     */
    public static function listLegalForms(array $params = []): Collection
    {
        $path = self::BASE_PATH . '/legal-forms';
        $response = HttpClient::request('GET', $path, null, $params);

        return new Collection($response, $params, $path);
    }

    /**
     * List NAF activity codes (Rev. 2).
     *
     * @param array<string, mixed> $params  search, limit
     * @return Collection
     */
    public static function listNafCodes(array $params = []): Collection
    {
        $path = self::BASE_PATH . '/naf-codes';
        $response = HttpClient::request('GET', $path, null, $params);

        return new Collection($response, $params, $path);
    }
}
