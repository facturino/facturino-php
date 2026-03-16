# Facturino PHP SDK

Official PHP client for the [Facturino API](https://facturino.com/docs/api) — French e-invoicing made simple.

[![Tests](https://github.com/facturino/facturino-php/actions/workflows/test.yml/badge.svg)](https://github.com/facturino/facturino-php/actions/workflows/test.yml)
[![Latest Version](https://img.shields.io/packagist/v/facturino/facturino-php.svg)](https://packagist.org/packages/facturino/facturino-php)
[![PHP Version](https://img.shields.io/packagist/php-v/facturino/facturino-php.svg)](https://packagist.org/packages/facturino/facturino-php)
[![License](https://img.shields.io/packagist/l/facturino/facturino-php.svg)](https://github.com/facturino/facturino-php/blob/master/LICENSE)

## Requirements

- PHP 8.1+
- ext-curl
- ext-json

## Installation

```bash
composer require facturino/facturino-php
```

## Quick start

```php
require 'vendor/autoload.php';

\Facturino\Facturino::setApiKey('fac_test_xxx');

// Create a customer
$customer = \Facturino\Customer::create([
    'name' => 'ACME Corp',
    'type' => 'company',
    'email' => 'billing@acme.com',
    'siret' => '12345678901234',
    'address' => [
        'line1' => '42 rue des Acacias',
        'postalCode' => '75001',
        'city' => 'Paris',
        'country' => 'FR',
    ],
]);

// Create and finalize an invoice
$invoice = \Facturino\Invoice::create([
    'customer' => $customer['id'],
    'items' => [[
        'description' => 'Consulting',
        'quantity' => 1,
        'unit_price' => 10000,  // 100.00 EUR in centimes
        'vat_rate' => 2000,     // 20.00% in centipercent
    ]],
    'dates' => [
        'issued' => '2026-01-15',
        'due' => '2026-02-15',
    ],
]);
$invoice = \Facturino\Invoice::finalize($invoice['id']);

// Send to PA (Plateforme Agreee)
\Facturino\Invoice::send($invoice['id']);

// Record a payment
\Facturino\Payment::create($invoice['id'], [
    'amount' => 12000,    // 120.00 EUR (100 HT + 20 TVA)
    'method' => 'transfer',
    'paidAt' => '2026-02-10',
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
| Member | `\Facturino\Member` | `/v1/members` |
| API Key | `\Facturino\ApiKey` | `/v1/api-keys` |
| Export | `\Facturino\Export` | `/v1/exports` |
| E-reporting | `\Facturino\Ereporting` | `/v1/ereporting/declarations` |
| Job | `\Facturino\Job` | `/v1/jobs` |
| Sandbox | `\Facturino\Sandbox` | `/v1/sandbox` |

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

Pass an idempotency key on POST requests to safely retry:

```php
$invoice = \Facturino\Invoice::create(
    ['customer' => 'cus_xxx', 'items' => [...]],
    'idem_unique_request_id_123'
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

## Amounts and rates

The API uses integers to avoid floating-point precision issues:

- **Amounts**: integer centimes. `10000` = 100.00 EUR
- **VAT rates**: integer centipercent. `2000` = 20.00%

```php
// 150.00 EUR HT with 20% VAT
$item = [
    'description' => 'Service',
    'quantity' => 1,
    'unit_price' => 15000,  // 150.00 EUR
    'vat_rate' => 2000,     // 20.00%
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
        $pdfUrl = $job['download_url'];
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
