<?php
// app/Services/Notifications/OrganizerNotifier.php
namespace App\Services\Notifications;

use App\Models\{EventTier, TicketPayment, User};
use App\Notifications\Payments\PaymentSubmittedNotification;
use App\Services\WhatsAppCloudService;
use Illuminate\Support\Facades\{Log, Notification, URL};

/**
 * Brings the organizer to a pending payment instead of expecting them to
 * find it. Sent when an attendee submits a payment (not when they
 * register or an online try fails: there's nothing to do yet), and when
 * a ticket type sells out, since only the organizer can allow more.
 */
class OrganizerNotifier
{
    /** How long the review link in the message keeps working. */
    public const REVIEW_LINK_DAYS = 7;

    public function paymentSubmitted(TicketPayment $payment): void
    {
        $payment->loadMissing(['ticket.client', 'ticket.event.organization', 'paymentAccount']);
        $organization = $payment->ticket->event->organization;
        $reviewUrl = $this->reviewUrl($payment);

        try {
            $recipients = User::where('organization_id', $organization->id)
                ->get()
                ->filter(fn (User $user) => $user->can('approve_payment'));

            Notification::send($recipients, new PaymentSubmittedNotification($payment, $reviewUrl));

            // WhatsApp goes to the organization's own number (users have
            // no phone column) once the template is approved.
            $templateName = \App\Support\WhatsAppTemplates::name('payment_submitted');
            if ($templateName && $organization->phone) {
                app(WhatsAppCloudService::class)->sendTemplate(
                    to: $organization->phone,
                    templateName: $templateName,
                    bodyParams: [
                        $payment->ticket->client->full_name,
                        'M' . number_format((float) $payment->amount, 2),
                        $payment->paymentAccount?->display_label ?? ucfirst((string) $payment->payment_method),
                        $payment->payment_reference ?? '—',
                    ],
                    buttonUrlSuffix: ltrim(parse_url($reviewUrl, PHP_URL_PATH) . '?' . parse_url($reviewUrl, PHP_URL_QUERY), '/'),
                );
            }
        } catch (\Throwable $e) {
            Log::error("Organizer notice failed for payment {$payment->id}: {$e->getMessage()}");
        }
    }

    /** A ticket type reached its number: they can allow more tickets if they expect more people. */
    public function tierSoldOut(EventTier $tier): void
    {
        $organization = $tier->event->organization;
        $editUrl = route('organizer.events.edit', $tier->event);

        try {
            $recipients = User::where('organization_id', $organization->id)
                ->get()
                ->filter(fn (User $user) => $user->can('edit_event'));

            Notification::send($recipients, new \App\Notifications\Organizer\TierSoldOut($tier, $editUrl));

            $templateName = \App\Support\WhatsAppTemplates::name('tickets_sold_out');
            if ($templateName && $organization->phone) {
                app(WhatsAppCloudService::class)->sendTemplate(
                    to: $organization->phone,
                    templateName: $templateName,
                    bodyParams: [$tier->tier_name, $tier->event->name, (string) (int) $tier->quantity_available],
                    buttonUrlSuffix: ltrim((string) parse_url($editUrl, PHP_URL_PATH), '/'),
                );
            }
        } catch (\Throwable $e) {
            Log::error("Sold-out notice failed for tier {$tier->id}: {$e->getMessage()}");
        }
    }

    /** A login-free link to the review page for this one payment. */
    public function reviewUrl(TicketPayment $payment): string
    {
        return URL::temporarySignedRoute('payment-review.show', now()->addDays(self::REVIEW_LINK_DAYS), ['payment' => $payment->id]);
    }
}
