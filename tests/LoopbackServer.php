<?php

declare(strict_types=1);

namespace Facturino\Tests;

/**
 * A one-request-at-a-time HTTP recorder on the loopback interface.
 *
 * The SDK builds its request with curl, so asserting the path, the headers and
 * the exact JSON body requires something to receive it. This binds 127.0.0.1
 * only — no call ever leaves the machine.
 */
final class LoopbackServer
{
    private int $port;

    /** @var resource|null */
    private $process = null;

    private string $captureFile;

    private string $responseFile;

    public function __construct()
    {
        $this->port = self::findFreePort();
        $this->captureFile = tempnam(sys_get_temp_dir(), 'fac-capture-');
        $this->responseFile = tempnam(sys_get_temp_dir(), 'fac-response-');
        $this->respondWith(200, '{}');

        $descriptors = [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
        $command = sprintf(
            'php -S 127.0.0.1:%d %s',
            $this->port,
            escapeshellarg(__DIR__ . '/server/router.php'),
        );
        $this->process = proc_open($command, $descriptors, $pipes, null, [
            'FACTURINO_TEST_CAPTURE' => $this->captureFile,
            'FACTURINO_TEST_RESPONSE' => $this->responseFile,
            'PATH' => getenv('PATH') ?: '/usr/bin:/bin',
        ]);

        $this->waitUntilReady();
    }

    public function baseUrl(): string
    {
        return 'http://127.0.0.1:' . $this->port;
    }

    /** Set the canned response returned to the next request. */
    public function respondWith(int $status, string $body): void
    {
        file_put_contents($this->responseFile, json_encode(['status' => $status, 'body' => $body]));
    }

    /**
     * @return array<string, mixed> the last request the server received
     */
    public function lastRequest(): array
    {
        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($this->captureFile))));
        if ($lines === []) {
            throw new \RuntimeException('no request was captured');
        }
        return json_decode(end($lines), true);
    }

    /** @return list<array<string, mixed>> every request received so far */
    public function requests(): array
    {
        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($this->captureFile))));
        return array_map(static fn (string $line): array => json_decode($line, true), $lines);
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
        @unlink($this->captureFile);
        @unlink($this->responseFile);
    }

    private function waitUntilReady(): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $connection = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.1);
            if (is_resource($connection)) {
                fclose($connection);
                return;
            }
            usleep(50_000);
        }
        throw new \RuntimeException('the loopback server did not start');
    }

    private static function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            throw new \RuntimeException('cannot allocate a port: ' . $errstr);
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        return (int) substr($name, strrpos($name, ':') + 1);
    }
}
