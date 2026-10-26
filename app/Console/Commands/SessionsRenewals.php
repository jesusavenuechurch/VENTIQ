<?php

namespace App\Console\Commands;

use App\Services\Sessions\PlanRenewals;
use Illuminate\Console\Command;

/** Paid Sessions plans: renewal reminders, grace notices, and plans past their grace back to Free. Daily. */
class SessionsRenewals extends Command
{
    protected $signature = 'sessions:renewals';

    protected $description = 'Remind organizations to renew their Sessions plan, and end plans left unpaid past their grace';

    public function handle(PlanRenewals $renewals): int
    {
        $done = $renewals->run();
        $this->info("{$done['due']} reminded, {$done['grace']} on grace, {$done['lapsed']} back to Free.");

        return self::SUCCESS;
    }
}
