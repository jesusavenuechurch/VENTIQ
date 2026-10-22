<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Notifications\Notification;

class TicketRegistrationNotification extends Notification
{
    public function __construct(
        public Ticket $ticket
    ) {}

    public function via($notifiable): array
    {
        return ['database']; // Only database for now, no email
    }

    public function toArray($notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'client_name' => $this->ticket->client->full_name,
            'event_name' => $this->ticket->event->name,
            'amount' => $this->ticket->amount,
            'payment_status' => $this->ticket->payment_status,
            'action_url' => route('organizer.events.attendees', [$this->ticket->event_id, 'q' => $this->ticket->voucher_code]),
            'message' => "New ticket registration from {$this->ticket->client->full_name} for {$this->ticket->event->name}",
        ];
    }
}