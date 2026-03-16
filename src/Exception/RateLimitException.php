<?php

declare(strict_types=1);

namespace Facturino\Exception;

/**
 * Thrown when the API rate limit is exceeded (429).
 */
class RateLimitException extends ApiException
{
    public function __construct(
        string $message = 'Rate limit exceeded.',
        int $httpStatus = 429,
        ?string $requestId = null,
    ) {
        parent::__construct($message, $httpStatus, $requestId);
    }
}
