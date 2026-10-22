<?php

namespace App\Mail;

use App\Models\PaymentSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To the organization's Admins: their bank transfer was found and applied, or wasn't found. */
class BankTransferResultMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PaymentSession $payment, public bool $confirmed, public ?string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->confirmed ? 'Payment received: ' . ($this->payment->purchase_meta['label'] ?? 'Sessions') : "We couldn't find your bank transfer {$this->payment->client_reference}",
            replyTo: [config('ventiq.billing.email')],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.bank-transfer-result', with: [
            'label' => $this->payment->purchase_meta['label'] ?? 'Sessions',
            'link'  => route('sessions.plan'),
        ]);
    }
}
