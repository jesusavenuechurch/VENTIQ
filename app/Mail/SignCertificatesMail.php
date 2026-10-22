<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "You can sign certificates": the link to draw your signature. */
class SignCertificatesMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $organization) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your signature for {$this->organization}'s certificates");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.sign-certificates', with: ['link' => route('sessions.signature')]);
    }
}
