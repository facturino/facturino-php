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
    /**
     * Only `final` carries amounts. Missing facts leave `pending_verification`
     * with the fact and remedy in issues; an unmodelled case is `unsupported`
     * under `facturino`, or `final` under `integration` with a notice and
     * foreignTaxReviewRequired. Both sources refuse mixed category O documents
     * and an EU address missing the postal code needed to split VAT territories.
     * Act only on `final`; status values remain open strings.
     */
    public readonly ?string $status;
    public readonly ?string $customerId;
    /**
     * Buyer identity and qualification, frozen with the decision.
     *
     * A business buyer without evidence stays pending. Only art. 18(2), second
     * subparagraph, of Implementing Regulation (EU) 282/2011 permits `consumer`
     * with `natureBasis` `declared_business_not_evidenced` and a notice:
     * telecommunications, broadcasting and electronically supplied services to
     * an EU VAT territory buyer (Monaco included) who communicated no VAT number.
     * A communicated but unvalidated number leaves
     * `eu_b2b_vat_number_not_validated` pending; INSEE categories 7, 8 and 9
     * without `taxStatus` keep their refusal.
     * `declared_non_taxable` means `taxStatus: non_taxable`. A legal person with
     * a VAT number is checked through VIES: a valid number makes its services
     * follow taxable-person rules (CGI art. 259-0); an unreachable VIES leaves
     * `vies_unavailable` pending. Nature bases remain open strings.
     * `vatNumber` is the number as DECLARED on the customer record;
     * `retainedVatNumber` is the one the decision RETAINED, the only one the
     * document, its electronic invoice and the e-reporting carry. It is null
     * without a declared number, when the number is set aside because the national
     * registration establishes the taxable status, or when the buyer is treated as
     * a non-taxable person; it is absent on a decision taken before the field
     * existed, where the declared number stands for the retained one.
     *
     * @var array{customerId?: string, name?: string, nature?: string, natureBasis?: string, territoryId?: string, territoryKind?: string, declaredCountry?: string, declaredPostalCode?: ?string, legalRegistrationId?: ?string, vatNumber?: ?string, retainedVatNumber?: ?string, crossBorderTaxableStatus?: string, businessStatusBasis?: string}|null
     */
    public readonly ?array $customer;
    /** SIRENE consultation frozen with the decision; absent when none was due. */
    public readonly ?TaxDecisionLegalCategory $legalCategory;
    public readonly ?string $sellerProfileId;
    public readonly ?int $sellerProfileRevision;
    /**
     * Frozen seller profile. Optional local registrations carry a territory
     * (`MF` today), a reference and YYYY-MM-DD dates; effectiveTo may be null.
     * Territory is an open string so future values remain readable.
     *
     * @var array{localTaxRegistrations?: list<array{territory: string, reference: string, effectiveFrom: string, effectiveTo: ?string}>, ...}|null
     */
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
    /**
     * A line may carry a separate local tax. Its code (`tgca_mf` today) and
     * exemption are open strings; a null exemption means none applies.
     * Rates are integer centipercent and amounts are integer centimes.
     * Under both sources, a Saint-Martin TGCA registration in force gives 4%;
     * declared `cgism_253` or `not_used_in_saint_martin` gives 0 with the mention.
     * Without either, `com_taaf_supplier_local_tax_liable` stays pending.
     * `vatexCode` is an open string from the official CIUS-FR list (88 codes).
     * category is null only on a pending quote/draft line with issue
     * supply_category_not_declared, without decided amounts. A service line
     * read as services and electronically_supplied_services with the same
     * complete result is final as services, with a notice of that code instead.
     * Direct POST /v1/tax-decisions requests still require category on every
     * input line under both sources. Historical decision categories are unchanged.
     *
     * @var list<array{category: ?string, vatexCode?: ?string, localTax?: array{code: string, rateCentipercent: int, amount: int, reference: string, exemption: ?string}, ...}>|null
     */
    public readonly ?array $lines;
    /**
     * Integer centimes. totalLocalTax is optional, including on older decisions;
     * preserve its absence rather than manufacturing a zero. When present it
     * is included in totalTTC and amountToCharge, separately from French VAT.
     *
     * @var array{totalHT?: int, totalVAT?: int, totalLocalTax?: int, totalTTC?: int, ...}|null
     */
    public readonly ?array $totals;
    public readonly ?array $vatBreakdown;
    public readonly ?int $amountToCharge;
    public readonly ?string $invoiceChannel;
    public readonly ?string $transactionReporting;
    public readonly ?string $paymentReporting;
    /**
     * Frozen transmission basis; null when none applies or history is unresolved.
     *
     * @var 'legal'|'voluntary_early_adoption'|null
     */
    public readonly ?string $obligationBasis;
    public readonly ?array $settledObligations;
    public readonly ?array $euB2cDestination;
    public readonly ?bool $foreignTaxReviewRequired;
    public readonly ?array $vies;
    public readonly ?array $locationEvidence;
    public readonly mixed $nonEuBusinessEvidence;
    /**
     * Why the decision is NOT final; empty on a final decision. Each entry has a
     * stable `code` and, when the code alone does not say what to complete, a
     * `detailCode` (`einvoicing_buyer_registration_required`: the electronic
     * invoice requires the buyer's SIREN, CGI art. 242 nonies A;
     * `tax_status_not_declared`: INSEE category 7, 8 or 9 without taxStatus;
     * `legal_category_unresolved`: SIRENE could not give the legal category;
     * `french_vat_and_tgca_cumulation`: general service to a Saint-Martin consumer
     * without localTaxExemption, unsupported under facturino), a normative
     * `reference` and the `lineId` concerned (absent when the reason covers the
     * whole operation).
     * Missing buyer status, VAT validation, location evidence, the EUR 10,000
     * threshold or goods movement leaves pending_verification, without amounts.
     * supply_category_not_declared means a quote/draft line needs its fiscal
     * nature declared; that pending line has category: null and no amounts.
     * `vat_category_o_split_required` is unsupported under both sources: issue
     * two documents. `non_taxable_legal_person_goods_regime_not_modelled` covers
     * goods dispatched to another member state or XI for a non-taxable legal
     * person that communicated a VAT number: unsupported under facturino,
     * final with a notice under integration. Codes and detail codes stay open.
     *
     * @var list<array{code: string, detailCode?: string, message?: string, reference?: string, lineId?: string}>|null
     */
    public readonly ?array $issues;
    /**
     * What the decision retained and what the seller can do next. Always
     * present on a response, empty when there is nothing to report. Notices
     * never stand for a missing fact: show them to the seller, and act only
     * on a `final` decision. Issues explain missing facts and refusals.
     *
     * @var list<TaxDecisionNotice>|null
     */
    public readonly ?array $notices;
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
        $this->legalCategory = isset($data['legalCategory']) ? TaxDecisionLegalCategory::fromArray($data['legalCategory']) : null;
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
        $this->obligationBasis = isset($data['obligationBasis']) ? $data['obligationBasis'] : null;
        $this->settledObligations = isset($data['settledObligations']) ? $data['settledObligations'] : null;
        $this->euB2cDestination = isset($data['euB2cDestination']) ? $data['euB2cDestination'] : null;
        $this->foreignTaxReviewRequired = isset($data['foreignTaxReviewRequired']) ? $data['foreignTaxReviewRequired'] : null;
        $this->vies = isset($data['vies']) ? $data['vies'] : null;
        $this->locationEvidence = isset($data['locationEvidence']) ? $data['locationEvidence'] : null;
        $this->nonEuBusinessEvidence = isset($data['nonEuBusinessEvidence']) ? $data['nonEuBusinessEvidence'] : null;
        $this->issues = isset($data['issues']) ? $data['issues'] : null;
        $this->notices = isset($data['notices']) ? array_map(static fn (array $item): TaxDecisionNotice => TaxDecisionNotice::fromArray($item), $data['notices']) : null;
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
