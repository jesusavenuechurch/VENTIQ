<?php

namespace App\Http\Controllers;

use App\Models\PaymentSession;
use App\Models\Ticket;
use App\Models\TicketPayment;
use App\Services\MopayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\SettlementItem;

/**
 * The fallback gateway, for tickets only. Its checkout is retired (410);
 * the return page still settles payments started before that.
 */
class MopayController extends Controller
{
    public function __construct(private MopayService $mopay) {}

    // =========================================================================
    // TICKET PAYMENT
    // =========================================================================

    public function initiateTicketPayment(Request $request)
    {
        // Retired: it charged a 5% surcharge that was recorded nowhere, and
        // no page links to it. Tickets are paid through PayLesotho.
        abort(410, 'This payment link is no longer used.');

        $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
        ]);

        $ticket = Ticket::with(['client', 'event', 'tier', 'event.organization'])->findOrFail($request->ticket_id);

        DB::beginTransaction();
        try {
            $internalRef = 'TKT' . $ticket->id . 'T' . time();

            $surchargeRate = config('constants.payment.surcharge_rate');
            $grossAmount   = round($ticket->amount * (1 + $surchargeRate), 2);

            $session = $this->mopay->createSession([
                'amount'        => $grossAmount,
                'reference'     => $internalRef,
                'redirectUrl'   => route('online-payment.ticket.callback'),
                'description'   => $ticket->event->name . ' — ' . $ticket->tier->tier_name,
                'customerEmail' =>  $ticket->client->email ?? 'noreply@ventiq.com',
                'customerName'  => $ticket->holder_name,
            ]);

            PaymentSession::create([
                'payable_type'      => 'ticket',
                'payable_id'        => $ticket->id,
                'mopay_reference'   => $internalRef,
                'mopay_session_id'  => $session['sessionId'],
                'mopay_payment_url' => $session['paymentUrl'],
                'amount'            => $ticket->amount,
                'status'            => 'pending',
                'organization_id'   => $ticket->event->organization_id,
                'initiated_by'      => null,
            ]);

            $ticket->update(['payment_reference' => $internalRef]);
            $ticket->payments()->latest()->first()?->update(['payment_reference' => $internalRef]);

            DB::commit();

            return redirect()->away($session['paymentUrl']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MoPay ticket initiation failed', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);

            return redirect()->route('ticket.registered', $ticket->qr_code)->with('error', 'Could not initiate payment. Please try another method.');
        }
    }

    public function ticketCallback(Request $request)
    {
        $sessionId = $request->query('sessionId');

        Log::info('MoPay ticket callback', $request->query());

        if (!$sessionId) {
            return redirect('/')->with('error', 'Invalid payment callback.');
        }

        $paymentSession = PaymentSession::where('mopay_session_id', $sessionId)
            ->where('payable_type', 'ticket')
            ->first();

        if (!$paymentSession) {
            Log::error('MoPay: ticket session not found', ['sessionId' => $sessionId]);
            return redirect('/')->with('error', 'Payment session not found.');
        }

        $ticket = Ticket::with(['client', 'event', 'tier', 'event.organization', 'payments'])->find($paymentSession->payable_id);

        if (!$ticket) {
            return redirect('/')->with('error', 'Ticket not found.');
        }

        $confirmationRoute = route('ticket.registered', $ticket->qr_code);

        try {
            $verified = $this->mopay->verifySession($sessionId);
        } catch (\Exception $e) {
            Log::error('MoPay ticket verification failed', ['error' => $e->getMessage()]);
            return redirect($confirmationRoute)->with('error', 'Could not verify payment. Please contact support.');
        }

        $paymentSession->update([
            'callback_payload' => array_merge($request->query(), ['verified' => $verified]),
            'payment_method'   => $verified['selectedPaymentMethod'] ?? $request->query('paymentMethod'),
            'transaction_id'   => $verified['transactionId'] ?? null,
        ]);

        if (($verified['status'] ?? '') === 'COMPLETED') {
            $paymentSession->update(['status' => 'completed']);
            $this->approveTicket($ticket, $verified, $paymentSession);

            return redirect()->route('ticket.download', [
                'qr_code' => $ticket->qr_code,
            ]);
        }

        if (in_array($verified['status'], ['FAILED', 'CANCELLED', 'EXPIRED'])) {
            $paymentSession->update(['status' => strtolower($verified['status'])]);

            $message = match($verified['status']) {
                'CANCELLED' => 'Payment was cancelled. You can try again.',
                'FAILED'    => 'Payment failed. Please try again.',
                default     => 'Payment expired. Please try again.',
            };

            return redirect($confirmationRoute)->with('error', $message);
        }

        return redirect($confirmationRoute)->with('warning', 'Payment is being processed. Your ticket will activate shortly.');
    }

    private function approveTicket(Ticket $ticket, array $verified, PaymentSession $paymentSession): void
    {
        app(\App\Services\Payments\TicketActivationService::class)->activate(
            ticket: $ticket,
            source: \App\Services\Payments\TicketActivationService::SOURCE_VENTIQ_ONLINE,
            paymentMethod: $verified['selectedPaymentMethod'] ?? 'mopay',
            paymentReference: $verified['transactionId'] ?? null,
            paymentSession: $paymentSession,
        );
    }
}