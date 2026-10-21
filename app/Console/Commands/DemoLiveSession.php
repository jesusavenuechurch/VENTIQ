<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Database\Seeders\Demo\LiveDemoSession;
use Database\Seeders\SessionsDemoSeeder;
use Illuminate\Console\Command;

/** Before a demo: the demo organization's live session, started 25 minutes ago again. */
class DemoLiveSession extends Command
{
    protected $signature = 'sessions:demo-live';

    protected $description = "Make the Sessions demo's live session happening now again";

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Not in production.');

            return self::FAILURE;
        }
        $organization = Organization::where('email', SessionsDemoSeeder::TRUST_EMAIL)->first();
        if (!$organization) {
            $this->error('No demo organization. Run: php artisan db:seed --class=SessionsDemoSeeder');

            return self::FAILURE;
        }

        $session = LiveDemoSession::refresh($organization);
        $this->info("\"{$session->title}\" is happening now, started at " . substr($session->start_time, 0, 5) . '.');

        return self::SUCCESS;
    }
}
