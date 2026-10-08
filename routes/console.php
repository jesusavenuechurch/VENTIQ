<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Payment windows: release unpaid places and send halfway reminders.
// Needs the scheduler running (`php artisan schedule:run` every minute).
\Illuminate\Support\Facades\Schedule::command('tickets:expire-unpaid')->everyFifteenMinutes()->withoutOverlapping();

// Online payments that failed and weren't finished: a nudge with the ticket link.
\Illuminate\Support\Facades\Schedule::command('tickets:payment-follow-ups')->everyFiveMinutes()->withoutOverlapping();

// Accounts that never created anything and haven't been used for two months:
// warned a week ahead, then removed.
\Illuminate\Support\Facades\Schedule::command('accounts:remove-unused')->dailyAt('06:10')->withoutOverlapping();

// Published events whose Khoebo order didn't go through (Khoebo down, say).
\Illuminate\Support\Facades\Schedule::command('khoebo:orders')->hourly()->withoutOverlapping();
