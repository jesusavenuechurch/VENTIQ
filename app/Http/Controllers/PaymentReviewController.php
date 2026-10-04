<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Organizer\Concerns\DecidesPayments;
use App\Models\TicketPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * The page behind the organizer's "Review payment" link: one payment, one
 * question. The signed, expiring URL is the credential, so it works from
 * a phone without logging in. Decisions are recorded as made via the
 * link (no user) unless the organizer happens to be logged in.
 */
class PaymentReviewController extends Controller
{
    use DecidesPayments;

    public function show(Request $request, TicketPayment $payment)
    {
        $payment->load(['ticket.client', 'ticket.event.organization', 'ticket.tier', 'paymentAccount']);

        return view('payment-review.show', [
            'payment'   => $payment,
            'decided'   => !$this->isUndecided($payment),
            'actionUrl' => URL::temporarySignedRoute(
                'payment-review.decide',
                now()->addHour(),
                ['payment' => $payment->id],
            ),
        ]);
    }

    public function decide(Request $request, TicketPayment $payment)
    {
        $payment->load(['ticket.client', 'ticket.event.organization']);

        if (!$this->isUndecided($payment)) {
            $message = 'This payment has already been dealt with.';
        } else {
            $decidedBy = $request->user()?->organization_id === $payment->ticket->event->organization_id
                ? $request->user()->id
                : null;

            $message = $this->applyDecision($request, $payment, $decidedBy);
        }

        return view('payment-review.done', ['payment' => $payment->fresh(['ticket.client', 'ticket.event']), 'message' => $message]);
    }
}
