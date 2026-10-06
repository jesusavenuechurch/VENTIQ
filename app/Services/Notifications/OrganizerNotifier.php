<?php
// app/Services/Notifications/OrganizerNotifier.php
namespace App\Services\Notifications;

use App\Models\{TicketPayment, User};
use App\Notifications\Payments\PaymentSubmittedNotification;
use App\Services\WhatsAppCloudService;
use Illuminate\Support\Facades\{Log, Notification, URL};

/**
 * Brings the organizer to a pending payment instead of expecting them to
 * find it. Sent when an attendee submits a payment, not when they
 * register: before that there's nothing for the organizer to do.
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

    /** A login-free link to the review page for this one payment. */
    public function reviewUrl(TicketPayment $payment): string
    {
        return URL::temporarySignedRoute('payment-review.show', now()->addDays(self::REVIEW_LINK_DAYS), ['payment' => $payment->id]);
    }
}
