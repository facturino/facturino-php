<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 */
final class CreditNoteEinvoicing implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    public readonly ?string $paErrorCode;
    public readonly ?string $paStatusCode;
    public readonly ?string $rejectionCode;
    public readonly ?PaRejectionSource $rejectionSource;
    public readonly ?string $rejectionReason;
    public readonly ?string $rejectionNote;
    public readonly ?PaRejectionCategory $rejectionCategory;
    public readonly ?array $previousSubmissions;
    public readonly ?string $paId;
    public readonly ?string $paStatus;
    public readonly ?string $depositedAt;
    public readonly ?string $paIdempotencyKey;
    public readonly ?string $ereportingId;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->paErrorCode = isset($data['paErrorCode']) ? $data['paErrorCode'] : null;
        $this->paStatusCode = isset($data['paStatusCode']) ? $data['paStatusCode'] : null;
        $this->rejectionCode = isset($data['rejectionCode']) ? $data['rejectionCode'] : null;
        $this->rejectionSource = isset($data['rejectionSource']) ? PaRejectionSource::tryFrom($data['rejectionSource']) : null;
        $this->rejectionReason = isset($data['rejectionReason']) ? $data['rejectionReason'] : null;
        $this->rejectionNote = isset($data['rejectionNote']) ? $data['rejectionNote'] : null;
        $this->rejectionCategory = isset($data['rejectionCategory']) ? PaRejectionCategory::tryFrom($data['rejectionCategory']) : null;
        $this->previousSubmissions = isset($data['previousSubmissions']) ? array_map(static fn (array $item): InvoicePreviousSubmission => InvoicePreviousSubmission::fromArray($item), $data['previousSubmissions']) : null;
        $this->paId = isset($data['paId']) ? $data['paId'] : null;
        $this->paStatus = isset($data['paStatus']) ? $data['paStatus'] : null;
        $this->depositedAt = isset($data['depositedAt']) ? $data['depositedAt'] : null;
        $this->paIdempotencyKey = isset($data['paIdempotencyKey']) ? $data['paIdempotencyKey'] : null;
        $this->ereportingId = isset($data['ereportingId']) ? $data['ereportingId'] : null;
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
