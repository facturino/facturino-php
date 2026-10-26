<?php

declare(strict_types=1);
namespace Facturino\Model;

/** Read-only projection. jsonSerialize preserves absent, null and unknown fields. */
final class EReporting implements \JsonSerializable
{
    public readonly array $rawData;
    public readonly ?string $id;
    public readonly ?string $object;
    public readonly ?string $status;
    /**
     * Open state string. Known values: reserved, submitting, queued, submitted,
     * acknowledged, accepted, rejected, blocked, skipped, reconciliation_required.
     * skipped closes locally without transmission; blocked leaves the obligation due.
     */
    public readonly ?string $state;
    /**
     * Open reason string; null or absent on historical responses.
     * nothing_to_declare means no payment data is expected for operations outside
     * French VAT scope: state is skipped and obligation.state is completed.
     */
    public readonly ?string $skippedReason;
    public readonly ?string $type;
    public readonly ?string $period;
    public readonly ?string $periodStart;
    public readonly ?string $periodEnd;
    /**
     * Frozen transmission basis; null when none applies or history is unresolved.
     *
     * @var 'legal'|'voluntary_early_adoption'|null
     */
    public readonly ?string $obligationBasis;
    /** Seller's legal wave opening date (YYYY-MM-DD), frozen at reservation; nullable. */
    public readonly ?string $obligationDueFrom;
    /**
     * Who acknowledged receipt, independently of acceptance; null without a known acknowledgement.
     *
     * @var 'administration'|'platform'|null
     */
    public readonly ?string $acknowledgedBy;
    public readonly ?string $paRejectionCode;
    public readonly ?string $paRejectionReason;
    public readonly ?string $blockedReason;
    public readonly ?string $reconciliationReason;
    public readonly ?string $supersedesDeclarationId;
    public readonly ?string $supersededByDeclarationId;
    public readonly ?string $created;
    public readonly ?string $updated;
    public readonly ?int $totalHT;
    public readonly ?int $totalTVA;
    public readonly ?int $totalTTC;
    public readonly ?int $attempt;
    public readonly ?array $lines;
    public readonly ?bool $livemode;
    public readonly ?ObligationFollowUp $obligation;

    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->id = $data['id'] ?? null;
        $this->object = $data['object'] ?? null;
        $this->status = $data['status'] ?? null;
        $this->state = $data['state'] ?? null;
        $this->skippedReason = $data['skippedReason'] ?? null;
        $this->type = $data['type'] ?? null;
        $this->period = $data['period'] ?? null;
        $this->periodStart = $data['periodStart'] ?? null;
        $this->periodEnd = $data['periodEnd'] ?? null;
        $this->obligationBasis = $data['obligationBasis'] ?? null;
        $this->obligationDueFrom = $data['obligationDueFrom'] ?? null;
        $this->acknowledgedBy = $data['acknowledgedBy'] ?? null;
        $this->paRejectionCode = $data['paRejectionCode'] ?? null;
        $this->paRejectionReason = $data['paRejectionReason'] ?? null;
        $this->blockedReason = $data['blockedReason'] ?? null;
        $this->reconciliationReason = $data['reconciliationReason'] ?? null;
        $this->supersedesDeclarationId = $data['supersedesDeclarationId'] ?? null;
        $this->supersededByDeclarationId = $data['supersededByDeclarationId'] ?? null;
        $this->created = $data['created'] ?? null;
        $this->updated = $data['updated'] ?? null;
        $this->totalHT = $data['totalHT'] ?? null;
        $this->totalTVA = $data['totalTVA'] ?? null;
        $this->totalTTC = $data['totalTTC'] ?? null;
        $this->attempt = $data['attempt'] ?? null;
        $this->lines = $data['lines'] ?? null;
        $this->livemode = $data['livemode'] ?? null;
        $this->obligation = isset($data['obligation']) ? ObligationFollowUp::fromArray($data['obligation']) : null;
    }
    public static function fromArray(array $data): self { return new self($data); }
    public function jsonSerialize(): array { return $this->rawData; }
}
