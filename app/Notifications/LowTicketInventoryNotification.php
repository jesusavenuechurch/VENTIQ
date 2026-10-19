<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class LowTicketInventoryNotification extends Notification
{
    public function __construct(
        public $tier,
        public $remaining
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'tier_id' => $this->tier->id,
            'tier_name' => $this->tier->tier_name,
            'event_name' => $this->tier->event->name,
            'remaining' => $this->remaining,
            'action_url' => route('organizer.events.edit', $this->tier->event_id),
            'message' => "Low inventory alert: Only {$this->remaining} tickets left for {$this->tier->tier_name}",
        ];
    }
}