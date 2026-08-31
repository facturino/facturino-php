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
if ($decision['status'] !== 'final') {
    return askForMissingEvidence($decision['issues']);
}

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

**Immediate collection** — capture the decided amount, verify, then finalize:

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

\Facturino\Invoice::finalize($invoice['id']);

// Record the REAL payment — its real date, method and the settlement's
// financial reference, never the decision id
// (transfer, card, check, cash, direct_debit, sepa, paypal or other).
\Facturino\Payment::create($invoice['id'], [
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
- **Same key + different body** — `409 idempotency_error`. A key belongs to a
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
| `status` | `final`, `pending_verification` or `unsupported`. Only `final` carries amounts. |
| `amountToCharge` | Exact amount to debit, integer centimes. `null` unless final. |
| `totals` | `totalHT` / `totalVAT` / `totalTTC`, integer centimes. `null` unless final. |
| `invoiceChannel` | `einvoicing` or `none` — whether the invoice travels the network. |
| `transactionReporting` | `ereporting`, `none` or `outside_scope`. |
| `paymentReporting` | `fr212`, `ereporting` or `none`. |
| `foreignTaxReviewRequired` | A foreign tax may apply; review it outside Facturino. |
| `vies` | VIES status only (`valid`, `invalid`, `unavailable`, `invalid_format`). |
| `issues` | What is missing, when the decision is not final. |
| `obligationReasons` | Why each axis carries the obligation it does. |
| `expiresAt` / `expired` | Past this instant the decision no longer opens a payment. |

Facturino decides **French VAT and the matching French obligations**. It does
not provide worldwide tax compliance: when a foreign tax may apply, the decision
says so through `foreignTaxReviewRequired`. An operation whose `invoiceChannel`
is `none` is not deposited on a certified platform — its obligation, if any,
goes through e-reporting.

### Missing evidence, then a retry

Supply the evidence and retry the SAME operation. Send the territorial
**signal**, never the raw one: a country and, where the territory needs it, a
postal code — not an IP address, a PSP payload or bank account details.

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
    } while ($job['status'] === 'pending');

    if ($job['status'] === 'completed') {
        $pdfUrl = $job['url'];
    }
}
```

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
