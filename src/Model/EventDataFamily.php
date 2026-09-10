<?php

declare(strict_types=1);

namespace Facturino\Model;

/** The discriminator of a payload. Unknown future event types return null. */
enum EventDataFamily: string
{
    case Invoice = 'Invoice';
    case IncomingInvoice = 'IncomingInvoice';
    case Quote = 'Quote';
    case CreditNote = 'CreditNote';
    case Customer = 'Customer';
    case PaymentCreated = 'PaymentCreated';
    case PaymentReceived = 'PaymentReceived';
    case Ereporting = 'Ereporting';
    case RecurringGenerated = 'RecurringGenerated';
    case RecurringFailed = 'RecurringFailed';
    case Export = 'Export';
    case Subscription = 'Subscription';

    public static function forEventType(string $type): ?self
    {
        return match ($type) {
            'invoice.created', 'invoice.finalized', 'invoice.sending', 'invoice.sent', 'invoice.deposited', 'invoice.transmitted', 'invoice.available', 'invoice.received', 'invoice.approved', 'invoice.refused', 'invoice.rejected', 'invoice.suspended', 'invoice.paid', 'invoice.partially_paid', 'invoice.overdue' => self::Invoice,
            'invoice.incoming.received' => self::IncomingInvoice,
            'quote.created', 'quote.sent', 'quote.viewed', 'quote.accepted', 'quote.refused', 'quote.expired', 'quote.converted' => self::Quote,
            'credit_note.created', 'credit_note.finalized', 'credit_note.credit_deposited', 'credit_note.credit_transmitted', 'credit_note.credit_approved', 'credit_note.credit_refused', 'credit_note.sent' => self::CreditNote,
            'customer.created', 'customer.updated', 'customer.deleted' => self::Customer,
            'payment.created' => self::PaymentCreated,
            'payment.received' => self::PaymentReceived,
            'ereporting.submitted' => self::Ereporting,
            'recurring_invoice.generated' => self::RecurringGenerated,
            'recurring_invoice.failed' => self::RecurringFailed,
            'export.ready' => self::Export,
            'subscription.created', 'subscription.cancelled', 'subscription.renewed', 'subscription.paused' => self::Subscription,
            default => null,
        };
    }
}
