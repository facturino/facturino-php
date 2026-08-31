<?php

declare(strict_types=1);

/**
 * Loopback request recorder used by the contract tests.
 *
 * It never reaches the network: PHP's built-in server binds 127.0.0.1 only. Each
 * request is appended to the capture file so a test can assert the exact path,
 * headers and body the SDK produced, and the canned response is read from
 * FACTURINO_TEST_RESPONSE.
 */
$captureFile = getenv('FACTURINO_TEST_CAPTURE');
$responseFile = getenv('FACTURINO_TEST_RESPONSE');

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_')) {
        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
        $headers[$name] = $value;
    }
}

$captured = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'path' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
    'query' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY),
    'headers' => $headers,
    'body' => file_get_contents('php://input'),
];

if ($captureFile !== false && $captureFile !== '') {
    file_put_contents($captureFile, json_encode($captured) . "\n", FILE_APPEND);
}

$canned = ['status' => 200, 'body' => '{}'];
if ($responseFile !== false && $responseFile !== '' && is_file($responseFile)) {
    $decoded = json_decode((string) file_get_contents($responseFile), true);
    if (is_array($decoded)) {
        $canned = $decoded + $canned;
    }
}

http_response_code((int) $canned['status']);
header('Content-Type: application/json');
echo $canned['body'];
