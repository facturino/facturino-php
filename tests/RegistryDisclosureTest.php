<?php
 declare(strict_types=1);
 namespace Facturino\Tests;
 use Facturino\Facturino;
 use Facturino\Customer;
 use PHPUnit\Framework\TestCase;
 final class RegistryDisclosureTest extends TestCase {
 public function testRegistryDisclosureIsPreserved(): void {
  $body = file_get_contents(__DIR__ . '/fixtures/contract/registry-disclosure.json');
  $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
  $this->assertNotFalse($socket, $error);
  $address = stream_socket_get_name($socket, false);
  $pid = pcntl_fork(); $this->assertNotSame(-1, $pid);
  if ($pid === 0) {
   $connection = stream_socket_accept($socket, 8);
   if ($connection !== false) {
    $headers = []; fgets($connection);
    while (($line = fgets($connection)) !== false && trim($line) !== '') {
     [$name, $value] = explode(':', $line, 2); $headers[strtolower(trim($name))] = trim($value);
    }
    $remaining = (int) ($headers['content-length'] ?? 0);
    while ($remaining > 0 && !feof($connection)) { $remaining -= strlen(fread($connection, $remaining)); }
    fwrite($connection, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nConnection: close\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body); fclose($connection);
   }
   fclose($socket); exit(0);
  }
  fclose($socket); Facturino::setApiKey('fac_test_fixture'); Facturino::setApiBase('http://' . $address);
  try { $actual = Customer::lookup(['siret' => '00012345500008']); $this->assertSame(json_decode($body, true), $actual); $this->assertSame('', $actual['data']['name']); $this->assertSame('P', $actual['data']['disclosure']['status']); }
  finally { Facturino::reset(); posix_kill($pid, SIGTERM); pcntl_waitpid($pid, $status); }
 }
 }
