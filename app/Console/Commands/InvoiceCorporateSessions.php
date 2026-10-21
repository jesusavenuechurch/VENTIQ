<?php

namespace App\Console\Commands;

use App\Services\Sessions\CorporateBilling;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** On the 1st: each corporate organization's invoice for last month's sessions (or --month=2026-10). */
class InvoiceCorporateSessions extends Command
{
    protected $signature = 'sessions:invoice {--month= : The month to invoice, YYYY-MM; last month by default}';

    protected $description = "Invoice corporate organizations for the month's sessions, in Khoebo and by email";

    public function handle(CorporateBilling $billing): int
    {
        $month = $this->option('month') ? Carbon::createFromFormat('Y-m-d', $this->option('month') . '-01') : now()->subMonthNoOverflow();
        $made = 0;
        foreach ($billing->due($month) as $organization) {
            if ($invoice = $billing->invoice($organization, $month)) {
                $made++;
                $this->line("{$invoice->number}: {$organization->name}, M" . number_format((float) $invoice->total, 2) . ($invoice->khoebo_reference ? " ({$invoice->khoebo_reference})" : ($invoice->khoebo_error ? ' (not in Khoebo yet)' : '')));
            }
        }
        $this->info("{$made} invoice(s) for " . $month->format('F Y') . '.');

        return self::SUCCESS;
    }
}
