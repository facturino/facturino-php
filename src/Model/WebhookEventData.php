<?php

declare(strict_types=1);

namespace Facturino\Model;

/**
 * Optional typed view of the API response. Missing historical fields stay null;
 * jsonSerialize() preserves the original payload, including unknown fields.
 */
final class WebhookEventData implements \JsonSerializable
{
    /** Original payload, including absent/null distinctions and unknown fields. */
    public readonly array $rawData;
    public readonly ?string $paStatus;
    public readonly ?string $paInvoiceId;
    public readonly ?string $id;
    public readonly ?string $object;
    public readonly ?string $status;
    public readonly ?string $previous_status;
    public readonly ?bool $livemode;
    public readonly ?string $number;
    public readonly ?string $documentStatus;
    public readonly ?string $transmissionStatus;
    public readonly ?string $transmissionDetail;
    public readonly ?string $paymentStatus;
    public readonly ?string $paErrorCode;
    public readonly ?string $rejectionReason;
    public readonly ?PaRejectionCategory $rejectionCategory;
    public readonly ?string $rejectionCode;
    public readonly ?PaRejectionSource $rejectionSource;
    public readonly ?string $relatedInvoiceId;
    public readonly ?string $relatedInvoiceNumber;
    public readonly ?array $metadata;
    public readonly ?string $amount;
    public readonly ?string $total_paid;
    public readonly ?string $total_due;
    public readonly ?string $total;
    public readonly ?string $amountDue;

    public readonly ?string $invoiceId;
    public readonly ?string $paymentId;
    public readonly ?string $method;
    public readonly ?string $pa_invoice_id;
    public readonly ?string $sender_siret;
    public readonly ?string $sender_name;
    public readonly ?string $total_ht;
    public readonly ?string $total_tva;
    public readonly ?string $total_ttc;
    public readonly ?string $recurringInvoiceId;
    public readonly ?string $error;
    public readonly ?int $count;
    public readonly ?string $plan;
    public readonly ?string $stripeSubscriptionId;
    public readonly ?string $reason;
    public readonly ?int $attempt;
    public readonly ?string $pausedUntil;
    public readonly ?string $period;
    public readonly ?string $type;

    /** @param array<string, mixed> $data */
    private function __construct(array $data)
    {
        $this->rawData = $data;
        $this->paStatus = $data['paStatus'] ?? null;
        $this->paInvoiceId = $data['paInvoiceId'] ?? null;
        $this->invoiceId = $data['invoiceId'] ?? null;
        $this->paymentId = $data['paymentId'] ?? null;
        $this->method = $data['method'] ?? null;
        $this->pa_invoice_id = $data['pa_invoice_id'] ?? null;
        $this->sender_siret = $data['sender_siret'] ?? null;
        $this->sender_name = $data['sender_name'] ?? null;
        $this->total_ht = $data['total_ht'] ?? null;
        $this->total_tva = $data['total_tva'] ?? null;
        $this->total_ttc = $data['total_ttc'] ?? null;
        $this->recurringInvoiceId = $data['recurringInvoiceId'] ?? null;
        $this->error = $data['error'] ?? null;
        $this->count = $data['count'] ?? null;
        $this->plan = $data['plan'] ?? null;
        $this->stripeSubscriptionId = $data['stripeSubscriptionId'] ?? null;
        $this->reason = $data['reason'] ?? null;
        $this->attempt = $data['attempt'] ?? null;
        $this->pausedUntil = $data['pausedUntil'] ?? null;
        $this->period = $data['period'] ?? null;
        $this->type = $data['type'] ?? null;

        $this->id = isset($data['id']) ? $data['id'] : null;
        $this->object = isset($data['object']) ? $data['object'] : null;
        $this->status = isset($data['status']) ? $data['status'] : null;
        $this->previous_status = isset($data['previous_status']) ? $data['previous_status'] : null;
        $this->livemode = isset($data['livemode']) ? $data['livemode'] : null;
        $this->number = isset($data['number']) ? $data['number'] : null;
        $this->documentStatus = isset($data['documentStatus']) ? $data['documentStatus'] : null;
        $this->transmissionStatus = isset($data['transmissionStatus']) ? $data['transmissionStatus'] : null;
        $this->transmissionDetail = isset($data['transmissionDetail']) ? $data['transmissionDetail'] : null;
        $this->paymentStatus = isset($data['paymentStatus']) ? $data['paymentStatus'] : null;
        $this->paErrorCode = isset($data['paErrorCode']) ? $data['paErrorCode'] : null;
        $this->rejectionReason = isset($data['rejectionReason']) ? $data['rejectionReason'] : null;
        $this->rejectionCategory = isset($data['rejectionCategory']) ? PaRejectionCategory::tryFrom($data['rejectionCategory']) : null;
        $this->rejectionCode = isset($data['rejectionCode']) ? $data['rejectionCode'] : null;
        $this->rejectionSource = isset($data['rejectionSource']) ? PaRejectionSource::tryFrom($data['rejectionSource']) : null;
        $this->relatedInvoiceId = isset($data['relatedInvoiceId']) ? $data['relatedInvoiceId'] : null;
        $this->relatedInvoiceNumber = isset($data['relatedInvoiceNumber']) ? $data['relatedInvoiceNumber'] : null;
        $this->metadata = isset($data['metadata']) ? $data['metadata'] : null;
        $this->amount = isset($data['amount']) ? $data['amount'] : null;
        $this->total_paid = isset($data['total_paid']) ? $data['total_paid'] : null;
        $this->total_due = isset($data['total_due']) ? $data['total_due'] : null;
        $this->total = isset($data['total']) ? $data['total'] : null;
        $this->amountDue = isset($data['amountDue']) ? $data['amountDue'] : null;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }
}
