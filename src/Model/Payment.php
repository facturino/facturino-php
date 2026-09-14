<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 */
final class Payment implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    public readonly ?ObligationFollowUp $reportingFollowUp;
    public readonly ?string $id;
    public readonly ?string $object;
    public readonly ?string $type;
    public readonly ?string $status;
    public readonly ?int $amount;
    public readonly ?string $method;
    public readonly ?string $reference;
    public readonly ?string $paidAt;
    public readonly ?string $recorded_by;
    public readonly ?string $created;
    public readonly ?PaymentCollectionStatus $fr212;

    public readonly ?string $companyId;
    public readonly ?string $invoiceId;
    public readonly ?bool $livemode;
    public readonly ?string $creditNoteId;
    public readonly ?string $cancelledAt;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->reportingFollowUp = isset($data['reportingFollowUp']) ? ObligationFollowUp::fromArray($data['reportingFollowUp']) : null;
        $this->companyId = $data['companyId'] ?? null;
        $this->invoiceId = $data['invoiceId'] ?? null;
        $this->livemode = $data['livemode'] ?? null;
        $this->creditNoteId = $data['creditNoteId'] ?? null;
        $this->cancelledAt = $data['cancelledAt'] ?? null;

        $this->id = isset($data['id']) ? $data['id'] : null;
        $this->object = isset($data['object']) ? $data['object'] : null;
        $this->type = isset($data['type']) ? $data['type'] : null;
        $this->status = isset($data['status']) ? $data['status'] : null;
        $this->amount = isset($data['amount']) ? $data['amount'] : null;
        $this->method = isset($data['method']) ? $data['method'] : null;
        $this->reference = isset($data['reference']) ? $data['reference'] : null;
        $this->paidAt = isset($data['paidAt']) ? $data['paidAt'] : null;
        $this->recorded_by = isset($data['recorded_by']) ? $data['recorded_by'] : null;
        $this->created = isset($data['created']) ? $data['created'] : null;
        $this->fr212 = isset($data['fr212']) ? PaymentCollectionStatus::fromArray($data['fr212']) : null;
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
