<?php

namespace App\Notifications\Payments;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an organizer that someone tried to pay online and it didn't go
 * through, so the ticket is unpaid. The attendee has already been sent
 * their link; the organizer may want to follow up with them.
 */
class PaymentUnfinishedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public int $tries,
        public string $heldUntil,
        public string $attendeesUrl,
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $t = $this->ticket;

        return (new MailMessage)
            ->subject("Unpaid ticket: {$t->holder_name}, {$t->event->name}")
            ->greeting('A ticket is waiting for payment')
            ->line("{$t->holder_name} tried to pay M" . number_format((float) $t->amount, 2) . " for a {$t->tier?->tier_name} ticket to {$t->event->name}, but the EcoCash payment didn't go through"
                . ($this->tries > 1 ? " ({$this->tries} tries)." : '.'))
            ->line("We've sent them their ticket link to try again, pay another way or send proof. Their place is held until {$this->heldUntil}.")
            ->line('Their number: ' . ($t->client?->phone ?: '—'))
            ->action('See unpaid tickets', $this->attendeesUrl)
            ->line('Nothing to do unless you want to follow up with them yourself.');
    }

    public function toArray($notifiable): array
    {
        return [
            'ticket_id'   => $this->ticket->id,
            'client_name' => $this->ticket->holder_name,
            'event_name'  => $this->ticket->event->name,
            'amount'      => $this->ticket->amount,
            'action_url'  => $this->attendeesUrl,
            'message'     => "{$this->ticket->holder_name}'s online payment for {$this->ticket->event->name} didn't go through",
        ];
    }
}
