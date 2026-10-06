<?php

namespace App\Services\Payments;

use App\Http\Controllers\PayLesothoController;
use App\Models\{PaymentSession, Ticket};
use App\Services\Notifications\{AttendeeNotifier, OrganizerNotifier};

/**
 * Someone tried to pay online and it didn't go through. Once per ticket we
 * send the attendee their link to try again, pay another way or send proof,
 * and tell the organizer there's an unpaid ticket:
 *   - straight away once all their tries are used up, or
 *   - 30 minutes after their last try, if they stopped before that.
 */
class UnfinishedPaymentFollowUp
{
    public const WAIT_MINUTES = 30;

    public function __construct(
        private AttendeeNotifier $attendees,
        private OrganizerNotifier $organizers,
    ) {}

    /** Sends the follow-up if it's due for this push. True when it was sent. */
    public function consider(PaymentSession $session): bool
    {
        if ($session->payable_type !== 'ticket' || $session->purchase_meta) {
            return false;
        }

        $unanswered = $session->status === 'failed'
            || ($session->status === 'pending' && ($session->callback_payload['no_answer'] ?? false));
        if (!$unanswered) {
            return false;
        }

        $ticket = Ticket::with(['client', 'tier', 'event.organization', 'payments'])->find($session->payable_id);
        if (!$ticket || $ticket->status !== 'pending' || $ticket->payment_status === 'completed') {
            return false;
        }

        $sessions = PaymentSession::where('payable_type', 'ticket')->where('payable_id', $ticket->id)->get();

        // Only the latest try counts: an older failure followed by a newer push isn't the end.
        if ($sessions->where('purchase_meta', null)->max('id') !== $session->id) {
            return false;
        }

        $alreadyTold   = $sessions->contains(fn ($s) => isset($s->callback_payload['followed_up_at']));
        $paidOtherwise = $sessions->contains(fn ($s) => $s->status === 'completed' || ($s->status === 'pending' && $s->purchase_meta))
            || $ticket->payments->contains(fn ($p) => $p->status === 'pending' && $p->submitted_at);
        if ($alreadyTold || $paidOtherwise) {
            return false;
        }

        $outOfTries = PayLesothoController::attemptsLeft($ticket) === 0;
        if (!$outOfTries && $session->created_at->gt(now()->subMinutes(self::WAIT_MINUTES))) {
            return false;
        }

        $session->update(['callback_payload' => array_merge($session->callback_payload ?? [], ['followed_up_at' => now()->toIso8601String()])]);
        $this->attendees->paymentFailed($ticket);
        $this->organizers->paymentUnfinished($ticket, PayLesothoController::pushesFor($ticket)->count());

        return true;
    }
}
