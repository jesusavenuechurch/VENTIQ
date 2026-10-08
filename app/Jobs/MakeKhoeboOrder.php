<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\Khoebo\{KhoeboException, KhoeboOrders};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * A published event's order in Khoebo. Run after the response; if Khoebo
 * can't be reached the hourly khoebo:orders tries again, so a failure here
 * is only logged.
 */
class MakeKhoeboOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(public int $eventId) {}

    public function handle(KhoeboOrders $orders): void
    {
        $event = Event::with('organization')->find($this->eventId);
        if (! $event || $orders->reasonNotToOrder($event)) {
            return;
        }

        try {
            $order = $orders->make($event);
            Log::info('Khoebo order made', ['event' => $event->id, 'order' => $order['reference'] ?? $order['id']]);
        } catch (KhoeboException $e) {
            Log::warning("Khoebo order not made for event {$event->id}: {$e->getMessage()}");
        }
    }
}
