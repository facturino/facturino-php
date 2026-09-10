<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 */
final class CreditNote implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    public readonly ?string $id;
    public readonly ?string $object;
    public readonly ?string $status;
    public readonly ?string $number;
    public readonly ?string $relatedInvoiceId;
    public readonly ?string $relatedInvoiceNumber;
    public readonly ?string $creditNoteType;
    public readonly ?string $reasonCode;
    public readonly ?array $items;
    public readonly ?array $totals;
    public readonly ?bool $livemode;
    public readonly ?string $created;
    public readonly ?string $originalInvoiceId;
    public readonly ?string $originalTaxDecisionId;
    public readonly ?string $taxSource;
    public readonly mixed $taxSnapshot;
    public readonly ?string $documentStatus;
    public readonly ?string $transmissionStatus;
    public readonly ?string $transmissionDetail;
    public readonly ?string $paymentStatus;
    public readonly ?CreditNoteEinvoicing $einvoicing;

    public readonly ?string $companyId;
    public readonly ?array $customer;
    public readonly ?string $currency;
    public readonly ?string $reason;
    public readonly ?array $dates;
    public readonly ?array $archive;
    public readonly ?array $lifecycle;
    public readonly ?bool $processed;
    public readonly ?string $updated;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->companyId = $data['companyId'] ?? null;
        $this->customer = $data['customer'] ?? null;
        $this->currency = $data['currency'] ?? null;
        $this->reason = $data['reason'] ?? null;
        $this->dates = $data['dates'] ?? null;
        $this->archive = $data['archive'] ?? null;
        $this->lifecycle = $data['lifecycle'] ?? null;
        $this->processed = $data['processed'] ?? null;
        $this->updated = $data['updated'] ?? null;

        $this->id = isset($data['id']) ? $data['id'] : null;
        $this->object = isset($data['object']) ? $data['object'] : null;
        $this->status = isset($data['status']) ? $data['status'] : null;
        $this->number = isset($data['number']) ? $data['number'] : null;
        $this->relatedInvoiceId = isset($data['relatedInvoiceId']) ? $data['relatedInvoiceId'] : null;
        $this->relatedInvoiceNumber = isset($data['relatedInvoiceNumber']) ? $data['relatedInvoiceNumber'] : null;
        $this->creditNoteType = isset($data['creditNoteType']) ? $data['creditNoteType'] : null;
        $this->reasonCode = isset($data['reasonCode']) ? $data['reasonCode'] : null;
        $this->items = isset($data['items']) ? $data['items'] : null;
        $this->totals = isset($data['totals']) ? $data['totals'] : null;
        $this->livemode = isset($data['livemode']) ? $data['livemode'] : null;
        $this->created = isset($data['created']) ? $data['created'] : null;
        $this->originalInvoiceId = isset($data['originalInvoiceId']) ? $data['originalInvoiceId'] : null;
        $this->originalTaxDecisionId = isset($data['originalTaxDecisionId']) ? $data['originalTaxDecisionId'] : null;
        $this->taxSource = isset($data['taxSource']) ? $data['taxSource'] : null;
        $this->taxSnapshot = isset($data['taxSnapshot']) ? $data['taxSnapshot'] : null;
        $this->documentStatus = isset($data['documentStatus']) ? $data['documentStatus'] : null;
        $this->transmissionStatus = isset($data['transmissionStatus']) ? $data['transmissionStatus'] : null;
        $this->transmissionDetail = isset($data['transmissionDetail']) ? $data['transmissionDetail'] : null;
        $this->paymentStatus = isset($data['paymentStatus']) ? $data['paymentStatus'] : null;
        $this->einvoicing = isset($data['einvoicing']) ? CreditNoteEinvoicing::fromArray($data['einvoicing']) : null;
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
