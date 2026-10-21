<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\PaymentSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To VENTIQ billing: an organization says it has paid for Sessions by bank transfer. Check the account, then confirm. */
class BankTransferClaimedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PaymentSession $payment) {}

    public function envelope(): Envelope
    {
        $org = Organization::find($this->payment->organization_id)?->name;

        return new Envelope(subject: "Bank transfer to check: {$this->payment->client_reference}, M" . number_format((float) $this->payment->amount, 2) . " from {$org}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.bank-transfer-claimed', with: [
            'organization' => Organization::find($this->payment->organization_id)?->name,
            'details'      => $this->payment->callback_payload ?? [],
            'label'        => $this->payment->purchase_meta['label'] ?? 'Sessions',
            'link'         => route('ventiq.session-plans.index'),
        ]);
    }
}
