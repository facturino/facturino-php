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
