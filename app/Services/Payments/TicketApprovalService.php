<?php
// app/Services/Payments/TicketApprovalService.php
namespace App\Services\Payments;

use App\Models\{PaymentSession, SettlementItem, Ticket};
use App\Jobs\SendTicketApprovedEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketApprovalService
{
    /**
     * @param string $gateway 'paylesotho' | 'mopay'
     */
    public function approve(Ticket $ticket, string $gateway, string $paymentMethod, ?string $gatewayReference, ?PaymentSession $paymentSession = null): void
    {
        DB::transaction(function () use ($ticket, $gateway, $paymentMethod, $gatewayReference, $paymentSession) {

            $pendingPayment = $ticket->payments()->where('status', 'pending')->latest()->first();
            if ($pendingPayment) {
                $pendingPayment->update([
                    'status'            => 'approved',
                    'payment_method'    => $paymentMethod,
                    'payment_reference' => $gatewayReference,
                    'approved_by'       => null,
                    'approved_at'       => now(),
                ]);
            }

            $ticket->update([
                'payment_status'    => 'completed',
                'status'            => 'active',
                'payment_method'    => $paymentMethod,
                'payment_reference' => $gatewayReference,
                'payment_date'      => now(),
                'amount_paid'       => $ticket->amount,
            ]);

            if ($ticket->payments()->where('status', 'approved')->count() === 1) {
                $ticket->tier->increment('quantity_sold');
            }

            $this->createSettlementItem($ticket, $paymentSession);

            dispatch(function () use ($ticket) {
                $ticket->load(['client', 'event', 'tier', 'event.organization']);
                $ticket->generateQrCode();
                $ticket->autoDeliverTicket();
            })->afterResponse();

            if ($ticket->client->email) {
                dispatch(new SendTicketApprovedEmail($ticket->id))->afterResponse();
            }

            Log::info("Ticket {$ticket->id} approved via {$gateway}, settlement_item created");
        });
    }

    /**
     * Ventiq's ticketing fee: 4.9% + M7.50 per ticket, excl. VAT.
     * Replaces the old surcharge_rate/gateway_fee_rate split entirely —
     * attendees pay the sticker price, Ventiq's cut comes off at settlement.
     */
    private function createSettlementItem(Ticket $ticket, ?PaymentSession $paymentSession): void
    {
        $ticketAmount = $ticket->amount;

        $percentFee = round($ticketAmount * config('constants.ticketing_fee.percent'), 2);
        $flatFee    = config('constants.ticketing_fee.flat');
        $ventiqFee  = round($percentFee + $flatFee, 2);

        $amountOwedToOrg = round($ticketAmount - $ventiqFee, 2);

        SettlementItem::create([
            'settlement_id'      => null,
            'payment_session_id' => $paymentSession?->id,
            'ticket_id'          => $ticket->id,
            'organization_id'    => $ticket->event->organization_id,
            'ticket_amount'      => $ticketAmount,
            'gross_paid'         => $ticketAmount,   // attendee pays sticker price now, no surcharge
            'gateway_fee'        => $ventiqFee,       // repurposed field: Ventiq's ticketing fee, not a payment-processor fee
            'amount_received'    => $ticketAmount,
            'amount_owed_to_org' => max($amountOwedToOrg, 0),
        ]);
    }
}