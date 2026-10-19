<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class WhatsAppService
{
    protected Client $twilio;
    protected string $from;

    public function __construct()
    {
        $this->twilio = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );

        $this->from = 'whatsapp:' . config('services.twilio.whatsapp_from');
    }

    public function sendTicket(Ticket $ticket, string $phone): bool
    {
        try {
            $to = 'whatsapp:' . $phone;

            $ticketUrl = route('ticket.download', $ticket->qr_code);

            $message =
                "🎫 *{$ticket->event->name}*\n\n" .
                "Hi {$ticket->holder_name}! 👋\n\n" .
                "Your ticket is ready!\n\n" .
                "📄 *Ticket:* {$ticket->ticket_number}\n" .
                "🎟️ *Tier:* {$ticket->tier->tier_name}\n" .
                "📅 *Date:* {$ticket->event->event_date->format('D, M j, Y @ g:i A')}\n" .
                "📍 *Venue:* {$ticket->event->venue}\n\n" .
                "👉 *Get your ticket:*\n{$ticketUrl}\n\n" .
                "See you at the event! 🎉";

            $this->twilio->messages->create($to, [
                'from' => $this->from,
                'body' => $message,
            ]);

            $ticket->markAsDeliveredViaWhatsApp();

            Log::info("✅ WhatsApp ticket sent", [
                'ticket' => $ticket->ticket_number,
                'phone' => $phone,
            ]);

            return true;

        } catch (\Throwable $e) {
            Log::error("❌ WhatsApp send failed", [
                'ticket' => $ticket->ticket_number,
                'error' => $e->getMessage(),
            ]);

            $ticket->logDeliveryFailure('whatsapp', $e->getMessage());

            return false;
        }
    }
}