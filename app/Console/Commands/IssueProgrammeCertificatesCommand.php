<?php

namespace App\Console\Commands;

use App\Jobs\IssueProgrammeCertificates;
use App\Models\Event;
use Illuminate\Console\Command;

/** Every evening: certificates for programmes that had a day recently (including days only checked in to). */
class IssueProgrammeCertificatesCommand extends Command
{
    protected $signature = 'sessions:issue-certificates';

    protected $description = 'Issue programme certificates to everyone who now meets the rule';

    public function handle(): int
    {
        $programmes = Event::where('is_programme', true)
            ->where('certificates_enabled', true)
            ->whereHas('sessions', fn ($q) => $q->whereDate('date', '<=', today())->whereDate('date', '>=', today()->subDays(30)))
            ->pluck('id');

        $programmes->each(fn ($id) => IssueProgrammeCertificates::dispatchSync($id));
        $this->info("Checked {$programmes->count()} programme(s).");

        return self::SUCCESS;
    }
}
