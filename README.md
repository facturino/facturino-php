# Facturino PHP SDK

Official PHP client for the [Facturino API](https://facturino.com/docs/api) — French e-invoicing made simple.

[![Tests](https://github.com/facturino/facturino-php/actions/workflows/test.yml/badge.svg)](https://github.com/facturino/facturino-php/actions/workflows/test.yml)
[![Latest Version](https://img.shields.io/packagist/v/facturino/facturino-php.svg)](https://packagist.org/packages/facturino/facturino-php)
[![PHP Version](https://img.shields.io/packagist/php-v/facturino/facturino-php.svg)](https://packagist.org/packages/facturino/facturino-php)
[![License](https://img.shields.io/packagist/l/facturino/facturino-php.svg)](https://github.com/facturino/facturino-php/blob/main/LICENSE)

## Requirements

- PHP 8.1+
- ext-curl
- ext-json

## Installation

```bash
composer require facturino/facturino-php
```

## Quick start

The recommended path is decision-first: identity → final tax decision →
create the decision-backed draft immediately → your chosen collection flow.
Facturino imposes no payment service provider and no payment method: an
immediate capture, a bank transfer, a direct debit or payment on agreed terms
all fit the same contract.

```php
require 'vendor/autoload.php';

\Facturino\Facturino::setApiKey('fac_test_xxx');

// 1. Decide before the final amount is presented, the invoice is issued,
//    or collection starts.
$decision = \Facturino\TaxDecision::create([
    'taxSource' => 'facturino', // or 'integration' to supply your own VAT
    'customerId' => 'cus_8f2k4m9n',
    'effectiveAt' => '2026-09-15',
    'currency' => 'eur',
    'priceMode' => 'tax_exclusive',
    'lines' => [[
        'reference' => 'abo-pro',
        'description' => 'Abonnement Pro',
        'category' => 'electronically_supplied_services',
        'rateCategory' => 'standard',
        'unitAmount' => 2900, // integer centimes
        'quantity' => '1',    // decimal STRING, never a float
    ]],
], 'order-4711'); // Idempotency-Key: required, 255 characters at most

// 2. Act only on a final decision. "pending_verification" does not mean
//    "nothing to charge": totals and amountToCharge are null, not 0.
if ($decision['status'] === 'pending_verification') {
    return askForMissingEvidence($decision['issues']);
}
if ($decision['status'] !== 'final') {
    return showRefusal($decision['issues']);
}
//    Notices say what was retained and what the seller can do next.
//    They never stand for a missing fact — show them to the seller.
showToSeller($decision['notices']);

// 3. Create the decision-backed draft immediately: no VAT is restated.
$invoice = \Facturino\Invoice::create([
    'customerId' => $decision['customerId'],
    'taxDecisionId' => $decision['id'],
    'decisionLines' => [['taxLineRef' => 'abo-pro', 'unit' => 'month']],
    'buyer' => $buyerSnapshot,
    'dates' => ['issued' => '2026-09-15', 'due' => '2026-10-15'],
    'payment' => $paymentTerms,
]);

// 4. Choose your collection flow — see the two variants below.
```

**Immediate collection** — capture the decided amount, verify, then finalize WITH the collection:

```php
// Capture exactly amountToCharge through your payment provider, payment
// processor, bank transfer or external collection flow. Carry $decision['id']
// in the provider metadata, order reference or custom reference. The
// settlement keeps its OWN financial reference (charge id, transfer
// wording…): the two identifiers are different things and must stay distinct.
$settlement = $yourCollectionProcess->capture(
    $decision['amountToCharge'],   // never a locally computed total
    $decision['currency'],
    ['taxDecisionId' => $decision['id']],
);

// Re-read the decision by its own id and verify what was actually captured.
$source = \Facturino\TaxDecision::retrieve($decision['id']);
if ($settlement['amount'] !== $source['amountToCharge']) {
    throw new \RuntimeException('amount mismatch');
}
if ($settlement['currency'] !== $source['currency']) {
    throw new \RuntimeException('currency mismatch');
}

// Finalize AND record the REAL payment in one call: the numbering and the
// collection land in the same transaction, so the issued original (PDF and
// Factur-X) is rendered on a settled invoice. The array is the very one
// Payment::create() takes — its real date, method and the settlement's
// financial reference, never the decision id
// (transfer, card, check, cash, direct_debit, sepa, paypal or other).
\Facturino\Invoice::finalize($invoice['id'], [
    'amount' => $settlement['amount'],
    'method' => $settlement['method'],
    'reference' => $settlement['reference'],
    'paidAt' => $settlement['paidAt'],
]);

// Send to the platform only on the channel the FROZEN decision states.
if ($source['invoiceChannel'] === 'einvoicing') {
    \Facturino\Invoice::send($invoice['id']);
}
```

**Payment on terms** — finalize and deliver now, collect later:

```php
\Facturino\Invoice::finalize($invoice['id']);
if ($decision['invoiceChannel'] === 'einvoicing') {
    \Facturino\Invoice::send($invoice['id']);
}

// …once the transfer arrives, record the REAL collection date.
\Facturino\Payment::create($invoice['id'], [
    'amount' => $decision['amountToCharge'],
    'method' => 'transfer',
    'reference' => 'VIR-2026-000871',
    'paidAt' => '2026-10-12',
]);
```

## Supplying your own VAT (`taxSource: integration`)

If your system already determines the VAT — an ERP, a marketplace engine, an
in-house rules service — declare it on the decision instead of asking
Facturino to determine it. Each line then carries the VAT you supply:
`vatRate` (integer centipercent), `vatCode` (S, Z, E, AE, K, G or O) and,
when the rate is zero, the `vatexCode` and `placeOfSupply` justifying it.
Facturino validates the coherence of the whole (a positive rate with an
exemption code, a franchise seller charging VAT, a reverse charge to a
consumer… are refused with `integration_vat_incoherent`) and never silently
corrects a rate. The decision, the invoice and the reporting obligations then
work exactly as with `taxSource: facturino` — the two journeys are equals.

```php
$decision = \Facturino\TaxDecision::create([
    'taxSource' => 'integration',
    'customerId' => 'cus_8f2k4m9n',
    'effectiveAt' => '2026-09-15',
    'currency' => 'eur',
    'priceMode' => 'tax_exclusive',
    'lines' => [[
        'reference' => 'conseil',
        'description' => 'Prestation de conseil',
        'category' => 'services',
        'unitAmount' => 10000,
        'quantity' => '1',
        'vatRate' => 2000, // 20.00 % — supplied by YOUR system
        'vatCode' => 'S',
    ]],
], 'order-4712');

$invoice = \Facturino\Invoice::create([
    'customerId' => $decision['customerId'],
    'taxDecisionId' => $decision['id'],
    'decisionLines' => [['taxLineRef' => 'conseil', 'unit' => 'unit']],
    'buyer' => $buyerSnapshot,
    'dates' => ['issued' => '2026-09-15', 'due' => '2026-10-15'],
    'payment' => $paymentTerms,
]);
```

### What this source is not

`vatRate`, `vatCode` and `vatexCode` describe **French VAT**. A local tax travels
separately in the optional `localTax` fields described below; this source never
passes a local tax through the French VAT fields.

`vatexCode` on invoice lines, credit-note lines, products and tax-decision lines
uses the official CIUS-FR list (88 codes), including `VATEX-FR-CGI261-4`,
`VATEX-FR-CGI261-7`, `VATEX-FR-CGI295`, `VATEX-FR-AE`, `VATEX-EU-132-1I`,
`VATEX-EU-I` and `VATEX-EU-J`. Legacy inputs `VATEX-FR-261` and
`VATEX-FR-AUTOLIQ` are accepted and stored and returned as `VATEX-FR-CGI261-4`
and `VATEX-FR-AE`. A document issued earlier keeps the code it was issued with.
The SDK keeps these values as open strings. The API contract date remains
2026-09-01.

### Overseas collectivities and the TAAF

Saint-Pierre-et-Miquelon (`PM`), Saint-Barthélemy (`BL`), Saint-Martin (`MF`),
French Polynesia (`PF`), New Caledonia (`NC`), Wallis-and-Futuna (`WF`) and the
TAAF (`TF`) are not part of France for VAT (CGI art. 256-0). Each legislates its
own indirect taxation, and no answer is deduced from a neighbour.

**Buyer established in one of the seven.** For an operation *located* in the
collectivity, French VAT is out of scope: the line is category `O` at 0.
Cases settled by a collectivity's text stay `final` under both sources:
New Caledonia where the taxable buyer accounts for the tax (art. Lp. 507-1),
Saint-Barthélemy, Saint-Pierre-et-Miquelon, and goods shipped to Saint-Martin.

Unmodelled cases are `unsupported` under `facturino`, and `final` under
`integration` with a notice carrying the same code and
`foreignTaxReviewRequired: true`; the integrator declares and assumes the tax:

| Issue under `facturino`; notice under `integration` | When |
|---|---|
| `com_taaf_local_tax_not_determined` | Non-taxable buyer (electronically supplied service to a consumer, CGI art. 259 D): a consumer self-assesses nothing, only the supplier could be called on to collect the collectivity's tax. |
| `com_taaf_local_regime_not_sourced` | Wallis-and-Futuna, TAAF: no official act held states who bears the tax. |
| `com_taaf_local_regime_not_sourced`, `detailCode: french_vat_and_tgca_cumulation` | General service to a Saint-Martin consumer without `localTaxExemption`: French VAT is due and the local-tax cumulation is not settled by a text held by Facturino. |
| `com_taaf_payment_withholding_not_modelled` | French Polynesia: the client withholds 10 % at source on the payment, and the service is taxed locally there. |

**Saint-Martin TGCA follows its known rule under both sources.** A registration
in force in `sellerProfile.localTaxRegistrations` gives 4% per line in `localTax`.
A declared `localTaxExemption` (`cgism_253`, `not_used_in_saint_martin`) gives 0
with the mention. Without either, the decision is `pending_verification` with
`com_taaf_supplier_local_tax_liable`. `totals.totalLocalTax` is included in
`totalTTC` and the amount to collect. TGCA is separate from French VAT.

**Seller established in one of the seven.** The decision is **not final**
(`unsupported`), with the *issue* `seller_com_taaf_local_tax_not_determined`: the
local tax of its collectivity decides what it invoices, and that is outside the
perimeter. This one is an issue, not a notice.

Under the `integration` source, a positive French VAT rate on a
line located in a collectivity answers `422 integration_vat_incoherent`: only
category `O` at 0 (`VATEX-EU-O`) describes such a place, and a positive rate
would declare a local tax as French VAT. `placeOfSupply` is **required** on every
line as soon as the buyer is established in one of the seven — the place of the
operation is what says whether the local tax is at stake, and it is never assumed
(`422 integration_vat_incoherent` otherwise) — and it must be the place Facturino
decides. A general B2C service can stay located in France; the Saint-Martin
cumulation case above still requires a declared `localTaxExemption` for Facturino
to settle it.

Both sources refuse a document mixing category `O` with any other category:
`unsupported`, `vat_category_o_split_required` (EN 16931 BR-O-11 to BR-O-14).
Issue two documents.

## B2C sales to consumers in other member states

An **electronically supplied service** to a consumer established in another
member state (Directive 2006/112/EC art. 58) and an **intra-EU distance sale** of
goods (art. 33(a)) follow one common regime. A **general** B2C service does not:
it stays taxed where the supplier is established (art. 45), and nothing below
concerns it.

Four questions are answered separately, in this order. Collapsing any two of them
produces a wrong rate:

1. is the operation covered by a destination rule;
2. does the common **EUR 10,000** threshold still allow taxation at origin
   (art. 59c(1));
3. did the seller **opt** for taxation at destination (art. 59c(3));
4. how is the tax due at destination **declared** — Union one-stop shop, or a
   local VAT registration in that member state?

The one-stop shop answers the **last** of the four: it is a way of declaring and
paying a tax, not a rule of place. Without it and without a local registration,
the tax due at destination is still due: it is invoiced exactly as with the
one-stop shop (same rate, same lines, same axes), and the notice
`destination_mechanism_missing` says how to register in time. It never falls back
on the seller's own national VAT.

That does not make the questions watertight in fact. For a **French** seller,
registering for the Union scheme is how the option of art. 59c(3) is exercised:
an **active** registration therefore settles the place at destination on its own,
and the threshold has nothing left to decide (`basis: "oss_union_registration"`,
`threshold: null`). The registration is dated — one opened in October decides
nothing for a September sale, and one that ended decides nothing any more. It is
sourced for France only: the way the option is exercised is fixed by the member
state where it is exercised, so a seller established elsewhere keeps the ordinary
threshold path and states its option explicitly.

The threshold is an **inclusive** cap of `1000000` centimes excluding VAT, open
only to a seller established in a **single** member state; the operation that
carries the running total past it is itself taxed at destination.

That running total lives in an **annual ledger** — `/v1/eu-threshold-ledgers`,
one per company, per mode and per calendar year — and NOT on the fiscal profile.
A profile revision is an immutable rule that decisions freeze; a turnover total
moves with every sale and gets corrected, so the two are kept apart. Facturino
keeps the register of the operations it receives; the sales made on your other
channels must be brought in by an adjustment, or by the opening declaration:

- **opening a year** declares four figures — the previous year's total and the
  total already made this year, each with its *services* part (see the two
  counters below) — plus the coverage mode: `facturino_only` (every covered sale
  goes through Facturino) or `mixed_channels`;
- **an adjustment** adds the turnover of another channel and moves forward the
  day those channels are declared complete through. Under `mixed_channels` a
  decision is served only up to that day.

**Two counters, strictly apart — and independent.** The same movements feed two
thresholds that do not measure the same thing: the common EUR 10,000 threshold
above, and the EUR 100,000 threshold of Reg. 282/2011 art. 24b that governs how
many items of location evidence are required. The second counts only telecom,
broadcasting and electronically supplied services, **domestic ones included**;
the first counts only **cross-border** supplies. A distance sale of goods raises
the first and never the second — and a domestic electronic service raises the
second and never the first.

Neither bounds the other, in either direction: a publisher selling mostly at home
legitimately declares far more on the evidence counter than on the common one.
That is why every figure comes in a pair (`amount` / `evidenceAmount`,
`acquiredMin` / `acquiredEvidenceMin`, …) rather than as a total and a share of
it, and why the single-evidence relaxation is **computed by the engine** on that
second counter rather than declared by the seller.

**Acquired and reserved are published apart, and never summed.** `acquiredMin`
is what the year has certainly made; `reservedMin` is the slices held right now
by operations still being decided. A held slice may still disappear, and one
combined "total" would hide exactly that.

Nothing is assumed: no year starts at zero on its own, and no sale made elsewhere
is presumed absent. A decision reserves its slice of the total in a transaction
and consumes it with the decision itself, so two concurrent operations never read
the same figure as certain and a replay counts nothing twice. A verdict is frozen
only when it holds at **both** bounds — with every concurrent slice counted and
with none of them — which is what makes an abandoned operation simply disappear
instead of staying in the total as turnover that never existed.

**Giving an amount back is a qualified correction, never a negative
adjustment.** Directive 2006/112/EC art. 90(1) reduces the taxable amount of a
supply on cancellation, refusal or a price reduction after the supply, and the
thresholds count the VALUE of the supplies — so a correction names the movement
it corrects, its qualification, the resource it rests on and its evidence.

A movement gives back what it brought in **once**, whatever the number of
corrections: the ledger keeps each movement's balance inside the same
transaction, and every entry publishes its `remainingMin`. `correctsEntryId` is
restricted to the ids the ledger itself mints — it reaches a document path, and
free text must not.

What cannot be qualified that way is not subtracted at all: the ledger goes
**under review** and stops deciding, rather than freeze verdicts on a total
nobody stands behind. A review is settled by **reconciliation**, never by a
comment: you state the version you checked and the two acquired totals you
verified, and only an exact agreement reopens the ledger.

**A decision is never frozen without its slice.** If the slice it held has
disappeared when the decision is about to be written, the transaction is
abandoned — no decision, no audit entry, no settled claim
(`409 eu_threshold_reservation_lost`) — and the ledger goes under review on a
path of its own, so the review survives that abandonment. Freezing a decision the
running total does not carry would leave the sale inside a decision and outside
the year the next operation reads.

Movements are paginated with a cursor (`limit`, `starting_after`): the ledger
keeps them all, a page shows some.

A missing threshold or location fact leaves `pending_verification`, without
amounts, with the fact and remedy in `issues`. Facturino never replaces a
missing fact with an assumption.

| Issue code | What to complete |
| --- | --- |
| `eu_threshold_state_missing` | The EUR 10,000 threshold is not declared. Open the year's ledger. |
| `destination_regional_scope_undetermined` | The EU address lacks the postal code needed to distinguish VAT territories. Both sources refuse to choose a territory; supply the postal code. |
| `location_evidence_insufficient`, `location_evidence_contradictory` | Supply sufficient concordant location evidence; the decision cannot retain a consumer territory. |
| `location_evidence_relief_undetermined` | The art. 24b relief cannot be established. Declare the year's services figures or supply a second item of evidence. |

A notice explains a retained result and what the seller can do next:

| Notice code | When |
| --- | --- |
| `destination_mechanism_missing` | Destination taxation is due with neither the Union scheme nor a local registration valid for that state on that date. The tax is invoiced; the notice's `detailCode` (`threshold_exceeded`, `destination_option` or `multi_member_state_establishment`) says which operation starts the registration period. Register with the one-stop shop by the 10th of the month that follows the first operation subject to the regime for it to apply from that operation (Implementing Regulation (EU) 282/2011 art. 57 quinquies), or register in the member state. |
| `location_evidence_contradictory` | A consumer territory was established by two concordant items of different kinds (`detailCode: concordant_evidence`), or every possible territory gives the same complete tax result (`detailCode: same_treatment`). |

The following issues also keep the decision **non-final**, without an amount:

| Issue code | When |
| --- | --- |
| `eu_threshold_external_coverage_incomplete` | Other channels exist and are not declared complete through the operation date. Record an adjustment — even a zero one, which simply states that nothing happened. |
| `eu_threshold_backdated_operation` | The operation predates one already counted, and decisions were frozen on that running total. It is refused rather than silently recomputed. |
| `eu_threshold_concurrent_decision_pending` | Other operations of the company are being decided at this very moment, and the cap falls between "all of them confirmed" and "all abandoned". Nothing is frozen on that: decide again once they conclude. |
| `eu_threshold_review_required` | The ledger is under review — its running total is known to be wrong, and no verdict rests on it until the review is settled. |
| `destination_threshold_operation_value_missing` | A line value cannot be sized, so no slice of the total can be taken for it. |
| `destination_threshold_price_mode_ambiguous` | Tax-inclusive price: the VAT-exclusive value depends on the rate the threshold has to decide, and the bounds fall on both sides of the cap. |
| `destination_option_period_invalid` | The option is declared over less than its minimum binding period. |
| `destination_option_scope_not_sourced` | Seller established outside France: the binding period is fixed by the member state where the option is exercised, and only the French one is sourced here. |
| `destination_establishment_in_member_state` | The seller declares an establishment in the destination member state: which establishment supplies then decides both the place and the mechanism, and no fact of the contract names it. A local VAT registration alone is not an establishment. |
| `franchise_destination_taxation_not_modelled` | Seller under the French small-enterprise exemption whose operation is taxed by another member state. |

Rates come from a **local, dated, versioned registry**: no network call during a
decision, and a decision replayed years later reproduces the same rate. A rate
change is a new period, never a rewrite. Only the **standard** rate of the 27
member states is tabulated — the scope of the reduced rates follows a national
classification this contract does not hold, and an ordinary electronically
supplied service is never granted the rate an electronic publication may benefit
from (`destination_rate_band_not_available`). An effect date before the registry
answers `destination_rate_not_sourced_for_date`.

A **region publishing its own standard rate** never blocks a whole member state.
What governs the region decides the answer:

- **the rate follows the place of the operation.** The region is then a territory
  of its own and the address decides: Portugal mainland 23%, Madeira 22%, Azores
  16% (CIVA art. 18, CTT postal ranges). An address placing no region at all
  leaves `destination_regional_scope_undetermined` under both sources, without
  amounts; supply the postal code;
- **the rate is reserved to operations carried out in the zone by a supplier
  established there.** A supplier at distance does not acquire it from the
  consumer's address, so the national rate is the final answer — and no postal
  cartography of the zone is needed to say so: Austrian Jungholz and
  Kleinwalsertal (§ 10(4) UStG — 20%, not 19%), and the Greek island regime FOR
  SERVICES, which AADE reserves to a supplier established on the island for an
  operation performed there. An electronically supplied service from France is
  therefore taxed at 24% in Greece, in Athens and in Kalymnos alike;
- **the rate follows the destination and the zone is not cartographied here.**
  Neither the regional rate nor the national one can be asserted, so the
  operation is refused: `destination_regional_regime_not_sourced`, non-final and
  without an amount. This is Greece FOR GOODS: since 2026-01-01 the islands of
  fewer than 20,000 inhabitants apply a reduced standard rate to the goods
  delivered there, intra-community acquisitions included, and the list of those
  islands is not cartographied by postal code here. An intra-EU distance sale of
  goods to Greece is therefore refused — in Athens as in Kalymnos — and never
  taxed at 24% by default. The answer is given per FAMILY of operation: the same
  member state can be settled for services and left open for goods.

### The annual ledger

```php
// Open the year — nothing starts at zero on its own.
\Facturino\EuThresholdLedger::open([
    'year' => '2026',
    'previousYearAmount' => 250000,      // centimes, VAT excluded
    'currentYearOpening' => 100000,
    'previousYearEvidenceAmount' => 500000,
    'currentYearEvidenceOpening' => 125000,
    'coverageMode' => 'mixed_channels',  // other channels exist
    'externalCompleteThroughDate' => '2026-01-01',
]);

// Bring in what was sold elsewhere. Append-only, idempotent on `reference`.
\Facturino\EuThresholdLedger::adjust('2026', [
    'reference' => 'marketplace-2026-08',
    'amount' => 40000,
    'externalCompleteThroughDate' => '2026-09-15',
    'reason' => 'Marketplace sales, August',
]);

$ledger = \Facturino\EuThresholdLedger::retrieve('2026');
$ledger['cumulativeMin'];  // total already acquired, in centimes
$ledger['remainingMin'];   // what is left before the cap
```

### The same rule under `taxSource: 'integration'`

An integration that concludes its own VAT does not get a different territoriality.
The `integration` source traverses the same coverage, the same threshold, the same
option, the same evidence and the same declarative mechanism; what differs is the
outcome. Where `facturino` **produces** the rate, `integration` **compares** the
one you supply to the legal result:

- equal — decision `final`;
- a category no B2C supply taxed at destination can carry (`AE`, `K`, `G`, `O`),
  or a `placeOfSupply` the rule contradicts — `422 integration_vat_incoherent`;
- a rate neither the destination **standard** rate nor the published bands of the
  seller's own territory confirm — non-final, with
  `eu_b2c_rate_supplied_mismatch`. Facturino holds only the standard rate of
  another member state, and only the bands it publishes for a French territory,
  so it can neither confirm a reduced rate nor correct yours. At origin that
  refusal asserts NO foreign tax: the operation is taxed in France;
- a missing fact — `pending_verification` with the fact and remedy in `issues`;
- an unmodelled case — `unsupported` under `facturino`, or `final` under
  `integration` with a notice carrying the same code and
  `foreignTaxReviewRequired: true`; the integrator declares and assumes the tax.

The confrontation reaches BOTH places the rule can settle, and the territorial
frontier is shared too: a seller established outside the French VAT territory
raises under `integration` exactly the obstacle it raises under `facturino` (the
decision is `unsupported`), and a buyer sitting in a territory excluded from the EU
VAT territory (the Canary Islands, Ceuta, Melilla, Åland…) has
`eu_special_territory_not_implemented`: `unsupported` under `facturino`, or
`final` under `integration` with that notice and `foreignTaxReviewRequired: true`.
The same source distinction applies to
`non_taxable_legal_person_goods_regime_not_modelled`: goods dispatched to another
member state or XI for a non-taxable legal person that communicated a VAT number.
Both sources refuse mixed category `O` documents (`vat_category_o_split_required`)
and an EU address missing the postal code that splits VAT territories
(`destination_regional_scope_undetermined`).

Because of this, `goodsMovement` is **required** on a goods line as soon as the
buyer is a consumer of another member state: that movement decides whether the
distance-sale rule applies, and it is never assumed.

### What the decision freezes

The decision carries `euB2cDestination`: what the rule concluded, as data rather
than as a sentence — the verdict and its basis, the threshold figures it was
decided on, the declarative mechanism, and the rate entry with its registry
version, its source, its verification date, its period and its region. It is
present as soon as the rule covers a line — including on a decision that is NOT
final, where it states exactly what is missing — and `null` on every operation
the rule does not reach.


## Auto-pagination

```php
// Iterate over all invoices automatically
foreach (\Facturino\Invoice::all(['limit' => 10]) as $invoice) {
    echo $invoice['id'] . ' ' . $invoice['status'] . "\n";
}

// Or access the first page directly
$collection = \Facturino\Invoice::all(['limit' => 25]);
$firstPage = $collection->getData();
$hasMore = $collection->hasMore();
```

## Resources

| Resource | Class | API endpoint |
|---|---|---|
| Tax Decision | `\Facturino\TaxDecision` | `/v1/tax-decisions` |
| Invoice | `\Facturino\Invoice` | `/v1/invoices` |
| Payment | `\Facturino\Payment` | `/v1/invoices/:id/payments` |
| Customer | `\Facturino\Customer` | `/v1/customers` |
| Product | `\Facturino\Product` | `/v1/products` |
| Quote | `\Facturino\Quote` | `/v1/quotes` |
| Credit Note | `\Facturino\CreditNote` | `/v1/credit-notes` |
| Event | `\Facturino\Event` | `/v1/events` |
| Webhook Endpoint | `\Facturino\WebhookEndpoint` | `/v1/webhook-endpoints` |
| Recurring Invoice | `\Facturino\RecurringInvoice` | `/v1/recurring-invoices` |
| Company | `\Facturino\Company` | `/v1/companies` |
| Export | `\Facturino\Export` | `/v1/exports` |
| E-reporting | `\Facturino\Ereporting` | `/v1/ereporting/declarations` |
| Job | `\Facturino\Job` | `/v1/jobs` |
| Sandbox | `\Facturino\Sandbox` | `/v1/sandbox` |
| Reference | `\Facturino\Reference` | `/v1/reference`, `/v1/pa-providers` |
| Health | `\Facturino\Health` | `/v1/health` |

> **Public token endpoints** — the recipient-facing portals (`/pay/:token`,
> `/portal/:token`, `/quote-portal/:token`) are intentionally not exposed by the
> SDK: they are opened by the end recipient through a hosted page, not called
> with an API key.

## E-reporting declarations

`\Facturino\Ereporting` returns declaration arrays. The optional
`Facturino\Model\EReporting` view exposes these read-only response fields:

| Key | Meaning |
|---|---|
| `obligationBasis` | Frozen transmission basis: `legal` for a legal obligation, `voluntary_early_adoption` for the seller's voluntary early adoption, or `null` when none applies or historical information is unresolved. |
| `obligationDueFrom` | Seller's legal wave opening date (`YYYY-MM-DD`), frozen at reservation; `null` when unresolved. A batch covering several waves keeps the earliest date. |
| `acknowledgedBy` | Who acknowledged receipt: `administration` (PPF), `platform`, or `null` without a known acknowledgement. Receipt is independent of acceptance. |
| `skippedReason` | Open string or `null`. `nothing_to_declare` with `state: skipped` means local closure without transmission and an obligation whose `state` is `completed`: operations outside French VAT scope have no payment data to declare. |

Historical responses may omit any of these fields. Typed properties then stay
null; serialization preserves the distinction between an absent key and an
explicit `null`. The API contract date remains `2026-09-01`.

Declaration `state` stays an open string. `reserved` has not been transmitted;
`blocked` leaves the obligation due. A historical `status: skipped` summary alone
does not establish local closure: read `state`, `skippedReason` and `obligation`.

## Products and quote supply categories

Products store and return optional `kind` (`goods` or `service`) and
`supplyCategory`. `kind` classifies the document; `supplyCategory` declares its
fiscal nature: `goods`, `services`, `electronically_supplied_services`, `deposit`
or `ancillary_costs`. These categories are hints on an open string: accept
future values, and preserve absence on older products. Deposits and ancillary
costs follow the principal operation of the document; the decision line names
that operation through `relatedCategory`.

```php
$product = \Facturino\Product::create([
    'name' => 'Online subscription',
    'unitPrice' => 10000, 'vatRate' => 2000, 'vatCode' => 'S', 'unit' => 'month',
    'kind' => 'service', 'supplyCategory' => 'electronically_supplied_services',
]);
\Facturino\Product::update($product['id'], [
    'reference' => 'SUB-MONTH', 'category' => 'subscriptions', 'active' => true,
    'tags' => ['online'],
]);
```

Product creation, update and read also expose optional open-string `vatexCode`.
The API accepts the 88 official CIUS-FR codes and stores legacy `VATEX-FR-261` /
`VATEX-FR-AUTOLIQ` as `VATEX-FR-CGI261-4` / `VATEX-FR-AE`; an older product may
still carry its earlier spelling. Product and quote resources keep returning
arrays, preserving fields and historical omissions.

Quote create/update `lines[]` accept optional `kind` and `supplyCategory`.
Quote responses return `items[].supplyCategory`; conversion copies the
declaration unchanged into the commercial draft invoice, and an omission stays
undeclared. A quote's indicative VAT does not decide its fiscal category.

```php
$quote = \Facturino\Quote::create([
    'customerId' => 'cus_1',
    'lines' => [[
        'description' => 'Online subscription', 'quantity' => '1',
        'unitPrice' => 10000, 'vatRate' => 2000, 'vatCode' => 'S', 'unit' => 'month',
        'kind' => 'service', 'supplyCategory' => 'electronically_supplied_services',
    ]],
]);
```

For a quote or draft service line with no declared `supplyCategory`, the engine
reads both `services` and `electronically_supplied_services`. If the complete
result is identical — VAT, category, mentions, local tax and all three obligation
axes — the decision is `final`, the line's `category` is `services`, and the
notice `supply_category_not_declared` explains the result. Otherwise it is
`pending_verification`, with issue `supply_category_not_declared`, no amounts and
`category: null` on that line. Declare its `supplyCategory` to resolve it. With
neither `kind` nor `supplyCategory`, no service reading is presumed: the line
stays pending. `kind: goods` can establish the `goods` category.

This applies to lines read from quotes and drafts. **Every line sent directly
to `POST /v1/tax-decisions` still requires `category`, under both sources.**
Historical decision lines keep their category. API contract date remains
2026-09-01.

## Quotes

Convert, decide, bind, finalize: ONE invoice throughout. A converted quote
yields a COMMERCIAL draft — it states the operation and no VAT
(`taxSource: null`). Bind a final decision to that same invoice, then finalize
it; never create a second one.

```php
// Clone a quote as a new draft (mirrors Invoice::clone)
$draft = \Facturino\Quote::clone('quo_xxx');

// Convert an accepted quote to a commercial draft invoice
$converted = \Facturino\Quote::convert('quo_xxx');

// Decide the VAT, then freeze it onto that same draft
$decision = \Facturino\TaxDecision::create($decisionInput, $idempotencyKey);
\Facturino\Invoice::bindTaxDecision($converted['invoiceId'], [
    'taxDecisionId' => $decision['id'],
    'decisionLines' => [['taxLineRef' => 'l1', 'unit' => 'unit']],
]);
\Facturino\Invoice::finalize($converted['invoiceId']);
```

## Filtering and expanding

The list and retrieve endpoints accept extra query parameters that pass
straight through to the API:

```php
// Invoices issued from a given quote
$invoices = \Facturino\Invoice::all(['convertedFrom' => 'quo_xxx']);

// Inline related resources on a single invoice. `expand` is
// comma-separated and accepts `customer`, `items.product` and
// `credit_notes`. With `credit_notes` the response gains
// `expanded.credit_notes` (array) and `expanded.net_balance` (string).
$invoice = \Facturino\Invoice::retrieve('inv_xxx', [
    'expand' => 'customer,credit_notes',
]);

// Product filters: q (name prefix), category, active
$products = \Facturino\Product::all([
    'q' => 'consult',
    'category' => 'services',
    'active' => true,
]);
```

## Contacts and roles

Each entry of a customer's `contacts[]` may carry a `role` of `billing`,
`technical` or `main`. The `billing` contact receives invoices by default.

```php
\Facturino\Customer::create([
    'name' => 'ACME Corp',
    'type' => 'company',
    'contacts' => [
        ['name' => 'Compta', 'email' => 'compta@acme.com', 'role' => 'billing'],
        ['name' => 'IT', 'email' => 'it@acme.com', 'role' => 'technical'],
    ],
]);
```

## Credit note numbering

`creditNoteSettings.numberingMode` controls how credit notes are
numbered: `separate` (default — their own number series) or `unified`
(credit notes share the invoice number series).

```php
\Facturino\Company::update('comp_xxx', [
    'creditNoteSettings' => ['numberingMode' => 'unified'],
]);
```

## Webhook verification

Verify incoming webhooks using HMAC-SHA256 with timing-safe comparison:

```php
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_FACTURINO_SIGNATURE'];
$endpointSecret = 'whsec_xxx'; // From your webhook endpoint

try {
    $event = \Facturino\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);

    switch ($event['type']) {
        case 'invoice.finalized':
            $invoice = $event['data'];
            // Handle finalized invoice
            break;
        case 'invoice.paid':
            // Handle payment
            break;
    }

    http_response_code(200);
    echo json_encode(['received' => true]);
} catch (\Facturino\Exception\InvalidRequestException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
```

## Idempotency

An `Idempotency-Key` protects the **replay of one request**. It is not a
deduplicator: the API never decides on its own that two requests "mean the same
thing".

- **Same key + same canonical body** — the first 2xx response is replayed
  verbatim, and the operation is not executed a second time.
- **Same key + different body** — `409 conflict`. A key belongs to a
  request, not to an endpoint.
- **Different keys** — two distinct operations, even with byte-identical bodies.
  Two requests describing the same operation are **not** deduplicated
  automatically; the key, and only the key, declares that two sends are the same
  attempt.
- **Canonical body** — JSON object keys are compared in a stable order, so
  reordering them does not change the request. Changing a value, adding or
  removing a field does. Array order is significant: two lines swapped are two
  different documents.
- **Failure before execution** (validation, read-only field, sanitisation)
  releases the key, so a corrected retry with the same key runs.
- **Business refusal during execution** is stored and replayed; the operation is
  not re-executed.
- **Scope** — 24 hours, per API key. `POST /v1/tax-decisions` additionally
  carries a durable business idempotency that never expires.

```php
// Same key + same body -> the first response, replayed.
$invoice = \Facturino\Invoice::create(
    ['customerId' => 'cus_xxx', 'lines' => [...]],
    'order-4821'
);

// Retry with new evidence is NOT idempotency. It takes a NEW decision on the
// same commercial operation: use a NEW key and link the previous decision.
$decision = \Facturino\TaxDecision::create(
    $operation + ['retryOfTaxDecisionId' => $suspended['id']],
    'order-4821-retry-1'
);
```

## Error handling

```php
use Facturino\Exception\AuthenticationException;
use Facturino\Exception\InvalidRequestException;
use Facturino\Exception\RateLimitException;
use Facturino\Exception\ApiException;

try {
    $invoice = \Facturino\Invoice::retrieve('inv_nonexistent');
} catch (AuthenticationException $e) {
    // Invalid API key (401)
    echo 'Auth error: ' . $e->getMessage();
} catch (RateLimitException $e) {
    // Rate limit exceeded (429) — SDK retries automatically up to 3 times
    echo 'Rate limited: ' . $e->getMessage();
} catch (InvalidRequestException $e) {
    // Client error (400-499)
    echo 'Error: ' . $e->getMessage();
    echo 'Code: ' . $e->getErrorCode();
    echo 'Param: ' . $e->getParam();
    echo 'Hint: ' . $e->getHint();
} catch (ApiException $e) {
    // Server error (500+) — SDK retries automatically up to 3 times
    echo 'API error: ' . $e->getMessage();
}
```

## Tax decisions

The full walkthrough lives in [Quick start](#quick-start). A decision is
immutable: it fixes the VAT, the exact `amountToCharge` and the reporting
obligations of one commercial operation, then never changes. Only a `final`
decision carries amounts, and the amount always comes from the decision —
never from a locally computed total.

`create()` requires an idempotency key (255 characters at most; the SDK checks
it before sending). The API answers `201` on creation and `200` when the same
key already produced that decision — both return the decision. Reusing the same
key with a different body answers `409` and throws `ConflictException`; it is
never retried.

### Optional: carrying the decision id through a PSP

These are examples, not requirements. If you collect through a PSP, keep the
decision id on the payment so step 4 can verify what was actually captured:
Stripe carries it in `metadata` (`facturino_tax_decision_id`), PayPal in
`custom_id` — and PayPal wants decimal units, so divide the centimes by 100.

### What a decision states

| Key | Meaning |
|---|---|
| `status` | `final` carries amounts; `pending_verification` names facts to complete; `unsupported` explains a refusal. Act only on `final`. |
| `amountToCharge` | Exact amount to debit, integer centimes. `null` unless final. |
| `totals` | `totalHT` / `totalVAT` / `totalTTC`, with optional `totalLocalTax` included in `totalTTC` and `amountToCharge`, integer centimes. `null` unless final. |
| `lines[].category` | Decided supply category; `null` only on a pending quote/draft line whose category was not declared (`supply_category_not_declared`). Direct decision requests always require `category` on every line. |
| `invoiceChannel` | `einvoicing` or `none` — whether the invoice travels the network. |
| `transactionReporting` | `ereporting`, `none` or `outside_scope`. |
| `paymentReporting` | `fr212`, `ereporting` or `none`. |
| `obligationBasis` | Read-only frozen transmission basis: `legal`, `voluntary_early_adoption` or `null` when none applies or historical information is unresolved. Historical decisions may omit it. |
| `settledObligations` | The axes French law settles DESPITE a non-final decision. `null` when final — the three axes above are then the settled ones. Each axis inside is `null` when it depends on the treatment that could not be concluded. It authorises nothing. |
| `foreignTaxReviewRequired` | A foreign tax may apply; review it outside Facturino. |
| `vies` | VIES status only (`valid`, `invalid`, `unavailable`, `invalid_format`). |
| `issues` | Facts to complete or reasons for refusal when the decision is not final. Each entry has a stable `code` and, when the code alone is not enough, a `detailCode`, a `reference` and the `lineId` concerned. |
| `notices` | What the decision retained and what the seller can do next. Always present, empty when there is nothing to report. Notices never stand for a missing fact: show them to the seller. |
| `customer.retainedVatNumber` | The VAT number the decision retained — the only one the document and its electronic invoice carry. `null` when none is retained. `customer.vatNumber` stays the number as declared. |
| `legalCategory` | Optional SIRENE consultation frozen with the decision: `source`, `siren`, open-string `status`, nullable open-string `legalCategoryCode`, `checkedAt` (`YYYY-MM-DD`). Absent when no consultation was due, including historical responses. |
| `obligationReasons` | Why each axis carries the obligation it does. |
| `expiresAt` / `expired` | Past this instant the decision no longer opens a payment. |

Facturino decides **French VAT and the matching French obligations**. It does
not provide worldwide tax compliance: when a foreign tax may apply, the decision
says so through `foreignTaxReviewRequired`. An operation whose `invoiceChannel`
is `none` is not deposited on a certified platform — its obligation, if any,
goes through e-reporting.

### Notices: what a decision retained

Always act only on a `final` decision, and read its `notices` too. They say what
the decision retained and what the seller can do next. They never stand for a
missing fact. Examples include destination VAT invoiced without a declared
mechanism, a consumer territory retained by two concordant items of different
kinds or by the same complete tax result on every possible territory, a VAT
number set aside because national registration establishes status, and unmodelled
local taxes declared and assumed by the integrator under `integration`.

The only legal presumption is art. 18(2), second subparagraph, of Implementing
Regulation (EU) 282/2011: telecommunications, broadcasting and electronically
supplied services to a buyer established in the EU VAT territory (Monaco
included) who communicated no VAT number. That buyer is treated as a non-taxable
person, with a notice and `customer.natureBasis: declared_business_not_evidenced`.
A communicated but unvalidated number leaves `eu_b2b_vat_number_not_validated`
pending. INSEE categories 7, 8 and 9 without `taxStatus` keep their refusal.

```php
if ($decision['status'] === 'final') {
    foreach ($decision['notices'] as $notice) {
        // `code` is stable; `detailCode`, `reference` and `lineId` are optional.
        echo $notice['code'], ' ', $notice['detailCode'] ?? '', ' ', $notice['message'], PHP_EOL;
    }
}
```

The list of codes can grow: treat an unknown code as a generic notice. Invoices
and credit notes freeze the notices of their decision in
`$invoice['taxSnapshot']['notices']`; a credit note repeats those of the invoice
it credits. The optional `Facturino\Model\TaxDecision` view hydrates the notices
as `Facturino\Model\TaxDecisionNotice` objects.

Invoices and credit notes also carry the read-only transmission basis in
`taxSnapshot.obligationBasis`: `legal`, `voluntary_early_adoption` or `null`.
The invoice freezes it with the decision; a credit note inherits the invoice's
snapshot. Historical snapshots may omit the key. Both model views preserve
the value, explicit nulls and omissions when serialized.

### Optional customer status and local taxes

Customer creation, update and responses accept optional `taxStatus`:
`taxable` or `non_taxable`, independently of the customer's `type`.
`Facturino\Model\Customer::$taxStatus` stays null when a historical response
omits it; serialization preserves the omission.

`customer.natureBasis: declared_non_taxable` means `taxStatus: non_taxable` was
declared. If that customer is a legal person carrying a VAT number, VIES is
consulted: a valid number makes its services follow taxable-person rules (CGI
art. 259-0); an unreachable VIES leaves `vies_unavailable` pending. Associations,
public bodies and foundations (INSEE categories 7, 8 and 9) without `taxStatus`
leave `business_status_not_evidenced`, `detailCode: tax_status_not_declared` pending.
If SIRENE could not give the category, the detail is `legal_category_unresolved`.
Codes, `detailCode` and `natureBasis` stay open strings.

`Facturino\Model\TaxDecision::$legalCategory` hydrates the optional consultation
as `Facturino\Model\TaxDecisionLegalCategory`. `source` is `sirene`; `siren` is
the nine-digit SIREN; `status` is `found`, `not_found` or `unavailable` today;
`legalCategoryCode` is a four-digit INSEE code or null; `checkedAt` is the civil
date of the consultation or reused server cache result. Status and code remain
open strings. The property stays null for historical responses without the key,
and serialization preserves its absence.
`not_found` means the registry has no such SIREN; `unavailable` means it could
not be reached. Both leave `legal_category_unresolved` pending and establish
no taxable status.

```php
\Facturino\Customer::update('cus_1', ['taxStatus' => 'taxable']);
```

A decision's `sellerProfile` may carry `localTaxRegistrations`, a list of
registrations with this shape:

```php
$registration = ['territory' => 'MF', 'reference' => 'Registration reference',
    'effectiveFrom' => '2026-09-01', 'effectiveTo' => null];
```

Both dates use `YYYY-MM-DD`; `effectiveTo` may be null. `territory` is an open
string (`MF` today). An input line in `TaxDecision::create()` may carry optional
`localTaxExemption`, also an open string; the documented values today are
`cgism_253` and `not_used_in_saint_martin`.

Under both sources, a registration in force gives Saint-Martin TGCA at 4% per
line; a declared exemption gives 0 with the mention. Without either, the decision
is `pending_verification`, `com_taaf_supplier_local_tax_liable`.

Decision response lines (`lines[]`) and invoice items (`items[]`) may carry
the same optional `localTax` object:

```php
$localTax = ['code' => 'tgca_mf', 'rateCentipercent' => 400, 'amount' => 400,
    'reference' => 'CGIsm art. 252', 'exemption' => null];
```

The rate is an integer in centipercent (`400` = 4%); `amount` is an integer in
centimes. `code` and `exemption` accept future strings; `exemption` may be null.
Decision and invoice `totals` may also carry `totalLocalTax`, an integer in
centimes, included in `totalTTC` and the amount to collect. These fields are
separate from French VAT. Historical responses may omit them: preserve the
distinction between absence, a zero amount and a null
exemption. Non-final decision totals remain null. The API contract date stays
`2026-09-01`.

### Missing evidence, then a retry

A missing fact leaves `pending_verification`, without amounts, with the fact
and remedy in `issues`: `business_status_not_evidenced`,
`eu_b2b_vat_number_not_validated`, `non_eu_business_status_not_evidenced`,
`vies_unavailable`, `location_evidence_insufficient`,
`location_evidence_contradictory`, `location_evidence_relief_undetermined`,
`eu_threshold_state_missing`, `goods_movement_unknown` or
`com_taaf_supplier_local_tax_liable`. An EU address missing the postal code
needed to split VAT territories is refused under both sources with
`destination_regional_scope_undetermined`.
Supply the evidence and retry the SAME operation. An `unsupported` decision
explains why the case is refused; act only on a `final` decision.
Send the territorial **signal**, never the raw one: a
country and, where the territory needs it, a postal code — not an IP address, a
PSP payload or bank account details.

```php
$params['retryOfTaxDecisionId'] = $pending['id'];
$params['locationEvidence'] = [[
    'kind'        => 'billing_address',
    'country'     => 'FR',
    'postalCode'  => '75002',
    'thirdParty'  => false,
    'source'      => 'declared',
    'collectedAt' => '2026-09-15',
]];
```

## Three status axes

A document has three states that do not follow from one another. The historical
`status` key stays populated as their projection.

```php
$invoice['documentStatus'];     // draft | finalized | cancelled
$invoice['transmissionStatus']; // not_applicable | pending | sending | deposited | transmitted | approved | rejected
$invoice['transmissionDetail']; // available | received | suspended | refused | null
$invoice['paymentStatus'];      // unpaid | partially_paid | paid | partially_refunded | refunded
```

Recording a payment never moves the transmission axis, and a refund does not
erase the collection that happened.

## Credit notes

A credit note credits fractions of the original invoice's frozen lines through
`creditedLines`. The rate, the category, the VATEX code and the legal mention
are inherited from the invoice's snapshot, never restated — there is no way to
state VAT on a credit note.

```php
\Facturino\CreditNote::create([
    'relatedInvoiceId' => 'inv_1',
    'creditNoteType'   => 'partial',
    'reasonCode'       => 'quality',
    // Either `quantity` or `amountTTC`, never both. Omit both to credit the
    // line's whole remaining balance.
    'creditedLines'    => [['taxLineRef' => 'abo-pro', 'amountTTC' => 1200]],
]);
```

## Recurring schedules

`taxInputs` (with its `taxSource`) is required. A recurrence stores no
decision: each occurrence is decided on its own generation date, so a schedule
created today does not carry this quarter's rules into next year.

```php
\Facturino\RecurringInvoice::create([
    'customerId'      => 'cus_1',
    'frequency'       => 'monthly',
    'startDate'       => '2026-09-01',
    'nextGenerationDate' => '2026-09-01',
    'taxInputs'       => [
        'taxSource' => 'facturino',
        'priceMode' => 'tax_exclusive',
        'lines'     => [[
            'reference' => 'abo-pro', 'description' => 'Abonnement Pro',
            'category' => 'electronically_supplied_services',
            'rateCategory' => 'standard', 'unitAmount' => 2900,
            'quantity' => '1', 'unit' => 'month',
        ]],
    ],
    'templateInvoice' => ['paymentTermsDays' => 30],
]);
```

## Amounts and rates

The API uses integers to avoid floating-point precision issues:

- **Amounts**: integer centimes. `10000` = 100.00 EUR
- **VAT rates**: integer centipercent. `2000` = 20.00%

```php
// 150.00 EUR HT with 20% VAT
$item = [
    'description' => 'Service',
    'quantity' => '1',
    'unit' => 'flat_rate',
    'unitPrice' => 15000,   // 150.00 EUR
    'vatRate' => 2000,      // 20.00%
    'vatCode' => 'S',
];
```

## Async jobs

Some operations (PDF generation, FEC export) return a job object:

```php
$result = \Facturino\Invoice::getPdf('inv_xxx');

if (isset($result['url'])) {
    // PDF already exists — download from signed URL
    $pdfUrl = $result['url'];
} else {
    // Async generation — poll the job
    $jobId = $result['id'];
    do {
        sleep(2);
        $job = \Facturino\Job::retrieve($jobId);
    } while (in_array($job['status'], ['pending', 'processing'], true));

    if ($job['status'] === 'completed') {
        $pdfUrl = $job['url'];
    }
}
```

An invoice export (`\Facturino\Export::exportInvoices()`) returns a job of type
`invoice_export`. Without `statuses`, it includes credit notes (`avoirs/` folder
of the ZIP), and `manifest.csv` carries additional columns. The archive holds at
most 5 000 pieces; beyond that the job carries `truncated: true`. Once finished,
the job also exposes `included` and `failed` (pieces in the archive and pieces
that could not be generated).

`\Facturino\Invoice::generateAuditTrailPdf()` is not available yet: the API
answers `501 not_implemented` and creates no job. Use the JSON audit trail
(`getAuditTrail`) meanwhile.

## Sandbox

Use test-mode API keys (`fac_test_*`) for development:

```php
\Facturino\Facturino::setApiKey('fac_test_xxx');

// Reset test data and load fixtures
\Facturino\Sandbox::resetData();

// Simulate PA status changes
\Facturino\Sandbox::simulateStatus('inv_xxx', 'deposited');
\Facturino\Sandbox::simulateStatus('inv_xxx', 'approved');
```

## Configuration

```php
// Override API base URL (for testing or proxying)
\Facturino\Facturino::setApiBase('https://localhost:5001/api');
```

## Development

```bash
git clone https://github.com/facturino/facturino-php.git
cd facturino-php
composer install
vendor/bin/phpunit
```

## License

MIT. See [LICENSE](LICENSE).
