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

    public function proof(TicketPayment $payment)
    {
        return \App\Support\PaymentProof::response($payment->proof_path);
    }

    public function show(Request $request, TicketPayment $payment)
    {
        $payment->load(['ticket.client', 'ticket.event.organization', 'ticket.tier', 'paymentAccount']);

        $proofUrl = $payment->proof_path ? URL::temporarySignedRoute('payment-review.proof', now()->addHour(), ['payment' => $payment->id]) : null;

        return \Inertia\Inertia::render('PaymentReview/Show', [
            'organization' => $payment->ticket->event->organization->name,
            'decided'      => !$this->isUndecided($payment),
            'status'       => $payment->status,
            'payment'      => [
                ...\App\Support\Organizer\PaymentCard::for($payment, $proofUrl),
                // The signed link is the credential: it works without signing in.
                'action' => URL::temporarySignedRoute('payment-review.decide', now()->addHour(), ['payment' => $payment->id]),
            ],
            'meta'         => ['title' => 'Review payment | VENTIQ'],
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

        return \Inertia\Inertia::render('PaymentReview/Done', [
            'message' => $message,
            'event'   => $payment->ticket->event->name,
            'meta'    => ['title' => 'Payment reviewed | VENTIQ'],
        ]);
    }
}
