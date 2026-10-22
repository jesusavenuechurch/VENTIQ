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

// The day after an event: its Khoebo invoice for what the organizer owes.
\Illuminate\Support\Facades\Schedule::command('khoebo:invoice')->dailyAt('07:20')->withoutOverlapping();

// Repeating sessions: each series keeps its next meetings on the desk.
\Illuminate\Support\Facades\Schedule::command('sessions:top-up-series')->dailyAt('05:40')->withoutOverlapping();

// Action points from sessions: reminders before and on the due date, weekly when overdue.
\Illuminate\Support\Facades\Schedule::command('sessions:action-reminders')->dailyAt('07:05')->withoutOverlapping();
// Programme certificates, for days that ended today (and days that were only checked in to).
\Illuminate\Support\Facades\Schedule::command('sessions:issue-certificates')->dailyAt('18:50')->withoutOverlapping();
// Ask Ventiq: embed passages that waited for the Mac mini.
\Illuminate\Support\Facades\Schedule::command('sessions:index-memory')->hourlyAt(17)->withoutOverlapping();
// Corporate Sessions invoices for last month, on the 1st.
\Illuminate\Support\Facades\Schedule::command('sessions:invoice')->monthlyOn(1, '06:10')->withoutOverlapping();
// Paid Sessions plans and sessions that didn't reach the Sessions books (Khoebo down, say).
\Illuminate\Support\Facades\Schedule::command('sessions:khoebo')->hourlyAt(25)->withoutOverlapping();
