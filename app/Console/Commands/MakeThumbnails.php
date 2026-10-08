<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Support\Thumb;
use Illuminate\Console\Command;

/** Make the small copies of every event poster already uploaded (run once after deploy). */
class MakeThumbnails extends Command
{
    protected $signature = 'images:thumbs';
    protected $description = 'Make small WebP copies of event posters for faster pages';

    public function handle(): int
    {
        $made = 0;
        Event::whereNotNull('banner_image')->pluck('banner_image')->unique()->each(function ($path) use (&$made) {
            Thumb::makeAll($path);
            $made++;
        });

        $this->info("{$made} poster(s) done.");

        return self::SUCCESS;
    }
}
