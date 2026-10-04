<?php
// app/Services/Payments/TicketActivationService.php
namespace App\Services\Payments;

use App\Jobs\SendTicketApprovedEmail;
use App\Models\{PaymentSession, SettlementItem, Ticket, TicketPayment};
use App\Services\Notifications\AttendeeNotifier;
use App\Support\PaymentWindow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * The one place a payment is confirmed or rejected and a ticket goes from
 * inactive to active.
 *
 * Every path calls in here: the PayLesotho and MoPay callbacks, the
 * Filament Activate action, and the organizer's review page and payments
 * list. Before this, four copies of the logic disagreed: one never updated
 * the payment row, one counted the sale twice, and repeated gateway
 * callbacks each created a fresh SettlementItem.
 *
 * Attendee delivery for fully paid tickets is left to Ticket's `updated`
 * hook, which sends the ticket when payment_status becomes 'completed';
 * dispatching it here as well was sending every WhatsApp twice. A ticket
 * activated with a balance still due never reaches 'completed', so it is
 * sent explicitly.
 */
class TicketActivationService
{
    public const SOURCE_VENTIQ_ONLINE    = 'ventiq_online';
    public const SOURCE_ORGANIZER_DIRECT = 'organizer_direct';

    /**
     * Confirm a payment and activate the ticket. Without $payment, the
     * ticket's latest pending payment is used (or one is recorded for the
     * outstanding amount, as the Filament action and gateways expect).
     *
     * A payment smaller than what's owed activates the ticket with a
     * balance due (payment_status 'partial'): only the organizer gets here
     * with a deposit, and Activate is their explicit choice.
     *
     * @return bool true if this call activated the ticket, false if it was
     *              already fully activated (repeated callback, double click)
     *              and nothing was changed.
     */
    public function activate(
        Ticket $ticket,
        string $source,
        ?string $paymentMethod = null,
        ?string $paymentReference = null,
        ?int $confirmedBy = null,
        ?PaymentSession $paymentSession = null,
        ?TicketPayment $payment = null,
    ): bool {
        $wasActive = false;

        $activated = DB::transaction(function () use ($ticket, $source, $paymentMethod, $paymentReference, $confirmedBy, $paymentSession, $payment, &$wasActive) {
            // Lock the row so two callbacks (or a callback and an organizer
            // click) arriving together can't both pass the check below.
            $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->first();

            if (!$locked || $this->isFullyActivated($locked)) {
                Log::info("Ticket {$ticket->id} already active, activation skipped", ['source' => $source]);
                return false;
            }

            $wasActive = $locked->status === 'active';

            $payment = $this->pendingPaymentFor($locked, $payment)
                ?? new TicketPayment([
                    'ticket_id'    => $locked->id,
                    'amount'       => max(0, (float) $locked->amount - (float) $locked->amount_paid),
                    'payment_type' => 'full',
                    'payment_date' => now(),
                ]);

            $this->approve($payment, $source, $confirmedBy, $paymentMethod, $paymentReference);

            $paid = (float) $locked->payments()->approved()->sum('amount');

            $locked->update([
                'status'            => $locked->status === 'checked_in' ? 'checked_in' : 'active',
                'payment_status'    => $paid >= (float) $locked->amount ? 'completed' : 'partial',
                'payment_method'    => $payment->payment_method,
                'payment_reference' => $payment->payment_reference,
                'payment_date'      => now(),
                'amount_paid'       => $paid,
                'payment_due_at'    => null,
            ]);

            // Counted once, when the ticket first becomes active.
            if (!$wasActive && $locked->wasChanged('status')) {
                $locked->tier->increment('quantity_sold');
            }

            if ($source === self::SOURCE_VENTIQ_ONLINE) {
                $this->createSettlementItem($locked, $paymentSession);
            }

            $ticket->setRawAttributes($locked->getAttributes(), true);

            return true;
        });

        if ($activated && !$wasActive) {
            if ($ticket->payment_status !== 'completed') {
                // Balance still due: the 'completed' hook won't fire.
                dispatch(fn () => $ticket->fresh()->autoDeliverTicket())->afterResponse();
            }

            if ($ticket->client?->email) {
                dispatch(new SendTicketApprovedEmail($ticket->id))->afterResponse();
            }

            Log::info("Ticket {$ticket->id} activated", ['source' => $source, 'by' => $confirmedBy]);
        }

        return $activated;
    }

    /**
     * Record a deposit the organizer has received while keeping the ticket
     * inactive: the money is confirmed, entry isn't (yet).
     */
    public function confirmDeposit(TicketPayment $payment, ?int $confirmedBy = null): void
    {
        DB::transaction(function () use ($payment, $confirmedBy) {
            $ticket = Ticket::whereKey($payment->ticket_id)->lockForUpdate()->firstOrFail();
            $payment = $this->pendingPaymentFor($ticket, $payment)
                ?? throw new InvalidArgumentException('This payment has already been decided.');

            $this->approve($payment, self::SOURCE_ORGANIZER_DIRECT, $confirmedBy);

            $paid = (float) $ticket->payments()->approved()->sum('amount');

            $ticket->update([
                'amount_paid'    => $paid,
                'payment_status' => $paid >= (float) $ticket->amount ? 'completed' : 'partial',
                // Money has arrived, so the place is no longer at risk.
                'payment_due_at' => null,
            ]);
        });

        Log::info("Deposit confirmed on ticket {$payment->ticket_id}", ['payment' => $payment->id, 'by' => $confirmedBy]);
    }

    /**
     * The organizer says this money never arrived. The ticket stays
     * inactive, the attendee is told and can submit again, and the payment
     * window restarts so the place is held while they do.
     */
    public function reject(TicketPayment $payment, ?int $decidedBy = null, ?string $reason = null): void
    {
        $ticket = DB::transaction(function () use ($payment, $decidedBy, $reason) {
            $ticket = Ticket::with('event')->whereKey($payment->ticket_id)->lockForUpdate()->firstOrFail();
            $payment = $this->pendingPaymentFor($ticket, $payment)
                ?? throw new InvalidArgumentException('This payment has already been decided.');

            $payment->update([
                'status'      => 'rejected',
                'approved_by' => $decidedBy,
                'approved_at' => now(),
                'notes'       => $reason,
            ]);

            if ($ticket->status === 'pending') {
                $ticket->update(['payment_due_at' => PaymentWindow::dueAt($ticket->event)]);
            }

            return $ticket;
        });

        app(AttendeeNotifier::class)->paymentRejected($ticket, $reason);

        Log::info("Payment {$payment->id} rejected on ticket {$payment->ticket_id}", ['by' => $decidedBy]);
    }

    /** The given payment if it's still pending on this ticket, else the latest pending one. */
    private function pendingPaymentFor(Ticket $ticket, ?TicketPayment $payment): ?TicketPayment
    {
        $pending = $ticket->payments()->pending();

        return $payment
            ? $pending->whereKey($payment->id)->first()
            : $pending->latest()->first();
    }

    private function approve(TicketPayment $payment, string $source, ?int $by, ?string $method = null, ?string $reference = null): void
    {
        $payment->fill([
            'status'            => 'approved',
            'source'            => $source,
            'payment_method'    => $method ?? $payment->payment_method,
            'payment_reference' => $reference ?? $payment->payment_reference,
            'approved_by'       => $by,
            'approved_at'       => now(),
        ])->save();
    }

    private function isFullyActivated(Ticket $ticket): bool
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
