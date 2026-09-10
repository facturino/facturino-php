<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 */
final class LifecycleEntry implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    public readonly ?string $status;
    public readonly ?string $timestamp;
    public readonly ?string $source;
    public readonly ?string $details;
    public readonly ?string $code;
    public readonly ?array $params;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->status = isset($data['status']) ? $data['status'] : null;
        $this->timestamp = isset($data['timestamp']) ? $data['timestamp'] : null;
        $this->source = isset($data['source']) ? $data['source'] : null;
        $this->details = isset($data['details']) ? $data['details'] : null;
        $this->code = isset($data['code']) ? $data['code'] : null;
        $this->params = isset($data['params']) ? $data['params'] : null;
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
