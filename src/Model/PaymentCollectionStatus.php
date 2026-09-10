<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 */
final class PaymentCollectionStatus implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    public readonly ?string $state;
    public readonly ?string $sentAt;
    public readonly ?string $lastErrorCode;
    public readonly ?string $lastErrorReason;
    public readonly ?string $updatedAt;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->state = isset($data['state']) ? $data['state'] : null;
        $this->sentAt = isset($data['sentAt']) ? $data['sentAt'] : null;
        $this->lastErrorCode = isset($data['lastErrorCode']) ? $data['lastErrorCode'] : null;
        $this->lastErrorReason = isset($data['lastErrorReason']) ? $data['lastErrorReason'] : null;
        $this->updatedAt = isset($data['updatedAt']) ? $data['updatedAt'] : null;
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
