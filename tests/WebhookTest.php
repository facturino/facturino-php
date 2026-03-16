<?php

declare(strict_types=1);

namespace Facturino\Tests;

use Facturino\Util\Webhook;
use Facturino\Exception\InvalidRequestException;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test_secret_key_for_unit_tests';

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testConstructEventWithValidSignature(): void
    {
        $payload = json_encode([
            'id' => 'evt_test123',
            'object' => 'event',
            'type' => 'invoice.finalized',
            'data' => ['id' => 'inv_test456', 'status' => 'finalized'],
        ]);

        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $hmac = hash_hmac('sha256', $signedPayload, self::SECRET);
        $header = 't=' . $timestamp . ',v1=' . $hmac;

        $event = Webhook::constructEvent($payload, $header, self::SECRET);

        $this->assertEquals('evt_test123', $event['id']);
        $this->assertEquals('invoice.finalized', $event['type']);
        $this->assertEquals('inv_test456', $event['data']['id']);
    }

    public function testConstructEventRejectsInvalidSignature(): void
    {
        $payload = json_encode(['id' => 'evt_test123', 'type' => 'invoice.created']);

        $timestamp = time();
        $header = 't=' . $timestamp . ',v1=invalid_signature_hex';

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('signature verification failed');

        Webhook::constructEvent($payload, $header, self::SECRET);
    }

    public function testConstructEventRejectsExpiredTimestamp(): void
    {
        $payload = json_encode(['id' => 'evt_test123']);

        // Timestamp 10 minutes ago (beyond 5-minute tolerance)
        $timestamp = time() - 600;
        $signedPayload = $timestamp . '.' . $payload;
        $hmac = hash_hmac('sha256', $signedPayload, self::SECRET);
        $header = 't=' . $timestamp . ',v1=' . $hmac;

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('tolerance zone');

        Webhook::constructEvent($payload, $header, self::SECRET);
    }

    public function testConstructEventRejectsMissingTimestamp(): void
    {
        $payload = json_encode(['id' => 'evt_test123']);
        $header = 'v1=some_signature';

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('timestamp');

        Webhook::constructEvent($payload, $header, self::SECRET);
    }

    public function testConstructEventRejectsMissingSignature(): void
    {
        $payload = json_encode(['id' => 'evt_test123']);
        $header = 't=' . time();

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('No v1 signature');

        Webhook::constructEvent($payload, $header, self::SECRET);
    }

    public function testConstructEventRejectsInvalidJson(): void
    {
        $payload = 'not valid json {{{';

        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $hmac = hash_hmac('sha256', $signedPayload, self::SECRET);
        $header = 't=' . $timestamp . ',v1=' . $hmac;

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('unable to parse JSON');

        Webhook::constructEvent($payload, $header, self::SECRET);
    }

    public function testGenerateSignature(): void
    {
        $payload = '{"id":"evt_test"}';
        $timestamp = 1700000000;

        $header = Webhook::generateSignature($payload, self::SECRET, $timestamp);

        $this->assertStringStartsWith('t=1700000000,v1=', $header);

        // Verify the generated signature is valid
        $parts = explode(',', $header);
        $hmacPart = substr($parts[1], 3); // remove 'v1='

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, self::SECRET);
        $this->assertEquals($expected, $hmacPart);
    }

    public function testConstructEventWithCustomTolerance(): void
    {
        $payload = json_encode(['id' => 'evt_test123']);

        // Timestamp 4 minutes ago
        $timestamp = time() - 240;
        $signedPayload = $timestamp . '.' . $payload;
        $hmac = hash_hmac('sha256', $signedPayload, self::SECRET);
        $header = 't=' . $timestamp . ',v1=' . $hmac;

        // With default 300s tolerance, this should pass
        $event = Webhook::constructEvent($payload, $header, self::SECRET, 300);
        $this->assertEquals('evt_test123', $event['id']);

        // With 60s tolerance, this should fail
        $this->expectException(InvalidRequestException::class);
        Webhook::constructEvent($payload, $header, self::SECRET, 60);
    }

    public function testVerifySignatureWithMultipleV1Signatures(): void
    {
        $payload = json_encode(['id' => 'evt_test123']);

        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $correctHmac = hash_hmac('sha256', $signedPayload, self::SECRET);

        // Header with old signature first, then correct one
        $header = 't=' . $timestamp . ',v1=old_invalid_sig,v1=' . $correctHmac;

        // Should not throw — one of the v1 signatures matches
        Webhook::verifySignature($payload, $header, self::SECRET);
        $this->assertTrue(true); // If we get here, verification passed
    }

    public function testTimingSafeComparison(): void
    {
        // Nearly-matching signature must still fail (timing-safe comparison).
        $payload = json_encode(['id' => 'evt_test123']);

        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $correctHmac = hash_hmac('sha256', $signedPayload, self::SECRET);

        // Flip one character in the signature
        $wrongHmac = substr($correctHmac, 0, -1) . ($correctHmac[-1] === 'a' ? 'b' : 'a');
        $header = 't=' . $timestamp . ',v1=' . $wrongHmac;

        $this->expectException(InvalidRequestException::class);
        Webhook::constructEvent($payload, $header, self::SECRET);
    }
}
