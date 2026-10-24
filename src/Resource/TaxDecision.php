<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;

/**
 * Tax decisions — /v1/tax-decisions
 *
 * A decision is an immutable fiscal position. It fixes the VAT, the exact
 * amount to charge and the three reporting axes for ONE commercial operation,
 * then never changes. Ask for a decision BEFORE charging anything: the amount
 * to debit is `amountToCharge`, not a total computed locally.
 *
 * Only a `final` decision carries amounts. On `pending_verification` or
 * `unsupported`, `totals` and `amountToCharge` are `null` — never `0`: absent
 * is not "nothing to charge".
 *
 * A missing fact leaves `pending_verification`, with the fact and remedy in
 * `issues`: business_status_not_evidenced, eu_b2b_vat_number_not_validated,
 * non_eu_business_status_not_evidenced, vies_unavailable,
 * location_evidence_insufficient, location_evidence_contradictory,
 * location_evidence_relief_undetermined, eu_threshold_state_missing and
 * goods_movement_unknown. For business_status_not_evidenced, detailCode
 * tax_status_not_declared names INSEE categories 7, 8 and 9 without taxStatus;
 * legal_category_unresolved means SIRENE could not give the legal category.
 * Optional `legalCategory` freezes that consultation (source, siren, status,
 * nullable legalCategoryCode, checkedAt as YYYY-MM-DD). It is absent when none
 * was due; status and category code stay open strings.
 *
 * `notices` say what the decision retained and what the seller can do next.
 * Always present, empty when there is nothing to report. They never stand for
 * a missing fact. Examples: destination VAT invoiced without a mechanism
 * (destination_mechanism_missing, with registration guidance), a territory
 * retained by two concordant items of different kinds or the same complete
 * tax result on every possible territory, and a VAT number set aside because
 * national registration establishes status. Show notices to the seller and
 * act only on `final`; `issues` explains missing facts and refusals.
 *
 * Only art. 18(2), second subparagraph, of Implementing Regulation (EU) 282/2011
 * permits `natureBasis: declared_business_not_evidenced`: telecommunications,
 * broadcasting and electronically supplied services to a buyer established in
 * the EU VAT territory (Monaco included) who communicated no VAT number. The
 * buyer is treated as a non-taxable person, with a notice. A communicated but
 * unvalidated number leaves eu_b2b_vat_number_not_validated pending; INSEE
 * categories 7, 8 and 9 without taxStatus keep their refusal.
 * `declared_non_taxable` means taxStatus: non_taxable. A legal person carrying
 * a VAT number is checked through VIES: valid makes its services follow
 * taxable-person rules (CGI art. 259-0); unreachable leaves vies_unavailable
 * pending. Nature bases, codes and detail codes remain open strings.
 *
 * The resource is immutable by design: there is no update and no delete. To
 * re-decide the same operation after supplying missing evidence, create a new
 * decision with `retryOfTaxDecisionId`.
 *
 * Every decision states its `taxSource`:
 *
 * - `facturino` — Facturino determines the VAT from the commercial lines
 *   (category, rate category) and the territorial evidence;
 * - `integration` — the integration supplies the VAT of every line
 *   (`vatRate` in integer centipercent, `vatCode` among S, Z, E, AE, K, G, O,
 *   and where the rate is zero a `vatexCode` and the line's `placeOfSupply`).
 *   Facturino validates the coherence of what is supplied and refuses
 *   contradictions with `integration_vat_incoherent`; it never silently
 *   corrects a rate.
 *
 * Both sources produce the same decision object, feed the same reporting
 * obligations engine and back invoices the same way.
 *
 * `vatRate`, `vatCode` and `vatexCode` describe FRENCH VAT. Local taxes travel
 * separately in optional line `localTax` and `totals.totalLocalTax`; the
 * `integration` source never passes a local tax through the French VAT fields.
 * `localTax` carries code (an open string, `tgca_mf` today), rateCentipercent
 * (integer centipercent), amount (integer centimes), reference and exemption
 * (an open string or null). The total is integer centimes. Older responses may
 * omit these fields. totalLocalTax is included in totalTTC and amountToCharge.
 * vatexCode stays an open string from the official CIUS-FR list (88 codes).
 * Legacy VATEX-FR-261 and VATEX-FR-AUTOLIQ inputs are stored and returned as
 * VATEX-FR-CGI261-4 and VATEX-FR-AE; issued documents keep their issued code.
 *
 * `sellerProfile.localTaxRegistrations` is an optional list of registrations:
 * territory (an open string, `MF` today), reference, effectiveFrom (YYYY-MM-DD)
 * and effectiveTo (YYYY-MM-DD or null).
 *
 * French overseas collectivities and the TAAF (PM, BL, MF, PF, NC, WF, TF) are
 * not part of France for VAT (CGI art. 256-0). An operation located there is
 * outside French VAT: category `O` at 0. Cases settled by a collectivity's text
 * stay `final` under both sources: New Caledonia where the taxable buyer
 * accounts for the tax, Saint-Barthélemy, Saint-Pierre-et-Miquelon, and goods
 * shipped to Saint-Martin.
 *
 * Unmodelled cases are `unsupported` under `facturino`, or `final` under
 * `integration` with a notice carrying the same code and
 * `foreignTaxReviewRequired: true`; the integrator declares and assumes the tax:
 * com_taaf_local_regime_not_sourced, com_taaf_local_tax_not_determined and
 * com_taaf_payment_withholding_not_modelled. A general service to a Saint-Martin
 * consumer without localTaxExemption has com_taaf_local_regime_not_sourced and
 * detailCode french_vat_and_tgca_cumulation.
 *
 * Saint-Martin TGCA follows its known rule under BOTH sources: a registration
 * in force in sellerProfile.localTaxRegistrations gives 4% per line in localTax;
 * localTaxExemption cgism_253 or not_used_in_saint_martin gives 0 with the
 * mention. Without either, pending_verification com_taaf_supplier_local_tax_liable.
 * A SELLER established in one of the seven stays unsupported with the issue
 * seller_com_taaf_local_tax_not_determined.
 * Under `integration`, a positive rate on a line located in a collectivity
 * answers `422 integration_vat_incoherent` (only category `O` at 0 describes it),
 * and `placeOfSupply` is required on every line as soon as the buyer is
 * established in one of the seven.
 *
 * Territoriality, on the other hand, is the SAME under both sources — including
 * the frontier of the perimeter itself: a seller established outside the French
 * VAT territory raises under `integration` exactly the obstacle it raises under
 * `facturino` (`unsupported`). A buyer established in an excluded EU VAT
 * territory (Canary Islands, Ceuta, Melilla) has
 * eu_special_territory_not_implemented: unsupported under facturino, final with
 * a notice and foreignTaxReviewRequired under integration. The same source
 * distinction applies to non_taxable_legal_person_goods_regime_not_modelled:
 * goods dispatched to another member state or XI for a non-taxable legal person
 * that communicated a VAT number.
 * Both sources refuse mixed category O documents (unsupported,
 * vat_category_o_split_required, EN 16931 BR-O-11 to BR-O-14): issue two documents.
 * Both also refuse an EU address missing the postal code needed to split VAT
 * territories (destination_regional_scope_undetermined): supply the postal code.
 * A B2C sale to a consumer of another member state — an
 * electronically supplied service (art. 58) or an intra-EU distance sale of
 * goods (art. 33(a)) — traverses the same coverage, the same EUR 10,000
 * threshold, the same option, the same evidence and the same declarative
 * mechanism whichever source concluded the VAT. Where `facturino` produces the
 * rate, `integration` compares the supplied one to the legal result, at BOTH
 * places the rule can settle: equal means final, a category no covered B2C
 * supply can carry or a contradicted `placeOfSupply` answers
 * `integration_vat_incoherent`, and a rate neither the destination standard rate
 * nor the published bands of the seller's own territory confirm leaves the
 * decision non-final with `eu_b2c_rate_supplied_mismatch` — which at origin
 * asserts no foreign tax, the operation being taxed in France. `goodsMovement`
 * is therefore required on a goods line as soon as the buyer is a consumer of
 * another member state: that movement decides whether the distance-sale rule
 * applies, and it is never assumed.
 *
 * A decision reached by that rule carries `euB2cDestination`: the verdict and
 * its basis, the threshold figures it was decided on, the declarative mechanism
 * and the rate entry — registry version, source, verification date, period and
 * region — so the position can be audited years later without replaying the
 * engine. `null` on every operation the rule does not reach.
 */
class TaxDecision
{
    private const BASE_PATH = '/v1/tax-decisions';

    /** Maximum length the API accepts for `Idempotency-Key`. */
    private const MAX_IDEMPOTENCY_KEY_LENGTH = 255;

    /**
     * Take a decision on a commercial operation.
     *
     * Every input line MUST declare category, under both sources. This POST
     * contract is unchanged. Response category may be null only on a pending
     * decision read from a quote/draft without a declared supplyCategory.
     * A service line is then read as services and electronically supplied
     * services: the same complete result (VAT, category, mentions, local tax,
     * three obligation axes) gives final, category services and a
     * supply_category_not_declared notice. Otherwise the decision is pending
     * with that issue, category null on the line and no amounts. With neither
     * kind nor supplyCategory, no service reading is presumed: declare its nature.
     *
     * Business idempotency is durable here, beyond the 24-hour transport
     * window, and it is keyed on the KEY - not on the operation. The same
     * idempotency key with the same canonical body always replays the same
     * decision, so a retry after a lost response costs nothing and charges
     * nothing twice. Two DIFFERENT keys describing the same operation produce
     * two decisions: nothing matches them up, and reusing one key with a
     * different body answers 409.
     *
     * Amounts are integer centimes; quantities are decimal STRINGS, never
     * floats. Location and business-status evidence carries the territorial
     * signal only — a country, and a postal code where the territory needs one
     * — never an IP address, a PSP payload or bank account details.
     *
     * Any line may carry `placeOfSupplyRule`, the territoriality rule of the
     * operation (`general` when absent, under both sources); on an `integration`
     * line it is the rule under which `placeOfSupply` (2 to 16 characters) is
     * checked against the place Facturino decides.
     *
     * Any input line may also carry optional `localTaxExemption`, an open
     * string. The documented values today are `cgism_253` and
     * `not_used_in_saint_martin`; future values pass through unchanged under
     * either source.
     *
     * The API answers `201` on creation and `200` when the same key already
     * produced this decision — both return the decision, so the caller reads
     * one shape either way. Reusing the key with a different body answers
     * `409` and raises a ConflictException.
     *
     * @param array<string, mixed> $params          taxSource, customerId, effectiveAt, currency, priceMode, lines
     * @param string               $idempotencyKey  Required, 255 characters at most
     * @return array<string, mixed>
     */
    public static function create(array $params, string $idempotencyKey): array
    {
        $key = trim($idempotencyKey);
        if ($key === '') {
            throw new \InvalidArgumentException('Idempotency-Key is required to create a tax decision');
        }
        // The API caps the key at 255 characters. Checking it here turns a
        // round trip and a 400 into an immediate, local error.
        if (strlen($key) > self::MAX_IDEMPOTENCY_KEY_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'Idempotency-Key must be at most %d characters (received %d)',
                self::MAX_IDEMPOTENCY_KEY_LENGTH,
                strlen($key)
            ));
        }
        return HttpClient::request('POST', self::BASE_PATH, $params, null, [
            'Idempotency-Key' => $key,
        ]);
    }

    /**
     * Retrieve a decision — typically after a payment capture, to check that
     * the captured amount, currency and buyer match what was decided.
     *
     * @param string $id Decision ID (taxdec_xxx)
     * @return array<string, mixed>
     */
    public static function retrieve(string $id): array
    {
        return HttpClient::request('GET', self::BASE_PATH . '/' . $id);
    }
}
