<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 */
final class InvoiceEinvoicing implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    public readonly ?string $paId;
    public readonly ?string $paStatus;
    public readonly ?string $paStatusCode;
    public readonly ?string $paTransactionId;
    public readonly ?string $paErrorCode;
    public readonly ?string $rejectionReason;
    public readonly ?PaRejectionCategory $rejectionCategory;
    public readonly ?string $refusalReason;
    public readonly ?string $paIdempotencyKey;
    public readonly ?string $peppolDeliveryId;
    public readonly ?string $ereportingId;
    public readonly ?string $sentAt;
    public readonly ?string $trackingId;
    public readonly ?InvoiceSubmissionArtefact $submissionArtefact;
    public readonly ?array $previousSubmissions;
    public readonly ?string $rejectionCode;
    public readonly ?PaRejectionSource $rejectionSource;
    public readonly ?string $rejectionNote;
    public readonly ?string $routingIdentifier;
    public readonly ?string $buyerReachableAt;
    public readonly ?string $directoryCheckedAt;
    public readonly ?string $ereportingPaymentId;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->paId = isset($data['paId']) ? $data['paId'] : null;
        $this->paStatus = isset($data['paStatus']) ? $data['paStatus'] : null;
        $this->paStatusCode = isset($data['paStatusCode']) ? $data['paStatusCode'] : null;
        $this->paTransactionId = isset($data['paTransactionId']) ? $data['paTransactionId'] : null;
        $this->paErrorCode = isset($data['paErrorCode']) ? $data['paErrorCode'] : null;
        $this->rejectionReason = isset($data['rejectionReason']) ? $data['rejectionReason'] : null;
        $this->rejectionCategory = isset($data['rejectionCategory']) ? PaRejectionCategory::tryFrom($data['rejectionCategory']) : null;
        $this->refusalReason = isset($data['refusalReason']) ? $data['refusalReason'] : null;
        $this->paIdempotencyKey = isset($data['paIdempotencyKey']) ? $data['paIdempotencyKey'] : null;
        $this->peppolDeliveryId = isset($data['peppolDeliveryId']) ? $data['peppolDeliveryId'] : null;
        $this->ereportingId = isset($data['ereportingId']) ? $data['ereportingId'] : null;
        $this->sentAt = isset($data['sentAt']) ? $data['sentAt'] : null;
        $this->trackingId = isset($data['trackingId']) ? $data['trackingId'] : null;
        $this->submissionArtefact = isset($data['submissionArtefact']) ? InvoiceSubmissionArtefact::fromArray($data['submissionArtefact']) : null;
        $this->previousSubmissions = isset($data['previousSubmissions']) ? array_map(static fn (array $item): InvoicePreviousSubmission => InvoicePreviousSubmission::fromArray($item), $data['previousSubmissions']) : null;
        $this->rejectionCode = isset($data['rejectionCode']) ? $data['rejectionCode'] : null;
        $this->rejectionSource = isset($data['rejectionSource']) ? PaRejectionSource::tryFrom($data['rejectionSource']) : null;
        $this->rejectionNote = isset($data['rejectionNote']) ? $data['rejectionNote'] : null;
        $this->routingIdentifier = isset($data['routingIdentifier']) ? $data['routingIdentifier'] : null;
        $this->buyerReachableAt = isset($data['buyerReachableAt']) ? $data['buyerReachableAt'] : null;
        $this->directoryCheckedAt = isset($data['directoryCheckedAt']) ? $data['directoryCheckedAt'] : null;
        $this->ereportingPaymentId = isset($data['ereportingPaymentId']) ? $data['ereportingPaymentId'] : null;
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
