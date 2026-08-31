# Changelog

All notable changes to `facturino/facturino-php` are documented here. This
project adheres to [Semantic Versioning](https://semver.org/) and
[Keep a Changelog](https://keepachangelog.com/).

## [2.0.0] - 2026-08-31

The first STABLE tax determination contract. Every invoice is now created from
an immutable tax decision, taken before the document exists, whichever of the
two fiscal journeys takes it.

### Added
- `TaxDecision` resource with `create()` and `retrieve()`:
  `POST /v1/tax-decisions` and `GET /v1/tax-decisions/{id}`. A decision fixes
  the VAT, the exact `amountToCharge` and the three reporting axes for one
  operation, then never changes. Available as `\Facturino\TaxDecision` and
  `\Facturino\Resource\TaxDecision`.
- Two equal fiscal sources, declared with the required `taxSource` field:
  `facturino` (Facturino determines the VAT from the commercial lines and the
  territorial evidence) and `integration` (your system supplies the VAT of
  every line: `vatRate`, `vatCode`, and where the rate is zero a `vatexCode`
  and the line's `placeOfSupply`). Supplied VAT is validated for coherence —
  contradictions answer `integration_vat_incoherent`, never a silent
  correction.
- `TaxDecision::create()` takes a required idempotency key — non-empty and at
  most 255 characters, checked locally so an over-long key fails immediately
  instead of after a round trip. Sent as the `Idempotency-Key` header so a
  retried request replays the same decision.
- `Invoice::create()` forwards `deposits` and `schedule` alongside the
  decision: both are settled server-side against the DECIDED amount due.
- `Facturino\Exception\ConflictException` for `409` responses — an
  `Idempotency-Key` reused with a different body, or a concurrent write. It
  extends `InvalidRequestException`, so existing `catch` blocks keep working.
- `Invoice::bindTaxDecision($id, ['taxDecisionId' => …, 'decisionLines' => …])`:
  `POST /v1/invoices/{id}/bind-tax-decision`. Freezes a FINAL decision onto a
  commercial draft that already exists — the one `Quote::convert()` produced —
  so the quote cycle runs on ONE document: convert → decide → bind → finalize.
  The invoice stays a draft; `finalize()` issues it. Idempotent on the decision.

### Changed — BREAKING
- `Invoice::create()` requires `taxDecisionId` + `decisionLines` and refuses
  `lines`/`items` locally, before any HTTP call: an invoice never states its
  own VAT. The explicit-tax contract is removed.
- `Invoice::update()` no longer accepts commercial or fiscal fields — only
  `dates`, `payment`, `notes`, `purchaseOrderNumber` and `metadata`. To change
  the operation, take a new decision and create a new draft.
- `CreditNote::create()` takes `creditedLines` (fractions of the original
  invoice's frozen lines); `items` is removed. A credit note strictly inherits
  the VAT of the invoice it credits.
- `RecurringInvoice::create()` requires `taxInputs` (with its `taxSource`);
  the `templateInvoice.items` template is removed. Each occurrence gets its
  OWN decision on its generation date.
- `taxSource` values are `facturino` and `integration` — the two equal
  journeys, and the only two. A commercial draft that has not been decided yet
  reads `taxSource: null`: it states the operation and no fiscal conclusion.
- `Facturino::API_VERSION` is `2026-09-01`, the single stable contract; the
  API serves no earlier version.

### Notes
- `TaxDecision::create()` returns the decision on both `201` (created) and
  `200` (the same key already produced it). Reusing a key with a different
  body answers `409` and raises `ConflictException`.
- A tax decision is immutable: the resource exposes no update and no delete.
  To re-decide the same operation after supplying missing evidence, create a
  new decision with `retryOfTaxDecisionId`.
- Invoices and credit notes return three status axes — `documentStatus`,
  `transmissionStatus`, `transmissionDetail`, `paymentStatus` — plus
  `taxSource`, `taxDecisionId` / `originalTaxDecisionId` and `taxSnapshot`.
  Responses are associative arrays, so the keys are readable with no code
  change; the `status` key stays populated as their summary projection.

## [1.1.0] - 2026-08-05

### Added
- Document `paypal` as an accepted payment method. Params are dynamic arrays, so
  the value already passes through; the API may add further payment method
  values over time — tolerate unknown values.

## [1.0.0] - 2026-07-25 — Initial release
