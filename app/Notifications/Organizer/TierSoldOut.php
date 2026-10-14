<?php

namespace App\Notifications\Organizer;

use App\Models\EventTier;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A ticket type has reached its number. Sales stop there; the organizer
 * can allow more tickets on the event if they expect more people.
 */
class TierSoldOut extends Notification
{
    use Queueable;

    public function __construct(public EventTier $tier, public string $editUrl) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $event = $this->tier->event;
        $n = (int) $this->tier->quantity_available;

        return (new MailMessage)
            ->subject("{$this->tier->tier_name} is sold out: {$event->name}")
            ->greeting("{$this->tier->tier_name} is sold out")
            ->line("All {$n} {$this->tier->tier_name} " . ($n === 1 ? 'ticket' : 'tickets') . " for {$event->name} are taken, so it has stopped selling.")
            ->line("If you can take more people, allow more tickets on your event and sales reopen straight away. If {$n} is your limit, there's nothing to do.")
            ->action('Allow more tickets', $this->editUrl)
            ->line('Your expected VENTIQ fees update with the new number.');
    }

    public function toArray($notifiable): array
    {
        return [
            'event_tier_id' => $this->tier->id,
            'event_name'    => $this->tier->event->name,
            'action_url'    => $this->editUrl,
            'message'       => "{$this->tier->tier_name} for {$this->tier->event->name} is sold out",
        ];
    }
}
