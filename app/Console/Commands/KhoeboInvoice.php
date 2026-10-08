<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\Khoebo\{KhoeboClient, KhoeboException, KhoeboInvoices};
use Illuminate\Console\Command;

/**
 * Invoice finished events in Khoebo for what organizers actually owe.
 * With an event id: that event. Without: every event due (runs daily).
 */
class KhoeboInvoice extends Command
{
    protected $signature = 'khoebo:invoice {event? : One event id; leave out to invoice every event that is due}';
    protected $description = "Invoice finished events in Khoebo for the fees organizers actually owe";

    public function handle(KhoeboInvoices $invoices, KhoeboClient $khoebo): int
    {
        if (! $khoebo->configured()) {
            $this->warn('Khoebo is not set up: set KHOEBO_URL and KHOEBO_TOKEN.');
            return $this->argument('event') ? self::FAILURE : self::SUCCESS;
        }

        if ($id = $this->argument('event')) {
            $event = Event::with('organization')->find($id);
            if (! $event) {
                $this->error('No event with that id.');
                return self::FAILURE;
            }
            return $this->invoice($invoices, $event) ? self::SUCCESS : self::FAILURE;
        }

        foreach ($invoices->due()->get() as $event) {
            if (! $invoices->reasonNotToInvoice($event)) {
                $this->invoice($invoices, $event);
            }
        }

        return self::SUCCESS;
    }

    private function invoice(KhoeboInvoices $invoices, Event $event): bool
    {
        try {
            $actual = $invoices->actual($event);
            $invoice = $invoices->make($event);
        } catch (KhoeboException $e) {
            $this->warn($e->getMessage());
            return false;
        }

        $this->info("{$event->name}: invoice {$invoice['reference']} for M" . number_format($actual['total'], 2)
            . " ({$actual['people']} people, M" . number_format($actual['service'], 2) . ' sales fee).');

        return true;
    }
}
