<?php

namespace App\Notifications\Payments;

use App\Models\TicketPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an organizer an attendee says they've paid, with a link straight
 * to the one question that matters: has the money arrived?
 */
class PaymentSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TicketPayment $payment,
        public string $reviewUrl,
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $ticket = $this->payment->ticket;

        return (new MailMessage)
            ->subject("Payment to confirm: {$ticket->client->full_name}, M" . number_format((float) $this->payment->amount, 2))
            ->greeting('A payment needs your confirmation')
            ->line("{$ticket->client->full_name} says they paid M" . number_format((float) $this->payment->amount, 2) . " for {$ticket->event->name}.")
            ->line('Paid to: ' . ($this->payment->paymentAccount?->display_label ?? ucfirst((string) $this->payment->payment_method)))
            ->line('Reference: ' . ($this->payment->payment_reference ?: '—'))
            ->action('Review payment', $this->reviewUrl)
            ->line('Check that the money has arrived before activating the ticket.');
    }

    public function toArray($notifiable): array
    {
        $ticket = $this->payment->ticket;

        return [
            'ticket_payment_id' => $this->payment->id,
            'ticket_id'         => $ticket->id,
            'client_name'       => $ticket->client->full_name,
            'event_name'        => $ticket->event->name,
            'amount'            => $this->payment->amount,
            'action_url'        => route('organizer.payments.index'),
            'message'           => "{$ticket->client->full_name} submitted a payment for {$ticket->event->name}",
        ];
    }
}
