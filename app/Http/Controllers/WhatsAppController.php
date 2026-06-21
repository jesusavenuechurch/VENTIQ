<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Twilio\Rest\Client;

class WhatsAppController extends Controller
{
    private $twilio;
    private $from;

    public function __construct()
    {
        $this->twilio = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );
        $this->from = 'whatsapp:' . config('services.twilio.whatsapp_from');
    }

    /**
     * ✅ NEW: Auto-deliver ticket on payment approval
     * Called from: TicketResource::approvePayment()
     * 
     * @param Ticket $ticket
     * @return bool Success status
     */
    public static function deliverTicket(Ticket $ticket): bool
    {
        try {
            // Validate ticket is ready for delivery
            if (!$ticket->client->phone) {
                Log::warning("Ticket {$ticket->ticket_number}: No phone number");
                return false;
            }

            if ($ticket->payment_status !== 'completed') {
                Log::warning("Ticket {$ticket->ticket_number}: Payment not completed");
                return false;
            }

            // Create controller instance
            $controller = app(self::class);
            
            // Send ticket with QR code
            $result = $controller->sendTicketWithQR($ticket);
            
            if ($result) {
                Log::info("✅ Ticket {$ticket->ticket_number} delivered via WhatsApp to {$ticket->client->phone}");
            } else {
                Log::error("❌ Failed to deliver ticket {$ticket->ticket_number} via WhatsApp");
            }
            
            return $result;

        } catch (\Exception $e) {
            Log::error("WhatsApp delivery error for ticket {$ticket->ticket_number}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ NEW: Send ticket with download link + QR code image
     * 
     * @param Ticket $ticket
     * @return bool Success status
     */
    private function sendTicketWithQR(Ticket $ticket): bool
    {
        try {
            $phone = $ticket->client->phone;
            $ticketUrl = route('ticket.download', $ticket->qr_code);
            
            // Build message
            $message = "🎉 *Payment Approved!*\n\n"
                     . "Hi {$ticket->client->full_name}! 👋\n\n"
                     . "Your ticket for *{$ticket->event->name}* is ready!\n\n"
                     . "📄 *Ticket:* {$ticket->ticket_number}\n"
                     . "🎟️ *Tier:* {$ticket->tier->tier_name}\n"
                     . "📅 *Date:* " . $ticket->event->event_date->format('D, M j, Y @ g:i A') . "\n"
                     . "📍 *Venue:* {$ticket->event->venue}\n\n"
                     . "👉 *Download your ticket:*\n{$ticketUrl}\n\n"
                     . "Or use the QR code attached below! 👇\n\n"
                     . "See you at the event! 🎉";

            // Get QR code image path
            $qrCodePath = $ticket->qr_code_path;
            
            if (!$qrCodePath || !Storage::disk('public')->exists($qrCodePath)) {
                Log::warning("QR code not found for ticket {$ticket->ticket_number}, sending without image");
                return $this->sendMessage($phone, $message);
            }

            // Get full URL to QR code
            $qrCodeUrl = Storage::disk('public')->url($qrCodePath);
            
            // Send message with media
            $sent = $this->sendMessageWithMedia($phone, $message, $qrCodeUrl);

            if ($sent) {
                // Update delivery status
                $ticket->update([
                    'delivery_status' => 'delivered',
                    'whatsapp_delivered_at' => now(),
                    'delivery_log' => array_merge($ticket->delivery_log ?? [], [
                        [
                            'method' => 'whatsapp',
                            'status' => 'delivered',
                            'timestamp' => now()->toIso8601String(),
                            'phone' => $phone,
                            'message_type' => 'auto_approved',
                            'has_qr_code' => true,
                        ]
                    ]),
                ]);
            }

            return $sent;

        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp with QR for ticket {$ticket->ticket_number}: " . $e->getMessage());
            
            // Log delivery failure
            $ticket->update([
                'delivery_log' => array_merge($ticket->delivery_log ?? [], [
                    [
                        'method' => 'whatsapp',
                        'status' => 'failed',
                        'timestamp' => now()->toIso8601String(),
                        'error' => $e->getMessage(),
                    ]
                ]),
            ]);
            
            return false;
        }
    }

    /**
     * Send WhatsApp message with media (QR code image)
     * 
     * @param string $to Phone number
     * @param string $message Text message
     * @param string $mediaUrl Public URL to image
     * @return bool Success status
     */
    private function sendMessageWithMedia(string $to, string $message, string $mediaUrl): bool
    {
        try {
            $toFormatted = 'whatsapp:' . $to;

            $this->twilio->messages->create($toFormatted, [
                'from' => $this->from,
                'body' => $message,
                'mediaUrl' => [$mediaUrl], // Attach QR code image
            ]);

            Log::info("WhatsApp message with media sent to {$to}");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp with media to {$to}: " . $e->getMessage());
            
            // Fallback: Try sending without media
            Log::info("Attempting to send without media...");
            return $this->sendMessage($to, $message);
        }
    }

    /**
     * Send plain WhatsApp message (no media)
     * 
     * @param string $to Phone number
     * @param string $message Text message
     * @return bool Success status
     */
    private function sendMessage(string $to, string $message): bool
    {
        try {
            $toFormatted = 'whatsapp:' . $to;

            $this->twilio->messages->create($toFormatted, [
                'from' => $this->from,
                'body' => $message,
            ]);

            Log::info("WhatsApp message sent to {$to}");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp message to {$to}: " . $e->getMessage());
            return false;
        }
    }

    /* ============================================================
     | WEBHOOK (KEPT FOR MANUAL TESTING/RESEND)
     ============================================================ */

    /**
     * Webhook - Receives incoming WhatsApp messages from users
     * NOTE: This is now optional - kept for manual ticket requests
     */
    public function webhook(Request $request)
    {
        try {
            $from = $request->input('From'); // whatsapp:+26659494756
            $body = trim($request->input('Body', '')); // "TICKET TKT-6-00001"
            
            // Extract phone number
            $phone = str_replace('whatsapp:', '', $from);
            
            Log::info('WhatsApp webhook received', [
                'from' => $phone,
                'body' => $body,
            ]);

            // Check if message contains ticket number
            if (preg_match('/TICKET\s+(TKT-\d+-\d+)/i', $body, $matches)) {
                $ticketNumber = strtoupper($matches[1]);
                
                return $this->handleTicketRequest($phone, $ticketNumber);
            }

            // If no ticket number found, send instructions
            return $this->sendInstructions($phone);

        } catch (\Exception $e) {
            Log::error('WhatsApp webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    /**
     * Handle manual ticket request from user
     * (For users who want to re-download their ticket)
     */
    private function handleTicketRequest(string $phone, string $ticketNumber)
    {
        // Find ticket by number and phone
        $ticket = Ticket::where('ticket_number', $ticketNumber)
            ->whereHas('client', function ($query) use ($phone) {
                $query->where('phone', $phone);
            })
            ->with(['client', 'event', 'tier'])
            ->first();

        if (!$ticket) {
            $message = "❌ *Ticket not found*\n\n"
                      . "We couldn't find ticket *{$ticketNumber}* for this number.\n\n"
                      . "Please check:\n"
                      . "✓ Ticket number is correct\n"
                      . "✓ Using the same phone number from registration\n\n"
                      . "Need help? Contact support.";
            
            $this->sendMessage($phone, $message);
            
            return response()->json(['status' => 'ticket_not_found']);
        }

        // Check if payment is approved
        if ($ticket->payment_status !== 'completed') {
            $message = "⏳ *Payment Pending*\n\n"
                      . "Hi {$ticket->client->full_name}!\n\n"
                      . "Your ticket *{$ticketNumber}* is awaiting payment approval.\n\n"
                      . "We'll send your ticket automatically as soon as payment is confirmed.\n\n"
                      . "Thank you for your patience! 🙏";
            
            $this->sendMessage($phone, $message);
            
            return response()->json(['status' => 'payment_pending']);
        }

        // Resend ticket
        $this->sendTicketWithQR($ticket);
        
        return response()->json(['status' => 'ticket_resent']);
    }

    /**
     * Send usage instructions
     */
    private function sendInstructions(string $phone)
    {
        $message = "👋 *Welcome to VENTIQ!*\n\n"
                  . "To request your ticket again, send:\n\n"
                  . "`TICKET TKT-X-XXXXX`\n\n"
                  . "Replace with your actual ticket number.\n\n"
                  . "Example:\n"
                  . "`TICKET TKT-6-00001`\n\n"
                  . "💡 *Note:* Tickets are sent automatically when payment is approved. You only need to use this if you need to download your ticket again.\n\n"
                  . "Need help? Contact support.";

        $this->sendMessage($phone, $message);

        return response()->json(['status' => 'instructions_sent']);
    }
    public function verifyWebhook(Request $request)
    {
        $verifyToken = config('services.whatsapp.verify_token');

        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('WhatsApp webhook verified successfully');
            return response($challenge, 200);
        }

        Log::warning('WhatsApp webhook verification failed', [
            'mode' => $mode,
            'token_received' => $token,
        ]);

        return response('Verification failed', 403);
    }
}