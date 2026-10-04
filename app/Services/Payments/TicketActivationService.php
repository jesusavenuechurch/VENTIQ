<?php
// app/Services/Payments/TicketActivationService.php
namespace App\Services\Payments;

use App\Jobs\SendTicketApprovedEmail;
use App\Models\{PaymentSession, SettlementItem, Ticket, TicketPayment};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The one place a ticket goes from inactive to active.
 *
 * Every path that confirms a payment — the PayLesotho and MoPay callbacks,
 * and the organizer's Activate action — calls activate(). Before this,
 * four copies of the logic disagreed: one never updated the payment row,
 * one counted the sale twice, and repeated gateway callbacks each created
 * a fresh SettlementItem.
 *
 * Attendee delivery is NOT dispatched here: Ticket's `updated` hook
 * already sends the ticket when payment_status becomes 'completed', and
 * dispatching it again here was sending every WhatsApp twice.
 */
class TicketActivationService
{
    public const SOURCE_VENTIQ_ONLINE    = 'ventiq_online';
    public const SOURCE_ORGANIZER_DIRECT = 'organizer_direct';

    /**
     * @return bool true if this call activated the ticket, false if it was
     *              already active (repeated callback, double click) and
     *              nothing was changed.
     */
    public function activate(
        Ticket $ticket,
        string $source,
        ?string $paymentMethod = null,
        ?string $paymentReference = null,
        ?int $confirmedBy = null,
        ?PaymentSession $paymentSession = null,
    ): bool {
        $activated = DB::transaction(function () use ($ticket, $source, $paymentMethod, $paymentReference, $confirmedBy, $paymentSession) {
            // Lock the row so two callbacks (or a callback and an organizer
            // click) arriving together can't both pass the check below.
            $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->first();

            if (!$locked || $this->isAlreadyActivated($locked)) {
                Log::info("Ticket {$ticket->id} already active, activation skipped", ['source' => $source]);
                return false;
            }

            $payment = $locked->payments()->pending()->latest()->first()
                ?? new TicketPayment([
                    'ticket_id'    => $locked->id,
                    'amount'       => max(0, (float) $locked->amount - (float) $locked->amount_paid),
                    'payment_type' => 'full',
                    'payment_date' => now(),
                ]);

            $payment->fill([
                'status'            => 'approved',
                'payment_method'    => $paymentMethod ?? $payment->payment_method,
                'payment_reference' => $paymentReference ?? $payment->payment_reference,
                'approved_by'       => $confirmedBy,
                'approved_at'       => now(),
            ])->save();

            $approvedCount = $locked->payments()->approved()->count();

            $locked->update([
                'status'            => 'active',
                'payment_status'    => 'completed',
                'payment_method'    => $payment->payment_method,
                'payment_reference' => $payment->payment_reference,
                'payment_date'      => now(),
                'amount_paid'       => $locked->payments()->approved()->sum('amount'),
            ]);

            if ($approvedCount === 1) {
                $locked->tier->increment('quantity_sold');
            }

            if ($source === self::SOURCE_VENTIQ_ONLINE) {
                $this->createSettlementItem($locked, $paymentSession);
            }

            $ticket->setRawAttributes($locked->getAttributes(), true);

            return true;
        });

        if ($activated) {
            if ($ticket->client?->email) {
                dispatch(new SendTicketApprovedEmail($ticket->id))->afterResponse();
            }

            Log::info("Ticket {$ticket->id} activated", ['source' => $source, 'by' => $confirmedBy]);
        }

        return $activated;
    }

    private function isAlreadyActivated(Ticket $ticket): bool
    {
        return in_array($ticket->status, ['active', 'checked_in'], true)
            && $ticket->payment_status === 'completed';
    }

    /**
     * Ventiq's ticketing fee comes off at settlement; attendees pay the
     * sticker price. Only gateway money (VENTIQ-collected) is settled, so
     * organizer-direct activations never create one.
     */
    private function createSettlementItem(Ticket $ticket, ?PaymentSession $paymentSession): void
    {
        if (SettlementItem::where('ticket_id', $ticket->id)->exists()) {
            return;
        }

        $ticketAmount = (float) $ticket->amount;

        $percentFee = round($ticketAmount * (float) config('constants.ticketing_fee.percent'), 2);
        $ventiqFee  = round($percentFee + (float) config('constants.ticketing_fee.flat'), 2);

        SettlementItem::create([
            'settlement_id'      => null,
            'payment_session_id' => $paymentSession?->id,
            'ticket_id'          => $ticket->id,
            'organization_id'    => $ticket->event->organization_id,
            'ticket_amount'      => $ticketAmount,
            'gross_paid'         => $ticketAmount,
            'gateway_fee'        => $ventiqFee,       // repurposed field: Ventiq's ticketing fee, not a payment-processor fee
            'amount_received'    => $ticketAmount,
            'amount_owed_to_org' => max(round($ticketAmount - $ventiqFee, 2), 0),
        ]);
    }
}
