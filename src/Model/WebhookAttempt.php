<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 */
final class WebhookAttempt implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    public readonly ?string $timestamp;
    public readonly ?int $httpStatus;
    public readonly ?string $error;
    public readonly ?float $duration;
    public readonly ?string $endpointId;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->timestamp = isset($data['timestamp']) ? $data['timestamp'] : null;
        $this->httpStatus = isset($data['httpStatus']) ? $data['httpStatus'] : null;
        $this->error = isset($data['error']) ? $data['error'] : null;
        $this->duration = isset($data['duration']) ? (float) $data['duration'] : null;
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
