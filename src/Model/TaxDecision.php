<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 */
final class TaxDecision implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    public readonly ?array $warnings;
    public readonly ?string $id;
    public readonly ?string $object;
    public readonly ?string $taxSource;
    public readonly ?string $companyId;
    public readonly ?string $status;
    public readonly ?string $customerId;
    public readonly ?array $customer;
    public readonly ?string $sellerProfileId;
    public readonly ?int $sellerProfileRevision;
    public readonly ?array $sellerProfile;
    public readonly ?string $currency;
    public readonly ?string $priceMode;
    public readonly ?string $effectiveAt;
    public readonly ?string $decidedAt;
    public readonly ?string $expiresAt;
    public readonly ?string $checkoutValidityPolicy;
    public readonly ?bool $expired;
    public readonly ?string $rulesVersion;
    public readonly ?string $reportingCalendar;
    public readonly ?string $roundingPolicy;
    public readonly ?string $requestFingerprint;
    public readonly ?string $operationFingerprint;
    public readonly ?array $lines;
    public readonly ?array $totals;
    public readonly ?array $vatBreakdown;
    public readonly ?int $amountToCharge;
    public readonly ?string $invoiceChannel;
    public readonly ?string $transactionReporting;
    public readonly ?string $paymentReporting;
    public readonly ?array $settledObligations;
    public readonly ?array $euB2cDestination;
    public readonly ?bool $foreignTaxReviewRequired;
    public readonly ?array $vies;
    public readonly ?array $locationEvidence;
    public readonly mixed $nonEuBusinessEvidence;
    public readonly ?array $issues;
    public readonly ?array $obligationReasons;
    public readonly ?string $retryOfTaxDecisionId;
    public readonly ?bool $livemode;
    public readonly ?string $created;
    public readonly ?string $updated;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->warnings = isset($data['warnings']) ? array_map(static fn (array $item): BuyerNatureWarning => BuyerNatureWarning::fromArray($item), $data['warnings']) : null;
        $this->id = isset($data['id']) ? $data['id'] : null;
        $this->object = isset($data['object']) ? $data['object'] : null;
        $this->taxSource = isset($data['taxSource']) ? $data['taxSource'] : null;
        $this->companyId = isset($data['companyId']) ? $data['companyId'] : null;
        $this->status = isset($data['status']) ? $data['status'] : null;
        $this->customerId = isset($data['customerId']) ? $data['customerId'] : null;
        $this->customer = isset($data['customer']) ? $data['customer'] : null;
        $this->sellerProfileId = isset($data['sellerProfileId']) ? $data['sellerProfileId'] : null;
        $this->sellerProfileRevision = isset($data['sellerProfileRevision']) ? $data['sellerProfileRevision'] : null;
        $this->sellerProfile = isset($data['sellerProfile']) ? $data['sellerProfile'] : null;
        $this->currency = isset($data['currency']) ? $data['currency'] : null;
        $this->priceMode = isset($data['priceMode']) ? $data['priceMode'] : null;
        $this->effectiveAt = isset($data['effectiveAt']) ? $data['effectiveAt'] : null;
        $this->decidedAt = isset($data['decidedAt']) ? $data['decidedAt'] : null;
        $this->expiresAt = isset($data['expiresAt']) ? $data['expiresAt'] : null;
        $this->checkoutValidityPolicy = isset($data['checkoutValidityPolicy']) ? $data['checkoutValidityPolicy'] : null;
        $this->expired = isset($data['expired']) ? $data['expired'] : null;
        $this->rulesVersion = isset($data['rulesVersion']) ? $data['rulesVersion'] : null;
        $this->reportingCalendar = isset($data['reportingCalendar']) ? $data['reportingCalendar'] : null;
        $this->roundingPolicy = isset($data['roundingPolicy']) ? $data['roundingPolicy'] : null;
        $this->requestFingerprint = isset($data['requestFingerprint']) ? $data['requestFingerprint'] : null;
        $this->operationFingerprint = isset($data['operationFingerprint']) ? $data['operationFingerprint'] : null;
        $this->lines = isset($data['lines']) ? $data['lines'] : null;
        $this->totals = isset($data['totals']) ? $data['totals'] : null;
        $this->vatBreakdown = isset($data['vatBreakdown']) ? $data['vatBreakdown'] : null;
        $this->amountToCharge = isset($data['amountToCharge']) ? $data['amountToCharge'] : null;
        $this->invoiceChannel = isset($data['invoiceChannel']) ? $data['invoiceChannel'] : null;
        $this->transactionReporting = isset($data['transactionReporting']) ? $data['transactionReporting'] : null;
        $this->paymentReporting = isset($data['paymentReporting']) ? $data['paymentReporting'] : null;
        $this->settledObligations = isset($data['settledObligations']) ? $data['settledObligations'] : null;
        $this->euB2cDestination = isset($data['euB2cDestination']) ? $data['euB2cDestination'] : null;
        $this->foreignTaxReviewRequired = isset($data['foreignTaxReviewRequired']) ? $data['foreignTaxReviewRequired'] : null;
        $this->vies = isset($data['vies']) ? $data['vies'] : null;
        $this->locationEvidence = isset($data['locationEvidence']) ? $data['locationEvidence'] : null;
        $this->nonEuBusinessEvidence = isset($data['nonEuBusinessEvidence']) ? $data['nonEuBusinessEvidence'] : null;
        $this->issues = isset($data['issues']) ? $data['issues'] : null;
        $this->obligationReasons = isset($data['obligationReasons']) ? $data['obligationReasons'] : null;
        $this->retryOfTaxDecisionId = isset($data['retryOfTaxDecisionId']) ? $data['retryOfTaxDecisionId'] : null;
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
