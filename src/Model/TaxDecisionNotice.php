<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 *
 * What the decision retained and what the seller can do next. Notices never
 * stand for a missing fact; issues say what prevents a final decision.
 * Show notices to the seller and act only on a `final` decision.
 */
final class TaxDecisionNotice implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    /**
     * Open stable code. Examples: `business_status_not_evidenced` only within
     * art. 18(2), second subparagraph, of Implementing Regulation (EU) 282/2011:
     * telecommunications, broadcasting and electronically supplied services to
     * an EU VAT territory buyer (Monaco included) who communicated no VAT number;
     * INSEE categories 7, 8 and 9 without taxStatus keep their refusal.
     * `location_evidence_contradictory` describes a territory retained by two
     * concordant items of different kinds or by the same complete tax result
     * on every possible territory. `destination_mechanism_missing` means
     * destination VAT is due and invoiced, with registration guidance.
     * A VAT number can be set aside when national registration establishes status.
     * `com_taaf_local_tax_not_determined`,
     * `com_taaf_local_regime_not_sourced`,
     * `com_taaf_payment_withholding_not_modelled`,
     * `eu_special_territory_not_implemented` and
     * `non_taxable_legal_person_goods_regime_not_modelled` describe unmodelled
     * cases under integration: final with foreignTaxReviewRequired, and the
     * integrator declares and assumes the tax. Facturino refuses unmodelled cases.
     * Saint-Martin TGCA follows its known rule under both sources; missing
     * registration and exemption leaves com_taaf_supplier_local_tax_liable pending.
     * `supply_category_not_declared` means an undeclared quote/draft service
     * line gave the same complete result as services and electronically supplied
     * services: final, with category services. A different result leaves a
     * pending issue of the same code, category null and no amounts instead.
     * Treat an unknown code as a generic notice.
     */
    public readonly ?string $code;
    /**
     * A more precise, equally stable reason, when the code alone does not say what
     * to do. On `destination_mechanism_missing`: `threshold_exceeded` (the
     * one-stop-shop registration period runs from the first operation above the
     * threshold), `destination_option` or `multi_member_state_establishment` (from
     * the first operation taxed at destination). On `vat_number_territory_mismatch`
     * or `eu_b2b_vat_number_not_validated`: `vat_number_ignored_national_registration`
     * (number set aside because the national registration establishes the business
     * status: the buyer stays a business, with no VAT number on the document).
     * `concordant_evidence` and `same_treatment` explain a retained territory.
     * `french_vat_and_tgca_cumulation` on com_taaf_local_regime_not_sourced
     * describes a general service to a Saint-Martin consumer without
     * localTaxExemption under integration. Open string; null when absent.
     */
    public readonly ?string $detailCode;
    /** Diagnostic message in English, free of personal data. */
    public readonly ?string $message;
    /** Normative reference of the retained result. */
    public readonly ?string $reference;
    /** Reference of the line concerned; null when the notice covers the whole operation. */
    public readonly ?string $lineId;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->code = isset($data['code']) ? $data['code'] : null;
        $this->detailCode = isset($data['detailCode']) ? $data['detailCode'] : null;
        $this->message = isset($data['message']) ? $data['message'] : null;
        $this->reference = isset($data['reference']) ? $data['reference'] : null;
        $this->lineId = isset($data['lineId']) ? $data['lineId'] : null;
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
