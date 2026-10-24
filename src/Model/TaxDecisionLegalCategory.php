<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * SIRENE consultation frozen with a tax decision, present only when one was due.
 * Missing historical fields stay null; jsonSerialize() preserves the original
 * payload, including absent/null distinctions and unknown fields.
 */
final class TaxDecisionLegalCategory implements \JsonSerializable
{
    public readonly array $rawData;
    /** Registry source (`sirene` today). */
    public readonly ?string $source;
    /** Consulted SIREN, nine digits. */
    public readonly ?string $siren;
    /**
     * Open string: `found`, `not_found` (SIREN absent from the registry) or
     * `unavailable` (registry unreachable). The latter two leave the decision
     * pending with legal_category_unresolved; neither establishes taxable status.
     */
    public readonly ?string $status;
    /** Open INSEE legal category code, or null when the registry gave none. */
    public readonly ?string $legalCategoryCode;
    /** Civil date (YYYY-MM-DD) of the consultation or reused server cache result. */
    public readonly ?string $checkedAt;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->source = isset($data['source']) ? $data['source'] : null;
        $this->siren = isset($data['siren']) ? $data['siren'] : null;
        $this->status = isset($data['status']) ? $data['status'] : null;
        $this->legalCategoryCode = isset($data['legalCategoryCode']) ? $data['legalCategoryCode'] : null;
        $this->checkedAt = isset($data['checkedAt']) ? $data['checkedAt'] : null;
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
