<?php

declare(strict_types=1);

namespace Facturino\Exception;

/**
 * Base exception for all Facturino API errors (5xx, network errors).
 */
class ApiException extends \Exception
{
    private int $httpStatus;
    private ?string $requestId;

    public function __construct(
        string $message = '',
        int $httpStatus = 0,
        ?string $requestId = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->httpStatus = $httpStatus;
        $this->requestId = $requestId;
    }

    /** HTTP status code returned by the API (0 for network errors). */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /** Unique request ID for support reference. */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
