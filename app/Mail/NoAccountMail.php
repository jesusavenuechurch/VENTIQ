<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};

/** Someone asked for a sign-in code for an email that has no account. */
class NoAccountMail extends Mailable
{
    public function __construct(public string $email, public ?string $intent = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Signing in to VENTIQ');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.no-account', with: [
            'email'       => $this->email,
            'registerUrl' => route('org.register.direct', ['intent' => $this->intent ?: 'host']),
        ]);
    }
}
