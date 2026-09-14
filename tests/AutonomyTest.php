<?php
declare(strict_types=1);
namespace Facturino\Tests;
use Facturino\Model;
use PHPUnit\Framework\TestCase;
final class AutonomyTest extends TestCase {
 public function testAutonomousObligationContract(): void {
  $c = json_decode(file_get_contents(__DIR__ . '/fixtures/contract/autonomy.json'), true, 512, JSON_THROW_ON_ERROR);
  $this->assertSame('facturino', Model\ObligationFollowUp::fromArray($c['obligation'])->owner);
  $this->assertNull(Model\ObligationFollowUp::fromArray($c['completed'])->owner);
  $this->assertSame('retry', Model\InvoiceEinvoicing::fromArray($c['einvoicing'])->obligation->action);
  $this->assertSame('retry', Model\PaymentCollectionStatus::fromArray($c['fr212'])->obligation->action);
  foreach ($c['events'] as $raw) {
   $event = Model\WebhookEvent::fromArray($raw);
   $this->assertSame('retry', $event->data->obligation->action);
   $this->assertSame(Model\EventDataFamily::Obligation, Model\EventDataFamily::forEventType($raw['type']));
   $this->assertSame($raw, $event->jsonSerialize());
  }
  $this->assertNull(Model\InvoiceEinvoicing::fromArray([])->obligation);
  $this->assertSame(['obligation'=>null], Model\InvoiceEinvoicing::fromArray(['obligation'=>null])->jsonSerialize());
 }
}
