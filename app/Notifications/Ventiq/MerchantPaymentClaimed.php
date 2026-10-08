<?php

namespace App\Notifications\Ventiq;

use App\Models\{PaymentSession, Ticket};
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To VENTIQ: an attendee says they paid VENTIQ's EcoCash merchant code by
 * hand. Only VENTIQ can see that statement, so only VENTIQ can confirm it,
 * on the money page ("Online payments to check").
 */
class MerchantPaymentClaimed extends Notification
{
    use Queueable;

    public function __construct(public PaymentSession $session, public Ticket $ticket) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $claim = $this->session->callback_payload['by_hand'] ?? [];
        $amount = 'M' . number_format((float) $this->session->amount, 2);

        $mail = (new MailMessage)
            ->subject("EcoCash merchant payment to check: {$this->ticket->holder_name}, {$amount}")
            ->greeting('A merchant payment needs checking')
            ->line("{$this->ticket->holder_name} says they paid {$amount} to the VENTIQ merchant code for {$this->ticket->event->name} ({$this->ticket->event->organization->name}).")
            ->line('Paid from: ' . ($claim['paid_from'] ?? '—'))
            ->line('Reference: ' . ($claim['reference'] ?? '—'))
            ->line('Ticket: ' . $this->ticket->ticket_number);

        if (!empty($claim['proof_path'])) {
            $mail->line('They also sent a screenshot; it opens from the money page.');
        }

        return $mail
            ->action('Check it on the money page', route('ventiq.money.index'))
            ->line('Find it on the merchant statement, then confirm or reject it there. Confirming sends their ticket.');
    }
}
