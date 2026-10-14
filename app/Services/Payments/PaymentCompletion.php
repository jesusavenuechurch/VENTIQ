<?php
// app/Services/Payments/PaymentCompletion.php
namespace App\Services\Payments;

use App\Models\{PaymentSession, SettlementItem, Ticket};
use App\Services\SessionPackageService;
use Illuminate\Support\Facades\Log;

/**
 * What happens once a gateway says a session is paid: the ticket is
 * activated, or the Sessions package applied. Shared by the PayLesotho
 * callback and the background wait on EcoCash v2.
 */
class PaymentCompletion
{
    public function __construct(
        private TicketActivationService $activation,
        private SessionPackageService $sessionPackages,
    ) {}

    public function completed(PaymentSession $session): void
    {
        if (!$session->isCompleted()) {
            return;
        }

        if ($session->payable_type === 'ticket') {
            $ticket = Ticket::with(['client', 'event', 'tier'])->find($session->payable_id);
            if (!$ticket) {
                return;
            }

            $activated = $this->activation->activate(
                ticket: $ticket,
                source: TicketActivationService::SOURCE_VENTIQ_ONLINE,
                paymentMethod: $session->payment_method,
                paymentReference: $session->transaction_id,
                paymentSession: $session,
            );

            // Already paid by another push (or by hand): this money came
            // in twice and has to go back. Listed on the VENTIQ money page.
            if (!$activated && !SettlementItem::where('payment_session_id', $session->id)->exists()) {
                $session->update(['callback_payload' => array_merge($session->callback_payload ?? [], ['paid_twice' => true])]);
                Log::warning('Ticket paid twice online: refund needed', ['ticket' => $ticket->id, 'session' => $session->id]);
            }

            return;
        }

        if ($session->payable_type === 'session_package') {
            $meta = $session->purchase_meta ?? [];

            if (($meta['type'] ?? null) === 'plan') {
                $this->sessionPackages->changePlan(
                    organizationId: $session->organization_id,
                    tier: $meta['tier'],
                    sessionsIncluded: $meta['sessions_included'],
                    whatsappIncluded: $meta['whatsapp_included'],
                    smsIncluded: $meta['sms_included'],
                    pricePaid: (float) $session->amount,
                );
            } elseif (($meta['type'] ?? null) === 'payg') {
                $this->sessionPackages->addPaygCredits(
                    organizationId: $session->organization_id,
                    quantity: $meta['quantity'],
                    pricePaid: (float) $session->amount,
                );
            }
        }
    }
}
