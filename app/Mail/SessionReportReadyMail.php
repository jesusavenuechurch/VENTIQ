<?php

namespace App\Mail;

use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SessionReportReadyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Participant $participant) {}

    public function envelope(): Envelope
    {
        $session = $this->participant->session;

        return new Envelope(
            subject: "Notes are ready for {$session->resolved_title}",
        );
    }

    public function content(): Content
    {
        $session = $this->participant->session;

        return new Content(
            view: 'emails.session-report-ready',
            with: [
                'name'    => $this->participant->client?->full_name ?? 'there',
                'title'   => $session->resolved_title,
                'orgName' => $session->organization?->name,
            ],
        );
    }
}
