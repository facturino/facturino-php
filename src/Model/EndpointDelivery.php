<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 */
final class EndpointDelivery implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    public readonly ?int $attemptCount;
    public readonly ?int $generation;
    public readonly ?bool $delivered;
    public readonly ?array $attempts;
    public readonly ?string $nextRetry;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->attemptCount = $data['attemptCount'] ?? null;
        $this->generation = $data['generation'] ?? null;
        $this->delivered = isset($data['delivered']) ? $data['delivered'] : null;
        $this->attempts = isset($data['attempts']) ? array_map(static fn (array $item): WebhookAttempt => WebhookAttempt::fromArray($item), $data['attempts']) : null;
        $this->nextRetry = isset($data['nextRetry']) ? $data['nextRetry'] : null;
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
