<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 */
final class InvoicePreviousSubmission implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    public readonly ?string $paId;
    public readonly ?string $paTransactionId;
    public readonly ?string $paIdempotencyKey;
    public readonly ?string $paStatus;
    public readonly ?string $paStatusCode;
    public readonly ?string $paErrorCode;
    public readonly ?string $rejectionReason;
    public readonly ?PaRejectionCategory $rejectionCategory;
    public readonly ?string $sentAt;
    public readonly ?string $closedAt;
    public readonly ?string $rejectionCode;
    public readonly ?PaRejectionSource $rejectionSource;
    public readonly ?string $rejectionNote;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->paId = isset($data['paId']) ? $data['paId'] : null;
        $this->paTransactionId = isset($data['paTransactionId']) ? $data['paTransactionId'] : null;
        $this->paIdempotencyKey = isset($data['paIdempotencyKey']) ? $data['paIdempotencyKey'] : null;
        $this->paStatus = isset($data['paStatus']) ? $data['paStatus'] : null;
        $this->paStatusCode = isset($data['paStatusCode']) ? $data['paStatusCode'] : null;
        $this->paErrorCode = isset($data['paErrorCode']) ? $data['paErrorCode'] : null;
        $this->rejectionReason = isset($data['rejectionReason']) ? $data['rejectionReason'] : null;
        $this->rejectionCategory = isset($data['rejectionCategory']) ? PaRejectionCategory::tryFrom($data['rejectionCategory']) : null;
        $this->sentAt = isset($data['sentAt']) ? $data['sentAt'] : null;
        $this->closedAt = isset($data['closedAt']) ? $data['closedAt'] : null;
        $this->rejectionCode = isset($data['rejectionCode']) ? $data['rejectionCode'] : null;
        $this->rejectionSource = isset($data['rejectionSource']) ? PaRejectionSource::tryFrom($data['rejectionSource']) : null;
        $this->rejectionNote = isset($data['rejectionNote']) ? $data['rejectionNote'] : null;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }
}
