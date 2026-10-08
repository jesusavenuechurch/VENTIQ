<?php

namespace App\Console\Commands;

use App\Models\PaymentSession;
use App\Services\Payments\UnfinishedPaymentFollowUp;
use Illuminate\Console\Command;

/**
 * People whose online payment failed (or got no answer) and who then left
 * without paying. Out of tries, they're told at once (ChargeMobileMoney);
 * this catches the rest 30 minutes after their last try.
 */
class PaymentFollowUps extends Command
{
    protected $signature = 'tickets:payment-follow-ups';
    protected $description = 'Tell attendees whose online payment failed how to finish paying, and their organizer';

    public function handle(UnfinishedPaymentFollowUp $followUp): int
    {
        $sent = PaymentSession::where('payable_type', 'ticket')->whereNull('purchase_meta')
            ->where('created_at', '>', now()->subDay())
            ->orderByDesc('id')->get()->unique('payable_id')
            ->filter(fn ($session) => $followUp->consider($session))
            ->count();

        $this->info("{$sent} unfinished payment(s) followed up.");

        return self::SUCCESS;
    }
}
