<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 */
final class InvoiceSubmissionArtefact implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    public readonly ?string $kind;
    public readonly ?string $path;
    public readonly ?string $generatedAt;
    public readonly ?array $correctedRules;
    public readonly ?string $routingIdentifier;
    public readonly ?string $sellerRoutingIdentifier;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->sellerRoutingIdentifier = $data['sellerRoutingIdentifier'] ?? null;
        $this->kind = isset($data['kind']) ? $data['kind'] : null;
        $this->path = isset($data['path']) ? $data['path'] : null;
        $this->generatedAt = isset($data['generatedAt']) ? $data['generatedAt'] : null;
        $this->correctedRules = isset($data['correctedRules']) ? $data['correctedRules'] : null;
        $this->routingIdentifier = isset($data['routingIdentifier']) ? $data['routingIdentifier'] : null;
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
