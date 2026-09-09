<?php

namespace App\Services;

use App\Models\Ticket;
use App\Services\WhatsAppCloudService;
use Illuminate\Support\Facades\Log;

class TicketDeliveryService
{
    public function deliver(Ticket $ticket): void
    {
        Log::info("🚀 Delivering ticket {$ticket->ticket_number}");

        // WhatsApp — Meta Cloud API, "ticket_approved" template (Twilio
        // retired for this flow; still referenced elsewhere until fully
        // migrated).
        if ($ticket->shouldDeliverViaWhatsApp()) {
            $sent = app(WhatsAppCloudService::class)->sendTicketApproved($ticket);

            if (!$sent) {
                $ticket->logDeliveryFailure('whatsapp', 'Failed to send via Meta WhatsApp Cloud API');
            }
        }

        // Email (future)
        if ($ticket->shouldDeliverViaEmail()) {
            // Mail::to(...)->send(...)
            Log::info("📧 Email delivery placeholder for {$ticket->ticket_number}");
        }
    }
}