<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 */
final class EventRetryResult implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    public readonly ?string $id;
    public readonly ?string $object;
    public readonly ?bool $retryScheduled;
    public readonly ?string $endpointId;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->id = isset($data['id']) ? $data['id'] : null;
        $this->object = isset($data['object']) ? $data['object'] : null;
        $this->retryScheduled = isset($data['retryScheduled']) ? $data['retryScheduled'] : null;
        $this->endpointId = isset($data['endpointId']) ? $data['endpointId'] : null;
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
