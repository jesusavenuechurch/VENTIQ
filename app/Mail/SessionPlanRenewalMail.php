<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\SessionPackage;
use App\Support\SessionPackageDefinition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * To the organization's Admins about paying for the next month of their
 * plan: it's due soon, it's running on grace, or it's gone back to Free.
 */
class SessionPlanRenewalMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public SessionPackage $plan, public Organization $organization, public string $stage) {}

    public function envelope(): Envelope
    {
        $label = $this->label();

        return new Envelope(
            subject: match ($this->stage) {
                'due'    => "Your {$label} plan renews on {$this->plan->period_end->format('j M')}",
                'grace'  => "Pay for your {$label} plan by {$this->plan->graceEndsAt()->format('j M')} to keep it",
                default  => "Your {$label} plan has ended: you're on Free",
            },
            replyTo: [config('ventiq.billing.email')],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.session-plan-renewal', with: [
            'label'   => $this->label(),
            'price'   => SessionPackageDefinition::get($this->plan->tier)['price'] ?? null,
            'renews'  => $this->plan->period_end?->format('j F Y'),
            'graceBy' => $this->stage === 'grace' ? $this->plan->graceEndsAt()->format('j F Y') : null,
            'payUrl'  => route('organization.session-plan.payment', ['type' => 'plan', 'tier' => $this->plan->tier]),
            'planUrl' => route('sessions.plan'),
        ]);
    }

    private function label(): string
    {
        return SessionPackageDefinition::get($this->plan->tier)['label'] ?? ucfirst($this->plan->tier);
    }
}
