<?php

namespace App\Mail;

use App\Models\OrganizationInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public OrganizationInvite $invite) {}

    public function envelope(): Envelope
    {
        $orgName = $this->invite->organization->name;
        $inviterName = $this->invite->invitedBy?->name ?? 'A teammate';

        return new Envelope(
            subject: "{$inviterName} invited you to join {$orgName} on VENTIQ",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.organization-invite',
            with: [
                'acceptUrl' => route('organization.invite.show', $this->invite->token),
                'orgName' => $this->invite->organization->name,
                'inviterName' => $this->invite->invitedBy?->name ?? 'A teammate',
            ],
        );
    }
}