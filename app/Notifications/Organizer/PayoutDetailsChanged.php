<?php

namespace App\Notifications\Organizer;

use App\Models\{Organization, User};
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every admin of an organization when its payout account changes:
 * if someone else changed it, they hear about it before any money moves.
 */
class PayoutDetailsChanged extends Notification
{
    use Queueable;

    public function __construct(public Organization $organization, public User $by) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payout account changed: {$this->organization->name}")
            ->greeting('Hi ' . (\Illuminate\Support\Str::before($notifiable->name ?? '', ' ') ?: 'there') . ',')
            ->line("{$this->by->name} ({$this->by->email}) changed where VENTIQ pays {$this->organization->name}'s online ticket money.")
            ->line('New account: ' . $this->organization->payoutSummary(masked: true))
            ->line("If this wasn't you or someone on your team, reply to this email or contact VENTIQ straight away: we check recent changes before paying.")
            ->action('Review payout settings', route('organizer.payout.edit'));
    }
}
