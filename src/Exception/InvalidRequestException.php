<?php

declare(strict_types=1);

namespace Facturino\Exception;

/**
 * Thrown for 4xx client errors (validation, not found, invalid state transitions).
 *
 * Mirrors the Stripe-like error structure:
 * {error: {type, code, message, param, doc_url, request_id, hint}}
 */
class InvalidRequestException extends ApiException
{
    private ?string $errorType;
    private ?string $errorCode;
    private ?string $param;
    private ?string $docUrl;
    private ?string $hint;

    public function __construct(
        string $message = '',
        int $httpStatus = 400,
        ?string $errorType = null,
        ?string $errorCode = null,
        ?string $param = null,
        ?string $docUrl = null,
        ?string $requestId = null,
        ?string $hint = null,
    ) {
        parent::__construct($message, $httpStatus, $requestId);
        $this->errorType = $errorType;
        $this->errorCode = $errorCode;
        $this->param = $param;
        $this->docUrl = $docUrl;
        $this->hint = $hint;
    }

    /** Error type (e.g. "invalid_request_error", "validation_error"). */
    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    /** Machine-readable error code (e.g. "invoice_already_finalized"). */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** Name of the parameter that caused the error. */
    public function getParam(): ?string
    {
        return $this->param;
    }

    /** Link to relevant API documentation. */
    public function getDocUrl(): ?string
    {
        return $this->docUrl;
    }

    /** Human-readable suggestion for fixing the error. */
    public function getHint(): ?string
    {
        return $this->hint;
    }
}
