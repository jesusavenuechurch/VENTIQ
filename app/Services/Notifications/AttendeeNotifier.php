<?php
// app/Services/Notifications/AttendeeNotifier.php
namespace App\Services\Notifications;

use App\Models\Ticket;
use App\Notifications\Payments\AttendeeTicketNotice;
use App\Services\WhatsAppCloudService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Payment-workflow messages to attendees. Email goes out whenever the
 * attendee gave an address; WhatsApp only once the matching template is
 * approved (constants.whatsapp_templates). Failures are
 * logged, never thrown: a message that didn't send must not undo the
 * organizer's decision that triggered it.
 */
class AttendeeNotifier
{
    public function paymentRejected(Ticket $ticket, ?string $reason = null): void
    {
        $this->send($ticket, 'payment_rejected',
            subject: "Payment not confirmed: {$ticket->event->name}",
            lines: array_filter([
                "{$ticket->event->organization->name} couldn't confirm your payment for {$ticket->event->name}.",
                $reason ? "Their note: \"{$reason}\"" : null,
                'Your ticket is still reserved. Please check your payment and submit it again.',
            ]),
            actionText: 'Submit payment again',
            actionUrl: $this->paymentUrl($ticket),
        );
    }

    public function paymentReminder(Ticket $ticket): void
    {
        $this->send($ticket, 'payment_reminder',
            subject: "Reminder: pay for your {$ticket->event->name} ticket",
            lines: [
                "Your ticket for {$ticket->event->name} is reserved until {$ticket->payment_due_at?->format('d M Y, H:i')}.",
                'Pay before then to keep your place.',
            ],
            actionText: 'Pay now',
            actionUrl: $this->paymentUrl($ticket),
            whatsappParams: [$ticket->client?->full_name, $ticket->event->name, $this->heldUntil($ticket)],
        );
    }

    /**
     * An online payment failed or got no answer and the attendee hasn't
     * paid since (sent once per ticket, by tickets:payment-follow-ups).
     */
    public function paymentFailed(Ticket $ticket): void
    {
        $this->send($ticket, 'payment_failed',
            subject: "Your payment didn't go through: {$ticket->event->name}",
            lines: [
                "Your payment for {$ticket->event->name} didn't go through, so your ticket isn't active yet.",
                "Your place is held until {$this->heldUntil($ticket)}.",
                'You can try again, pay another way, or send us proof if you did pay.',
            ],
            actionText: 'Finish paying',
            actionUrl: $this->paymentUrl($ticket),
            whatsappParams: [$ticket->client?->full_name, $ticket->event->name, $this->heldUntil($ticket)],
        );
    }

    private function heldUntil(Ticket $ticket): string
    {
        return $ticket->payment_due_at?->format('j M, H:i') ?? 'the event';
    }

    public function paymentWindowExpired(Ticket $ticket): void
    {
        $this->send($ticket, 'payment_expired',
            subject: "Payment window ended: {$ticket->event->name}",
            lines: [
                "The time to pay for your {$ticket->event->name} ticket has run out, so your place has been released.",
                'If places are still available you can register again, or contact the organizer.',
            ],
            actionText: 'View the event',
            actionUrl: route('event.short', [$ticket->event->organization->slug, $ticket->event->slug]),
        );
    }

    private function paymentUrl(Ticket $ticket): string
    {
        return route('ticket.pay', $ticket->qr_code);
    }

    private function send(Ticket $ticket, string $template, string $subject, array $lines, string $actionText, string $actionUrl, ?array $whatsappParams = null): void
    {
        $ticket->loadMissing(['client', 'event.organization']);

        try {
            if ($ticket->client?->email) {
                Notification::route('mail', $ticket->client->email)->notify(new AttendeeTicketNotice(
                    subject: $subject,
                    greeting: "Hi {$ticket->client->full_name},",
                    lines: array_values($lines),
                    actionText: $actionText,
                    actionUrl: $actionUrl,
                ));
            }

            $templateName = \App\Support\WhatsAppTemplates::name($template);
            if ($templateName && $ticket->client?->phone) {
                $sent = app(WhatsAppCloudService::class)->sendTemplate(
                    to: $ticket->client->phone,
                    templateName: $templateName,
                    bodyParams: array_map('strval', $whatsappParams ?? [$ticket->client->full_name, $ticket->event->name]),
                    // The template's button is "https://<domain>/{{1}}": it opens
                    // the same page as the email's button (pay page, or the
                    // event to register again once the place is released).
                    buttonUrlSuffix: \App\Support\WhatsAppTemplates::hasButton($template) ? ltrim((string) parse_url($actionUrl, PHP_URL_PATH), '/') : null,
                );

                if (!$sent) {
                    $ticket->logDeliveryFailure('whatsapp', "Failed to send {$template}");
                }
            }
        } catch (\Throwable $e) {
            Log::error("Attendee notice {$template} failed for ticket {$ticket->id}: {$e->getMessage()}");
        }
    }
}
