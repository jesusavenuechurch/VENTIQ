<?php

namespace App\Console\Commands;

use App\Services\Khoebo\{KhoeboClient, KhoeboException, KhoeboOrders as Orders};
use Illuminate\Console\Command;

/**
 * Published events that should have a Khoebo order and don't (it failed
 * when they were published): make them. Runs hourly.
 */
class KhoeboOrders extends Command
{
    protected $signature = 'khoebo:orders';
    protected $description = "Make Khoebo orders for published events that don't have one yet";

    public function handle(Orders $orders, KhoeboClient $khoebo): int
    {
        if (! $khoebo->configured()) {
            return self::SUCCESS;
        }

        foreach ($orders->missing()->get() as $event) {
            if ($orders->reasonNotToOrder($event)) {
                continue;   // e.g. no ticket numbers yet
            }
            try {
                $order = $orders->make($event);
                $this->line("{$event->name}: order {$order['reference']}");
            } catch (KhoeboException $e) {
                $this->warn("{$event->name}: {$e->getMessage()}");
                if ($e->status === null || $e->status >= 500) {
                    break;  // Khoebo unreachable: try again next hour
                }
            }
        }

        return self::SUCCESS;
    }
}
