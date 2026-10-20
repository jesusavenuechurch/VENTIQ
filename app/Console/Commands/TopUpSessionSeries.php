<?php

namespace App\Console\Commands;

use App\Models\SessionSeries;
use App\Services\Sessions\SeriesScheduler;
use Illuminate\Console\Command;

/** Keeps every running series' next meetings made, as the weeks go by. */
class TopUpSessionSeries extends Command
{
    protected $signature = 'sessions:top-up-series';

    protected $description = "Make the next meetings of every repeating session series";

    public function handle(SeriesScheduler $scheduler): int
    {
        $made = 0;
        SessionSeries::whereNull('ended_at')->with('organization')->each(function (SessionSeries $series) use ($scheduler, &$made) {
            $made += $scheduler->topUp($series);
        });

        $this->info("{$made} meetings made.");

        return self::SUCCESS;
    }
}
