<?php

namespace App\Http\Controllers\Organizer\Concerns;

use App\Models\TicketPayment;
use App\Services\Payments\TicketActivationService;
use Illuminate\Http\Request;

/**
 * The organizer's answer to "has this payment been received?", shared by
 * the payments list and the signed review link.
 *
 *  - activate: yes, let them in (a deposit activates with a balance due)
 *  - deposit:  yes, but it's a deposit; keep the ticket inactive
 *  - reject:   no, the money hasn't arrived
 */
trait DecidesPayments
{
    protected function applyDecision(Request $request, TicketPayment $payment, ?int $decidedBy): string
    {
        $data = $request->validate([
            'decision' => 'required|in:activate,deposit,reject',
            'reason'   => 'nullable|string|max:500',
        ]);

        $service = app(TicketActivationService::class);
        $ticket = $payment->ticket;

        switch ($data['decision']) {
            case 'activate':
                $service->activate(
                    ticket: $ticket,
                    source: TicketActivationService::SOURCE_ORGANIZER_DIRECT,
                    confirmedBy: $decidedBy,
                    payment: $payment,
                );
                return "Ticket activated for {$ticket->client->full_name}.";

            case 'deposit':
                $service->confirmDeposit($payment, $decidedBy);
                return "Deposit recorded for {$ticket->client->full_name}. The ticket stays inactive until you activate it.";

            default:
                $service->reject($payment, $decidedBy, $data['reason'] ?? null);
                return "Payment rejected. {$ticket->client->full_name} has been asked to submit it again.";
        }
    }

    protected function isUndecided(TicketPayment $payment): bool
    {
        return $payment->status === 'pending';
    }
}
