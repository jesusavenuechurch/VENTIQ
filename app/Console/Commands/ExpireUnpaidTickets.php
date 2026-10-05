<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\Notifications\AttendeeNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Releases places held by tickets nobody paid for, and nudges attendees
 * halfway through their payment window. Only tickets with a deadline are
 * touched: no payment window configured means nothing ever expires.
 *
 * A ticket waiting on the organizer never expires — submitting a payment
 * clears its deadline.
 */
class ExpireUnpaidTickets extends Command
{
    protected $signature = 'tickets:expire-unpaid';

    protected $description = 'Expire unpaid tickets past their payment window and send halfway reminders';

    public function handle(AttendeeNotifier $notifier): int
    {
        $expired = 0;
        $reminded = 0;

        Ticket::with(['client', 'event.organization'])
            ->where('status', 'pending')
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<=', now())
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', 'pending')->whereNotNull('submitted_at'))
            // An online payment still waiting for confirmation: PayLesotho's
            // callback can't be relied on yet, so VENTIQ confirms some by
            // hand; the place is kept until it's decided.
            ->whereNotExists(fn ($q) => $q->select(\DB::raw(1))->from('payment_sessions')
                ->whereColumn('payment_sessions.payable_id', 'tickets.id')
                ->where('payment_sessions.payable_type', 'ticket')
                ->where('payment_sessions.status', 'pending')
                ->where('payment_sessions.created_at', '>', now()->subDay()))
            ->chunkById(100, function ($tickets) use ($notifier, &$expired) {
                foreach ($tickets as $ticket) {
                    // Re-check under the update in case a payment arrived
                    // since the query ran.
                    $updated = Ticket::whereKey($ticket->id)
                        ->where('status', 'pending')
                        ->where('payment_due_at', '<=', now())
                        ->update(['status' => 'expired']);

                    if ($updated) {
                        $expired++;
                        Log::info("Ticket {$ticket->id} expired: payment window ended");
                        $notifier->paymentWindowExpired($ticket->fresh(['client', 'event.organization']));
                    }
                }
            });

        Ticket::with(['client', 'event.organization'])
            ->where('status', 'pending')
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '>', now())
            ->whereNull('payment_reminder_sent_at')
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', 'pending')->whereNotNull('submitted_at'))
            ->chunkById(100, function ($tickets) use ($notifier, &$reminded) {
                foreach ($tickets as $ticket) {
                    $halfway = $ticket->created_at->copy()->addSeconds(
                        (int) ($ticket->created_at->diffInSeconds($ticket->payment_due_at) / 2)
                    );

                    if (now()->lt($halfway)) {
                        continue;
                    }

                    $ticket->update(['payment_reminder_sent_at' => now()]);
                    $notifier->paymentReminder($ticket);
                    $reminded++;
                }
            });

        $this->info("Expired {$expired} ticket(s), sent {$reminded} reminder(s).");

        return self::SUCCESS;
    }
}
