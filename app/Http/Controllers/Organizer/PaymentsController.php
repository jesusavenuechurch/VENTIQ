<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Organizer\Concerns\DecidesPayments;
use App\Models\TicketPayment;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    use DecidesPayments;

    /** Payments attendees have submitted that the organizer hasn't decided yet, oldest first. */
    public function index(Request $request)
    {
        $organization = $request->attributes->get('organization');

        $payments = TicketPayment::query()
            ->with(['ticket.client', 'ticket.event', 'ticket.tier', 'paymentAccount'])
            ->awaitingDecisionFor($organization)
            ->when($request->integer('event'), fn ($q, $eventId) => $q->whereHas('ticket', fn ($t) => $t->where('event_id', $eventId)))
            ->orderBy('submitted_at')
            ->get();

        return \Inertia\Inertia::render('Organizer/Payments', [
            'payments'  => $payments->map(fn (TicketPayment $p) => [
                ...\App\Support\Organizer\PaymentCard::for($p, $p->proof_path ? route('organizer.payments.proof', $p) : null),
                'action' => route('organizer.payments.decide', $p),
            ])->values(),
            'canDecide' => $request->user()->can('approve_payment'),
            'meta'      => ['title' => 'Payments to confirm | VENTIQ'],
        ]);
    }

    public function proof(Request $request, TicketPayment $payment)
    {
        abort_unless($payment->ticket?->event?->organization_id === $request->attributes->get('organization')->id, 404);

        return \App\Support\PaymentProof::response($payment->proof_path);
    }

    public function decide(Request $request, TicketPayment $payment)
    {
        abort_unless($payment->ticket?->event?->organization_id === $request->attributes->get('organization')->id, 404);
        abort_unless($request->user()->can('approve_payment'), 403);

        if (!$this->isUndecided($payment)) {
            return back()->with('status', 'That payment has already been dealt with.');
        }

        return back()->with('status', $this->applyDecision($request, $payment, $request->user()->id));
    }
}
