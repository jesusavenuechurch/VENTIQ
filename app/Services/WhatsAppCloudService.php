<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta WhatsApp Cloud API — replacing Twilio (see WhatsAppService/
 * WhatsAppController, both still Twilio-based for now, kept until this is
 * verified end-to-end and every template it needs is approved).
 *
 * Every send here is a business-initiated message (ticket approved, session
 * thank-you, etc.) — outside a live customer-service window, WhatsApp
 * requires these to go through pre-approved templates, never freeform text.
 * That's why every method here builds a `type: template` payload rather
 * than a plain body string.
 */
class WhatsAppCloudService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('services.whatsapp.api_version'),
            config('services.whatsapp.phone_number_id'),
        );
    }

    /**
     * Low-level template send. $bodyParams are plain strings, in order,
     * matching the template's {{1}}, {{2}}, ... placeholders. $headerImageUrl,
     * when given, must be a genuinely public HTTPS URL — Meta's servers
     * fetch it directly, so this breaks silently behind a local/dev URL.
     * $buttonUrlSuffix, when given, fills the single dynamic trailing
     * segment on the template's URL button (e.g. the ticket's QR code,
     * appended after the button's static base URL).
     */
    public function sendTemplate(
        string $to,
        ?string $templateName,
        array $bodyParams,
        ?string $headerImageUrl = null,
        ?string $buttonUrlSuffix = null,
        // 'en_US' — confirmed the language these templates were actually
        // recreated under on the REAL Ventiq app (the sandbox test-app
        // copies were 'en' instead, but .env now points at the real
        // number permanently, so that's no longer relevant).
        string $languageCode = 'en_US',
    ): bool {
        // Not approved by Meta yet (config/constants.php): nothing to send.
        if (!$templateName) {
            Log::info('WhatsApp template not approved yet, skipped', ['to' => $to]);
            return false;
        }

        $components = [];

        if ($headerImageUrl) {
            $components[] = [
                'type' => 'header',
                'parameters' => [
                    ['type' => 'image', 'image' => ['link' => $headerImageUrl]],
                ],
            ];
        }

        if (!empty($bodyParams)) {
            // WhatsApp rejects a template send outright if any {{n}} text
            // parameter is an empty string (#131008) — a real ticket can
            // easily have a blank optional field (e.g. no venue set), so
            // guard every param here rather than trusting each call site
            // to remember to.
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(
                    fn ($text) => ['type' => 'text', 'text' => trim((string) $text) !== '' ? (string) $text : '—'],
                    $bodyParams,
                ),
            ];
        }

        if ($buttonUrlSuffix) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [
                    ['type' => 'text', 'text' => $buttonUrlSuffix],
                ],
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizeNumber($to),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
                'components' => $components,
            ],
        ];

        $response = Http::withToken(config('services.whatsapp.access_token'))
            ->acceptJson()
            ->timeout(20)
            ->post($this->baseUrl, $payload);

        $body = $response->json() ?? [];

        if ($response->successful()) {
            Log::info("WhatsApp template '{$templateName}' sent", ['to' => $to, 'response' => $body]);
            return true;
        }

        Log::error("WhatsApp template '{$templateName}' failed", ['to' => $to, 'status' => $response->status(), 'response' => $body]);
        return false;
    }

    /** An approved template's name (constants.whatsapp_templates), or null: not sent yet. */
    private function template(string $key): ?string
    {
        return \App\Support\WhatsAppTemplates::name($key);
    }

    // Meta wants E.164 without the leading "+" (e.g. "26658123456").
    private function normalizeNumber(string $number): string
    {
        return ltrim(preg_replace('/\D/', '', $number), '0') ?: $number;
    }

    /* ------------------------------------------------------------
     | Per-flow convenience methods — one per approved template.
     | Only sendTicketApproved() is wired into a live trigger today
     | (TicketDeliveryService); the others are ready to flip on the
     | moment their templates clear Meta's review.
     ------------------------------------------------------------ */

    public function sendTicketApproved(Ticket $ticket): bool
    {
        if (!$ticket->client?->phone) {
            return false;
        }

        // "ticket_ready", not "ticket_approved" — deliberately a separate
        // template (no image header) rather than editing the original.
        // "ticket_approved" stays untouched/still-approved with its image
        // header for whenever the server-side rendering problem gets
        // solved, so switching back later needs zero re-review wait.
        return $this->sendTemplate(
            to: $ticket->client->phone,
            templateName: $this->template('ticket_ready'),
            bodyParams: [
                $ticket->client->full_name,
                $ticket->event->name,
                $ticket->ticket_number,
                $ticket->tier->tier_name,
                $ticket->event->event_date?->format('d M Y') ?? '',
                $ticket->event->venue ?? $ticket->event->location ?? '',
                $ticket->voucher_code ?? $ticket->ticket_number,
            ],
            buttonUrlSuffix: $ticket->qr_code,
        );
    }

    // Not wired anywhere yet — enable once "ticket_registered" (the
    // resubmitted Utility version) is approved, and add the call at
    // registration time alongside/instead of TicketPendingMail.
    public function sendTicketPending(Ticket $ticket): bool
    {
        if (!$ticket->client?->phone) {
            return false;
        }

        return $this->sendTemplate(
            to: $ticket->client->phone,
            templateName: $this->template('ticket_registered'),
            bodyParams: [
                $ticket->client->full_name,
                $ticket->event->name,
                $ticket->ticket_number,
                number_format((float) $ticket->amount, 2),
            ],
        );
    }

    // Approved, wired into SessionSegmentController::
    // notifyParticipantsSessionEnded(), alongside the existing email send.
    // Approved body only has one variable ({{1}}, name) — the original
    // draft's {{2}} session-title variable got dropped when the wording
    // was simplified to clear the Marketing-content flag.
    public function sendSessionThankYou(\App\Models\Participant $participant, string $cardPath): bool
    {
        if (!$participant->client?->phone) {
            return false;
        }

        $cardUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($cardPath);

        return $this->sendTemplate(
            to: $participant->client->phone,
            templateName: $this->template('thank_you'), // approved under this name, not "session_thank_you"
            bodyParams: [
                $participant->client->full_name,
            ],
            headerImageUrl: $cardUrl,
        );
    }
}
