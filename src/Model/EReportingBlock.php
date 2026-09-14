<?php

declare(strict_types=1);
namespace Facturino\Model;

/** Read-only projection. jsonSerialize preserves absent, null and unknown fields. */
final class EReportingBlock implements \JsonSerializable
{
    public readonly array $rawData;
    public readonly ?string $volet;
    public readonly ?string $code;
    public readonly ?string $remedy;
    public readonly ?ObligationFollowUp $obligation;
    public readonly ?string $periodStart;
    public readonly ?string $periodEnd;
    public readonly ?string $updated;
    private function __construct(array $data) {
        $this->rawData = $data;
        $this->volet = $data['volet'] ?? null;
        $this->code = $data['code'] ?? null;
        $this->remedy = $data['remedy'] ?? null;
        $this->periodStart = $data['periodStart'] ?? null;
        $this->periodEnd = $data['periodEnd'] ?? null;
        $this->updated = $data['updated'] ?? null;
        $this->obligation = isset($data['obligation']) ? ObligationFollowUp::fromArray($data['obligation']) : null;
    }
    public static function fromArray(array $data): self { return new self($data); }
    public function jsonSerialize(): array { return $this->rawData; }
}
