<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};

/**
 * Sent straight away, not queued: someone is waiting at the login screen.
 * The code is in the subject so it shows in the phone's notification.
 */
class LoginCodeMail extends Mailable
{
    public function __construct(public User $user, public string $code, public string $link) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->code} is your VENTIQ sign-in code");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.login-code', with: [
            'name'    => strtok((string) $this->user->name, ' ') ?: 'there',
            'code'    => $this->code,
            'link'    => $this->link,
            'minutes' => \App\Services\Auth\LoginCodeService::TTL_MINUTES,
        ]);
    }
}
