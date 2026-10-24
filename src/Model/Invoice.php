<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 */
final class Invoice implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    public readonly ?EReportingBlock $ereportingBlock;
    public readonly ?string $id;
    public readonly ?string $object;
    public readonly ?string $type;
    public readonly ?string $status;
    public readonly ?string $number;
    public readonly ?string $currency;
    public readonly ?array $customer;
    /**
     * Optional local tax frozen with each invoice item, separate from VAT.
     * Code (`tgca_mf` today) and exemption are open strings. Rates are integer
     * centipercent; amounts are integer centimes. An exemption may be null.
     * Saint-Martin TGCA is 4% with a registration in force, or 0 with a
     * declared exemption and its mention, under both tax sources.
     * vatexCode stays an open string; issued documents keep their issued code.
     * supplyCategory is an optional open-string declaration copied from quote
     * lines on conversion. Preserve an omission on historical items.
     *
     * @var list<array{supplyCategory?: string, vatexCode?: ?string, localTax?: array{code: string, rateCentipercent: int, amount: int, reference: string, exemption: ?string}, ...}>|null
     */
    public readonly ?array $items;
    /**
     * Integer centimes; totalLocalTax is included in totalTTC and the amount
     * to collect. It is optional on historical responses.
     *
     * @var array{totalHT?: int, totalVAT?: int, totalLocalTax?: int, totalTTC?: int, amountDue?: int, amountPaid?: int, ...}|null
     */
    public readonly ?array $totals;
    public readonly ?array $dates;
    public readonly ?array $deposits;
    public readonly ?array $paymentSchedule;
    public readonly ?string $notes;
    public readonly ?bool $livemode;
    public readonly ?string $created;
    public readonly ?string $updated;
    public readonly ?string $taxDecisionId;
    public readonly ?string $taxSource;
    /**
     * Undecided commercial lines, including any supplyCategory copied from a
     * quote. An absent declaration stays absent; its indicative VAT decides none.
     *
     * @var array{lines?: list<array{kind?: 'goods'|'service', supplyCategory?: string, ...}>, ...}|null
     */
    public readonly ?array $commercialDraft;
    /**
     * Frozen fiscal position copied from the decision. Its `notices` key holds
     * the decision's notices frozen with it, in the shape of the entries of
     * `TaxDecision::$notices`: what was retained and what the seller can do next.
     * Empty when there is nothing to report; older snapshots may omit the key.
     */
    public readonly mixed $taxSnapshot;
    public readonly ?string $documentStatus;
    public readonly ?string $transmissionStatus;
    public readonly ?string $transmissionDetail;
    public readonly ?string $paymentStatus;
    public readonly ?InvoiceEinvoicing $einvoicing;
    public readonly ?array $files;
    public readonly ?array $lifecycle;

    public readonly ?array $paymentInfo;
    public readonly ?array $archive;
    public readonly ?array $portal;
    public readonly ?string $legalMentions;
    public readonly ?string $purchaseOrderNumber;
    public readonly ?array $reminderTaskIds;
    public readonly ?bool $processed;
    public readonly ?bool $anonymized;
    public readonly ?string $anonymizedAt;
    public readonly ?array $metadata;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->ereportingBlock = isset($data['ereportingBlock']) ? EReportingBlock::fromArray($data['ereportingBlock']) : null;
        $this->paymentInfo = $data['paymentInfo'] ?? null;
        $this->archive = $data['archive'] ?? null;
        $this->portal = $data['portal'] ?? null;
        $this->legalMentions = $data['legalMentions'] ?? null;
        $this->purchaseOrderNumber = $data['purchaseOrderNumber'] ?? null;
        $this->reminderTaskIds = $data['reminderTaskIds'] ?? null;
        $this->processed = $data['processed'] ?? null;
        $this->anonymized = $data['anonymized'] ?? null;
        $this->anonymizedAt = $data['anonymizedAt'] ?? null;
        $this->metadata = $data['metadata'] ?? null;

        $this->id = isset($data['id']) ? $data['id'] : null;
        $this->object = isset($data['object']) ? $data['object'] : null;
        $this->type = isset($data['type']) ? $data['type'] : null;
        $this->status = isset($data['status']) ? $data['status'] : null;
        $this->number = isset($data['number']) ? $data['number'] : null;
        $this->currency = isset($data['currency']) ? $data['currency'] : null;
        $this->customer = isset($data['customer']) ? $data['customer'] : null;
        $this->items = isset($data['items']) ? $data['items'] : null;
        $this->totals = isset($data['totals']) ? $data['totals'] : null;
        $this->dates = isset($data['dates']) ? $data['dates'] : null;
        $this->deposits = isset($data['deposits']) ? $data['deposits'] : null;
        $this->paymentSchedule = isset($data['paymentSchedule']) ? $data['paymentSchedule'] : null;
        $this->notes = isset($data['notes']) ? $data['notes'] : null;
        $this->livemode = isset($data['livemode']) ? $data['livemode'] : null;
        $this->created = isset($data['created']) ? $data['created'] : null;
        $this->updated = isset($data['updated']) ? $data['updated'] : null;
        $this->taxDecisionId = isset($data['taxDecisionId']) ? $data['taxDecisionId'] : null;
        $this->taxSource = isset($data['taxSource']) ? $data['taxSource'] : null;
        $this->commercialDraft = isset($data['commercialDraft']) ? $data['commercialDraft'] : null;
        $this->taxSnapshot = isset($data['taxSnapshot']) ? $data['taxSnapshot'] : null;
        $this->documentStatus = isset($data['documentStatus']) ? $data['documentStatus'] : null;
        $this->transmissionStatus = isset($data['transmissionStatus']) ? $data['transmissionStatus'] : null;
        $this->transmissionDetail = isset($data['transmissionDetail']) ? $data['transmissionDetail'] : null;
        $this->paymentStatus = isset($data['paymentStatus']) ? $data['paymentStatus'] : null;
        $this->einvoicing = isset($data['einvoicing']) ? InvoiceEinvoicing::fromArray($data['einvoicing']) : null;
        $this->files = isset($data['files']) ? $data['files'] : null;
        $this->lifecycle = isset($data['lifecycle']) ? array_map(static fn (array $item): LifecycleEntry => LifecycleEntry::fromArray($item), $data['lifecycle']) : null;
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
