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

    /** @var list<array{code: string, param: ?string, message: string}> */
    private array $issues;

    /**
     * @param list<array{code: string, param: ?string, message: string}> $issues
     */
    public function __construct(
        string $message = '',
        int $httpStatus = 0,
        ?string $requestId = null,
        int $code = 0,
        ?\Throwable $previous = null,
        array $issues = [],
    ) {
        parent::__construct($message, $code, $previous);
        $this->httpStatus = $httpStatus;
        $this->requestId = $requestId;
        $this->issues = $issues;
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

    /**
     * Detailed reasons behind this one refusal.
     *
     * Empty when the API sent none, so reading it never needs a null check.
     * `getErrorCode()` stays the value to branch on; each entry adds a more
     * precise code and, when it is certain, the field in cause.
     *
     * @return list<array{code: string, param: ?string, message: string}>
     */
    public function getIssues(): array
    {
        return $this->issues;
    }
}
