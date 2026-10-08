<?php

namespace App\Mail;

use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class SessionThankYouMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Participant $participant, public string $cardPath) {}

    public function envelope(): Envelope
    {
        $session = $this->participant->session;

        return new Envelope(
            subject: "Thank you for attending {$session->resolved_title}",
        );
    }

    public function content(): Content
    {
        $session = $this->participant->session;

        return new Content(
            view: 'emails.session-thank-you',
            with: [
                'name'      => $this->participant->client?->full_name ?? 'there',
                'title'     => $session->resolved_title,
                'orgName'   => $session->organization?->name,
                'cardUrl'   => Storage::disk('public')->url($this->cardPath),
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('public', $this->cardPath)
                ->as("attendance-card-{$this->participant->id}.png")
                ->withMime('image/png'),
        ];
    }
}