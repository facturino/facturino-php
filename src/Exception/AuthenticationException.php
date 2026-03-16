<?php

declare(strict_types=1);

namespace Facturino\Exception;

/**
 * Thrown when authentication fails (401).
 */
class AuthenticationException extends ApiException
{
    public function __construct(
        string $message = 'Invalid API key.',
        int $httpStatus = 401,
        ?string $requestId = null,
    ) {
        parent::__construct($message, $httpStatus, $requestId);
    }
}
