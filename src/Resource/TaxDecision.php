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
 * `vatRate`, `vatCode` and `vatexCode` describe FRENCH VAT: the contract has no
 * local-tax jurisdiction, no local tax scheme and no withholding, so the
 * `integration` source is not a way to pass one through. Where a local tax of a
 * French overseas collectivity or the TAAF (PM, BL, MF, PF, NC, WF, TF) can
 * change what is invoiced or what is collected, the decision is not final under
 * EITHER source. The one sourced exception is a B2B service located in New
 * Caledonia (art. Lp. 507-1). `placeOfSupply` is therefore required on every
 * `integration` line as soon as the buyer is established in one of the seven.
 *
 * Territoriality, on the other hand, is the SAME under both sources — including
 * the frontier of the perimeter itself: a seller established outside the French
 * VAT territory, or a buyer sitting in a territory excluded from the EU VAT
 * territory, raises under `integration` exactly the obstacle it raises under
 * `facturino`. A B2C sale to a consumer of another member state — an
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
