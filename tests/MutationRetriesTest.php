<?php

declare(strict_types=1);

namespace Facturino\Tests;

use Facturino\Facturino;
use Facturino\HttpClient;
use Facturino\Exception\ApiException;
use Facturino\Exception\RateLimitException;
use PHPUnit\Framework\TestCase;

final class MutationRetriesTest extends TestCase
{
    protected function setUp(): void { Facturino::reset(); Facturino::setApiKey('fac_test_local'); }
    protected function tearDown(): void { Facturino::reset(); }

    /** A real local HTTP peer. The child owns the fake durable movement map. */
    private function withServer(string $mode, callable $test): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $this->assertNotFalse($socket, $error);
        $address = stream_socket_get_name($socket, false);
        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid);
        if ($pid === 0) {
            $movements = [];
            $keys = [];
            $started = microtime(true);
            // Every response closes the connection, so all retries reach this loop.
            for ($attempt = 0; $attempt < 4; $attempt++) {
                $connection = @stream_socket_accept($socket, 8);
                if ($connection === false) { break; }
                $headers = [];
                fgets($connection);
                while (($line = fgets($connection)) !== false && trim($line) !== '') {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($name))] = trim($value);
                }
                $length = (int) ($headers['content-length'] ?? 0);
                while ($length > 0 && !feof($connection)) { $length -= strlen(fread($connection, $length)); }
                $key = $headers['idempotency-key'] ?? '';
                $keys[] = $key;
                if (!isset($movements[$key])) { $movements[$key] = count($movements) + 1; }
                if ($mode === 'cut' && $attempt === 0) { fclose($connection); continue; }
                $status = 200;
                $retry = '';
                $body = json_encode(['id' => $movements[$key], 'keys' => $keys, 'mutations' => count($movements), 'elapsed' => microtime(true) - $started]);
                if ($mode === 'budget' || $mode === 'unkeyed' || ($mode === 'wait' && $attempt === 0)) {
                    $status = 429;
                    $retry = 'Retry-After: ' . ($mode === 'wait' ? '1' : '90') . "\r\n";
                    $body = '{"error":{"type":"rate_limit_error","code":"rate_limit_exceeded","message":"Slow down"}}';
                }
                fwrite($connection, "HTTP/1.1 $status Response\r\nContent-Type: application/json\r\nConnection: close\r\nContent-Length: " . strlen($body) . "\r\n" . $retry . "\r\n" . $body);
                fclose($connection);
            }
            fclose($socket);
            exit(0);
        }
        fclose($socket);
        Facturino::setApiBase('http://' . $address);
        try { $test(); } finally { posix_kill($pid, SIGTERM); pcntl_waitpid($pid, $status); }
    }

    public function testOneMutationWhenResponseIsCutAfterApply(): void
    {
        $this->withServer('cut', function (): void {
            $result = HttpClient::request('POST', '/payments', ['amount' => 1188]);
            $this->assertSame(1, $result['id']);
            $this->assertSame(1, $result['mutations']);
            $this->assertCount(2, $result['keys']);
            $this->assertNotEmpty($result['keys'][0]);
            $this->assertSame($result['keys'][0], $result['keys'][1]);
            $next = HttpClient::request('POST', '/payments', ['amount' => 1188]);
            $this->assertSame(2, $next['mutations']);
        });
    }

    public function testLongRetryAfterIsNotCapped(): void
    {
        $method = new \ReflectionMethod(HttpClient::class, 'computeRetryDelay');
        $this->assertSame(90000, $method->invoke(null, 0, ['retry-after' => '90']));
        $this->withServer('budget', function (): void {
            $started = microtime(true);
            try { HttpClient::request('POST', '/payments', []); $this->fail('Expected 429'); }
            catch (RateLimitException $error) {
                $this->assertSame(429, $error->getHttpStatus());
                $this->assertLessThan(1.0, microtime(true) - $started);
            }
        });
    }

    public function testExplicitKeySurvivesTheFullWait(): void
    {
        Facturino::setAutoIdempotency(false);
        $this->withServer('wait', function (): void {
            $result = HttpClient::request('POST', '/payments', [], null, ['Idempotency-Key' => 'caller-key']);
            $this->assertSame(['caller-key', 'caller-key'], $result['keys']);
            $this->assertGreaterThanOrEqual(1.0, $result['elapsed']);
        });
    }

    public function testUnkeyedPostIsNeverRetried(): void
    {
        Facturino::setAutoIdempotency(false);
        $this->withServer('cut', function (): void {
            try { HttpClient::request('POST', '/payments', []); $this->fail('Expected lost response error'); }
            catch (ApiException $error) { $this->assertSame(0, $error->getHttpStatus()); }
        });
    }
}
