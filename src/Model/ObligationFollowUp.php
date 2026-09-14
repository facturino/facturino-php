<?php

declare(strict_types=1);
namespace Facturino\Model;

/** Read-only projection. jsonSerialize preserves absent, null and unknown fields. */
final class ObligationFollowUp implements \JsonSerializable
{
    public readonly array $rawData;
    public readonly ?string $state;
    public readonly ?string $reasonCode;
    public readonly ?string $reasonSource;
    public readonly ?string $owner;
    public readonly ?string $action;
    public readonly ?string $nextAttemptAt;

    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->state = $data['state'] ?? null;
        $this->reasonCode = $data['reasonCode'] ?? null;
        $this->reasonSource = $data['reasonSource'] ?? null;
        $this->owner = $data['owner'] ?? null;
        $this->action = $data['action'] ?? null;
        $this->nextAttemptAt = $data['nextAttemptAt'] ?? null;
    }
    public static function fromArray(array $data): self { return new self($data); }
    public function jsonSerialize(): array { return $this->rawData; }
}
