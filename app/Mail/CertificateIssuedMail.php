<?php

namespace App\Mail;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificateIssuedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Certificate $certificate) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your certificate for {$this->certificate->programme->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.certificate-issued',
            with: [
                'name'          => $this->certificate->client->full_name,
                'programmeName' => $this->certificate->programme->name,
                'orgName'       => $this->certificate->organization->name,
                'verifyUrl'     => $this->certificate->verify_url,
                'linkedInUrl'   => $this->certificate->linked_in_add_url,
            ],
        );
    }

    public function attachments(): array
    {
        $pdf = $this->certificate->pdf()->output();

        return [
            Attachment::fromData(fn () => $pdf, "ventiq-certificate-{$this->certificate->id}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
