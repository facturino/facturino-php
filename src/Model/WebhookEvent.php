<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 */
final class WebhookEvent implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    public readonly ?string $id;
    public readonly ?string $object;
    public readonly ?string $type;
    public readonly ?string $apiVersion;
    public readonly ?WebhookEventData $data;
    public readonly ?EventDataFamily $dataFamily;
    public readonly ?array $request;
    public readonly ?bool $livemode;
    public readonly ?string $created;
    public readonly ?string $updated;
    public readonly ?string $companyId;
    public readonly ?string $endpointId;
    public readonly ?bool $delivered;
    public readonly ?array $attempts;
    public readonly ?string $nextRetry;
    public readonly ?array $deliveries;
    public readonly ?string $expireAt;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->dataFamily = EventDataFamily::forEventType($data['type'] ?? '');
        $this->id = isset($data['id']) ? $data['id'] : null;
        $this->object = isset($data['object']) ? $data['object'] : null;
        $this->type = isset($data['type']) ? $data['type'] : null;
        $this->apiVersion = isset($data['apiVersion']) ? $data['apiVersion'] : null;
        $this->data = isset($data['data']) ? WebhookEventData::fromArray($data['data']) : null;
        $this->request = isset($data['request']) ? $data['request'] : null;
        $this->livemode = isset($data['livemode']) ? $data['livemode'] : null;
        $this->created = isset($data['created']) ? $data['created'] : null;
        $this->updated = isset($data['updated']) ? $data['updated'] : null;
        $this->companyId = isset($data['companyId']) ? $data['companyId'] : null;
        $this->endpointId = isset($data['endpointId']) ? $data['endpointId'] : null;
        $this->delivered = isset($data['delivered']) ? $data['delivered'] : null;
        $this->attempts = isset($data['attempts']) ? array_map(static fn (array $item): WebhookAttempt => WebhookAttempt::fromArray($item), $data['attempts']) : null;
        $this->nextRetry = isset($data['nextRetry']) ? $data['nextRetry'] : null;
        $this->deliveries = isset($data['deliveries']) ? $data['deliveries'] : null;
        $this->expireAt = isset($data['expireAt']) ? $data['expireAt'] : null;
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
