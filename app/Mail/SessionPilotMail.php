<?php

namespace App\Mail;

use App\Models\SessionAgreement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A Sessions pilot: ending in a week (to VENTIQ), or ended (to the organization's Admins and VENTIQ). */
class SessionPilotMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public SessionAgreement $pilot, public string $stage) {}

    public function envelope(): Envelope
    {
        $name = $this->pilot->organization->name;

        return new Envelope(
            subject: $this->stage === 'ending'
                ? "{$name}'s pilot ends on {$this->pilot->ends_on->format('j M')}: renew it or agree a rate"
                : "Your VENTIQ Sessions pilot has ended",
            replyTo: [config('ventiq.billing.email')],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.session-pilot', with: [
            'name'     => $this->pilot->organization->name,
            'ends'     => $this->pilot->ends_on->format('j F Y'),
            'since'    => $this->pilot->starts_on->format('j F Y'),
            'held'     => \App\Models\Session::where('organization_id', $this->pilot->organization_id)
                ->whereDate('date', '>=', $this->pilot->starts_on)->whereIn('status', ['active', 'completed', 'reported'])->count(),
            'ratesUrl' => route('ventiq.corporate.index'),
            'planUrl'  => route('sessions.plan'),
        ]);
    }
}
