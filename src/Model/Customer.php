<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 */
final class Customer implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    public readonly ?string $id;
    public readonly ?string $object;
    public readonly ?array $warnings;
    public readonly ?string $name;
    public readonly ?string $type;
    public readonly ?string $siret;
    public readonly ?string $vatNumber;
    public readonly ?array $legalForm;
    public readonly ?array $naf;
    public readonly ?array $address;
    public readonly ?array $deliveryAddress;
    public readonly ?array $contacts;
    public readonly ?bool $active;
    public readonly ?bool $livemode;
    public readonly ?string $created;
    public readonly ?string $updated;

    public readonly ?string $companyId;
    public readonly ?int $balance;
    public readonly ?string $currency;
    public readonly ?bool $siretVerified;
    public readonly ?bool $vatVerified;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->companyId = $data['companyId'] ?? null;
        $this->balance = $data['balance'] ?? null;
        $this->currency = $data['currency'] ?? null;
        $this->siretVerified = $data['siretVerified'] ?? null;
        $this->vatVerified = $data['vatVerified'] ?? null;

        $this->id = isset($data['id']) ? $data['id'] : null;
        $this->object = isset($data['object']) ? $data['object'] : null;
        $this->warnings = isset($data['warnings']) ? array_map(static fn (array $item): BuyerNatureWarning => BuyerNatureWarning::fromArray($item), $data['warnings']) : null;
        $this->name = isset($data['name']) ? $data['name'] : null;
        $this->type = isset($data['type']) ? $data['type'] : null;
        $this->siret = isset($data['siret']) ? $data['siret'] : null;
        $this->vatNumber = isset($data['vatNumber']) ? $data['vatNumber'] : null;
        $this->legalForm = isset($data['legalForm']) ? $data['legalForm'] : null;
        $this->naf = isset($data['naf']) ? $data['naf'] : null;
        $this->address = isset($data['address']) ? $data['address'] : null;
        $this->deliveryAddress = isset($data['deliveryAddress']) ? $data['deliveryAddress'] : null;
        $this->contacts = isset($data['contacts']) ? $data['contacts'] : null;
        $this->active = isset($data['active']) ? $data['active'] : null;
        $this->livemode = isset($data['livemode']) ? $data['livemode'] : null;
        $this->created = isset($data['created']) ? $data['created'] : null;
        $this->updated = isset($data['updated']) ? $data['updated'] : null;
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
