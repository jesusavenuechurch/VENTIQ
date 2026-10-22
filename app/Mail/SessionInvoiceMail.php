<?php

namespace App\Mail;

use App\Models\SessionInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A corporate organization's monthly Sessions invoice, with the PDF. */
class SessionInvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public SessionInvoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "VENTIQ invoice {$this->invoice->number}: Sessions, {$this->invoice->periodLabel()}",
            replyTo: [config('ventiq.billing.email')],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.session-invoice', with: [
            'organization' => $this->invoice->organization->name,
            'link'         => route('sessions.plan'),
        ]);
    }

    public function attachments(): array
    {
        $pdf = \App\Http\Controllers\SessionInvoiceController::pdf($this->invoice)->output();

        return [Attachment::fromData(fn () => $pdf, "{$this->invoice->number}.pdf")->withMime('application/pdf')];
    }
}
