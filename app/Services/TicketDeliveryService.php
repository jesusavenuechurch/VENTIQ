<?php

namespace App\Services;

use App\Models\Ticket;
use App\Services\WhatsAppCloudService;
use Illuminate\Support\Facades\Log;

class TicketDeliveryService
{
    /**
     * @return bool|null whether WhatsApp accepted the ticket; null when the
     *                   ticket isn't set up for WhatsApp (no number, opted out).
     */
    public function deliver(Ticket $ticket): ?bool
    {
        $whatsapp = null;

        Log::info("🚀 Delivering ticket {$ticket->ticket_number}");

        // WhatsApp — Meta Cloud API, "ticket_approved" template (Twilio
        // retired for this flow; still referenced elsewhere until fully
        // migrated).
        if ($ticket->shouldDeliverViaWhatsApp()) {
            $whatsapp = app(WhatsAppCloudService::class)->sendTicketApproved($ticket);

            $whatsapp
                ? $ticket->markAsDeliveredViaWhatsApp()
                : $ticket->logDeliveryFailure('whatsapp', 'Failed to send via Meta WhatsApp Cloud API');
        }

        // Email (future)
        if ($ticket->shouldDeliverViaEmail()) {
            // Mail::to(...)->send(...)
            Log::info("📧 Email delivery placeholder for {$ticket->ticket_number}");
        }

        return $whatsapp;
    }
}