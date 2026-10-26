<?php

namespace App\Console\Commands;

use App\Services\Sessions\Pilots;
use Illuminate\Console\Command;

/** Sessions pilots: VENTIQ told a week before one ends, and those past their last day ended. Daily. */
class SessionsPilots extends Command
{
    protected $signature = 'sessions:pilots';

    protected $description = 'Tell VENTIQ about Sessions pilots ending soon, and end those past their last day';

    public function handle(Pilots $pilots): int
    {
        $done = $pilots->run();
        $this->info("{$done['ending']} ending soon, {$done['ended']} ended.");

        return self::SUCCESS;
    }
}
