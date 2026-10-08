<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\Khoebo\{KhoeboException, KhoeboOrders};
use Illuminate\Console\Command;

/** Make an event's draft order in Khoebo (its possible fees). */
class KhoeboOrder extends Command
{
    protected $signature = 'khoebo:order {event : The event id}';
    protected $description = "Make an event's draft order in Khoebo from its ticket numbers";

    public function handle(KhoeboOrders $orders): int
    {
        $event = Event::with('organization')->find($this->argument('event'));
        if (! $event) {
            $this->error('No event with that id.');
            return self::FAILURE;
        }

        $possible = $orders->possible($event);
        $this->line("{$event->name}: {$possible['people']} people × M" . config('constants.fees.operational_per_person')
            . " = M{$possible['person_fee']}, plus M{$possible['sales_fee']} on M{$possible['sales']} possible sales: M{$possible['total']}.");

        try {
            $order = $orders->make($event);
        } catch (KhoeboException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Made Khoebo order {$order['reference']} (draft), total M" . ($order['totals']['grand_total'] ?? '?') . '.');

        return self::SUCCESS;
    }
}
