<?php

declare(strict_types=1);

namespace Facturino\Resource;

use Facturino\HttpClient;

/**
 * Annual EU threshold ledger — /v1/eu-threshold-ledgers
 *
 * The ledger is the running total the two EU B2C thresholds are assessed on. It
 * is deliberately separate from the seller's fiscal profile: a profile revision
 * is an immutable RULE that decisions freeze, while a turnover total moves with
 * every sale and gets corrected. Keeping them apart is what lets a correction be
 * recorded without rewriting the rule a frozen decision was taken under.
 *
 * It carries TWO counters, strictly apart and INDEPENDENT: the common
 * EUR 10,000 threshold (art. 59c(1) — intra-EU distance sales of goods AND
 * cross-border services to consumers) and the EUR 100,000 location-evidence
 * threshold (Reg. 282/2011 art. 24b — electronically supplied services,
 * DOMESTIC ones included). A distance sale of goods raises the first and never
 * the second, so every figure comes in a pair — and neither bounds the other,
 * because the common threshold counts only cross-border supplies. A seller whose
 * electronic services are mostly sold at home legitimately declares more on the
 * second.
 *
 * Nothing is assumed. Opening a year declares four figures — the two totals and
 * their services part — plus whether every covered sale goes through Facturino.
 * Sales made elsewhere are never assumed absent: under `mixed_channels` they
 * enter through adjustments, and the ledger serves a decision only up to the day
 * those channels are declared complete through.
 * An undeclared EUR 10,000 threshold leaves the decision pending_verification
 * without amounts (eu_threshold_state_missing); open the year's ledger.
 * When destination VAT is due without a declared mechanism, it is invoiced on
 * a final decision with destination_mechanism_missing and registration guidance.
 *
 * The ledger is append-only: there is no update and no delete. Giving an amount
 * back is a qualified CORRECTION, which names the movement it corrects; what
 * cannot be qualified that way puts the ledger under review instead.
 *
 * A movement's `kind` is one of `opening`, `external_adjustment`,
 * `external_correction`, `reservation_consumed`, `reservation_released`,
 * `review_opened` and `review_resolved`. `reservation_released` gives a
 * reserved slice back: zero amounts when it was never consumed, negative
 * amounts when a sale counted for a draft is taken back out before any
 * issuance (the decision that replaces it counts it once).
 */
class EuThresholdLedger
{
    private const BASE_PATH = '/v1/eu-threshold-ledgers';

    /**
     * Open a calendar year.
     *
     * A year already open is never rewritten (`eu_threshold_year_already_open`):
     * decisions were frozen on its opening figures. `externalCompleteThroughDate`
     * must belong to the ledger's own year and must never be in the future.
     *
     * The two evidence figures are INDEPENDENT of the totals they accompany, in
     * both directions: the common threshold counts only cross-border supplies,
     * the art. 24b one counts domestic electronic services too.
     *
     * @param array<string, mixed> $params year, previousYearAmount, currentYearOpening,
     *                                     previousYearEvidenceAmount, currentYearEvidenceOpening,
     *                                     coverageMode, externalCompleteThroughDate
     * @return array<string, mixed>
     */
    public static function open(array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH, $params);
    }

    /**
     * Read a year: acquired totals, held slices, what remains, first page of movements.
     *
     * `acquiredMin` and `reservedMin` are published apart and never summed: a
     * held slice may still disappear, and one figure would hide that.
     *
     * @param array<string, mixed> $queryParams limit, starting_after
     * @return array<string, mixed>
     */
    public static function retrieve(string $year, array $queryParams = []): array
    {
        return HttpClient::request(
            'GET',
            self::BASE_PATH . '/' . $year,
            null,
            $queryParams === [] ? null : $queryParams,
        );
    }

    /**
     * Walk the movements page by page, newest first.
     *
     * The ledger keeps every movement; a page shows some, and `next_cursor`
     * names the last one returned.
     *
     * @param array<string, mixed> $queryParams limit, starting_after
     * @return array<string, mixed>
     */
    public static function listEntries(string $year, array $queryParams = []): array
    {
        return HttpClient::request(
            'GET',
            self::BASE_PATH . '/' . $year . '/entries',
            null,
            $queryParams === [] ? null : $queryParams,
        );
    }

    /**
     * Record turnover made on another channel.
     *
     * Append-only and idempotent on your own `reference`, compared through a
     * canonical fingerprint of the WHOLE body: replaying the same adjustment
     * adds nothing, and reusing the reference for any different fact answers
     * `eu_threshold_entry_conflict`. The reference never becomes a document id —
     * it is hashed, and kept verbatim as data. A negative amount is impossible
     * here: giving an amount back is `correct()`.
     *
     * `evidenceAmount` is INDEPENDENT of `amount`: a domestic electronic service
     * raises the art. 24b counter and not the other.
     *
     * @param array<string, mixed> $params reference, amount, evidenceAmount,
     *                                     externalCompleteThroughDate, reason
     * @return array<string, mixed>
     */
    public static function adjust(string $year, array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $year . '/adjustments', $params);
    }

    /**
     * Take a qualified amount back out of the running total.
     *
     * This is NOT a negative adjustment. Directive 2006/112/EC art. 90(1)
     * reduces the taxable amount of a supply on cancellation, refusal or a price
     * reduction after the supply, and the thresholds count the VALUE of the
     * supplies — so the correction NAMES the movement it corrects, its
     * qualification, the resource it rests on and its evidence. An unknown
     * movement answers `eu_threshold_correction_target_unknown`; giving back
     * more than it brought in answers `eu_threshold_correction_exceeds_counted`.
     *
     * Decisions already frozen are never rewritten: they were correct on the
     * figures of their own day. Only the total the NEXT operations read changes.
     *
     * `correctsEntryId` is constrained to the ids the ledger mints (`opening`,
     * or `adj_`/`cor_`/`con_`/`rel_`/`rev_` plus 32 hex characters): it reaches
     * a document path, and free text must not. The ledger also keeps the BALANCE
     * of each movement inside the transaction, so the corrections of one
     * movement never add up to more than it brought in — each entry publishes
     * its `remainingMin`.
     *
     * @param array<string, mixed> $params reference, correctsEntryId, kind, amount,
     *                                     evidenceAmount, relatedResourceType,
     *                                     relatedResourceId, evidenceReference, reason
     * @return array<string, mixed>
     */
    public static function correct(string $year, array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $year . '/corrections', $params);
    }

    /**
     * Stop deciding on this ledger: its running total is known to be wrong.
     *
     * Every reservation then answers `eu_threshold_review_required`. This is the
     * honest exit when an amount must come out and no qualified correction can
     * name the movement it corrects.
     *
     * @param array<string, mixed> $params reason
     * @return array<string, mixed>
     */
    public static function review(string $year, array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $year . '/review', $params);
    }

    /**
     * Serve decisions again — by RECONCILIATION, never by comment.
     *
     * A review says the running total is known to be wrong. Reopening the ledger
     * on a free-text note would put that same total back in front of the next
     * verdict with a sentence for only guarantee. So you state the figures you
     * actually verified, and the server compares them to its own:
     * `reconciledVersion` pins the state that was checked (a movement recorded
     * since answers `eu_threshold_reconciliation_stale`), and the two acquired
     * totals must MATCH (`eu_threshold_reconciliation_mismatch`, which returns
     * both figures). What was verified is written into the immutable
     * `review_resolved` movement, with its evidence reference.
     *
     * @param array<string, mixed> $params reconciledVersion, reconciledAcquiredMin,
     *                                     reconciledAcquiredEvidenceMin,
     *                                     evidenceReference, reason
     * @return array<string, mixed>
     */
    public static function resolveReview(string $year, array $params): array
    {
        return HttpClient::request('POST', self::BASE_PATH . '/' . $year . '/review/resolve', $params);
    }
}
