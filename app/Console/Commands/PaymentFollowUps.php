<?php

namespace App\Console\Commands;

use App\Models\{PaymentSession, Ticket};
use App\Services\Notifications\AttendeeNotifier;
use Illuminate\Console\Command;

/**
 * People whose online payment failed (or got no answer) and who then left
 * without paying: one message, 10 minutes later, with their ticket link
 * to try again, pay another way, or send proof. Once per ticket.
 */
class PaymentFollowUps extends Command
{
    protected $signature = 'tickets:payment-follow-ups';
    protected $description = 'Tell attendees whose online payment failed how to finish paying';

    public function handle(AttendeeNotifier $notifier): int
    {
        // Each ticket's most recent push from the last day, at least 10 minutes old.
        $latest = PaymentSession::where('payable_type', 'ticket')->whereNull('purchase_meta')
            ->where('created_at', '>', now()->subDay())
            ->orderByDesc('id')->get()->unique('payable_id')
            ->filter(fn ($s) => $s->created_at->lte(now()->subMinutes(10)));

        $sent = 0;
        foreach ($latest as $session) {
            $unanswered = $session->status === 'failed'
                || ($session->status === 'pending' && ($session->callback_payload['no_answer'] ?? false));
            if (!$unanswered) {
                continue;
            }

            $ticket = Ticket::with(['client', 'event.organization', 'payments'])->find($session->payable_id);
            if (!$ticket || $ticket->status !== 'pending' || $ticket->payment_status === 'completed') {
                continue;
            }

            $sessions = PaymentSession::where('payable_type', 'ticket')->where('payable_id', $ticket->id)->get();
            $alreadyTold   = $sessions->contains(fn ($s) => isset($s->callback_payload['followed_up_at']));
            $paidOtherwise = $sessions->contains(fn ($s) => $s->status === 'completed' || ($s->status === 'pending' && $s->purchase_meta))
                || $ticket->payments->contains(fn ($p) => $p->status === 'pending' && $p->submitted_at);
            if ($alreadyTold || $paidOtherwise) {
                continue;
            }

            $session->update(['callback_payload' => array_merge($session->callback_payload ?? [], ['followed_up_at' => now()->toIso8601String()])]);
            $notifier->paymentFailed($ticket);
            $sent++;
        }

        $this->info("{$sent} attendee(s) told how to finish paying.");

        return self::SUCCESS;
    }
}
