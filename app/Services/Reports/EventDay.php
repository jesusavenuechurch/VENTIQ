<?php

namespace App\Services\Reports;

use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * What an organizer watches at the door: how many people are in against
 * how many are expected, per ticket type, who arrived last, and which
 * tickets never reached the attendee's WhatsApp.
 *
 * People, not tickets: a table of 3 expects 3 and counts each scan.
 * Arrival times are a ticket's first scan (no per-scan log is kept).
 */
class EventDay
{
    public const VALID = ['active', 'checked_in'];

    private function __construct(private Event $event) {}

    public static function for(Event $event): self
    {
        return new self($event);
    }

    public function summary(): array
    {
        $row = $this->event->tickets()
            ->whereIn('status', self::VALID)
            ->selectRaw('COUNT(*) as tickets, COALESCE(SUM(COALESCE(admissions, 1)), 0) as expected, COALESCE(SUM(admitted_count), 0) as admitted')
            ->selectRaw('SUM(CASE WHEN admitted_count > 0 THEN 1 ELSE 0 END) as tickets_arrived')
            ->first();

        $unpaid = (int) $this->event->tickets()->where('status', 'pending')->sum(\DB::raw('COALESCE(admissions, 1)'));
        $expected = (int) $row->expected;
        $admitted = (int) $row->admitted;

        return [
            'expected'        => $expected,
            'admitted'        => $admitted,
            'still_to_come'   => max(0, $expected - $admitted),
            'percent'         => $expected ? (int) floor($admitted / $expected * 100) : 0,
            'tickets'         => (int) $row->tickets,
            'tickets_arrived' => (int) $row->tickets_arrived,
            'unpaid_people'   => $unpaid,
        ];
    }

    /** Expected and admitted people per ticket type, biggest first. */
    public function byTier(): Collection
    {
        return $this->event->tiers()
            ->withSum(['tickets as expected' => fn ($q) => $q->whereIn('status', self::VALID)], 'admissions')
            ->withSum(['tickets as admitted' => fn ($q) => $q->whereIn('status', self::VALID)], 'admitted_count')
            ->get()
            ->map(fn ($tier) => [
                'name'     => $tier->tier_name,
                'expected' => (int) $tier->expected,
                'admitted' => (int) $tier->admitted,
            ])
            ->filter(fn ($t) => $t['expected'] > 0)
            ->sortByDesc('expected')
            ->values();
    }

    /** The latest arrivals. */
    public function recent(int $limit = 12): Collection
    {
        return $this->event->tickets()
            ->with(['client', 'tier'])
            ->whereNotNull('checked_in_at')
            ->latest('checked_in_at')
            ->limit($limit)
            ->get();
    }

    /**
     * People admitted by the hour of their ticket's first scan, from the
     * first arrival to the last, with empty hours kept so gaps show.
     *
     * @return Collection<int, array{hour: \Carbon\Carbon, people: int}>
     */
    public function arrivalsByHour(): Collection
    {
        $tickets = $this->event->tickets()
            ->whereNotNull('checked_in_at')
            ->get(['checked_in_at', 'admitted_count']);

        if ($tickets->isEmpty()) {
            return collect();
        }

        $byHour = $tickets->groupBy(fn ($t) => $t->checked_in_at->copy()->startOfHour()->timestamp)
            ->map(fn ($group) => (int) $group->sum(fn ($t) => max(1, (int) $t->admitted_count)));

        $hours = collect();
        $start = $tickets->min('checked_in_at')->copy()->startOfHour();
        $end = $tickets->max('checked_in_at')->copy()->startOfHour();
        // At least six hours, so a first arrival isn't one wall-sized bar.
        if ($start->diffInHours($end) < 5) {
            $start = $end->copy()->subHours(5);
        }
        for ($h = $start->copy(); $h->lte($end) && $hours->count() < 48; $h->addHour()) {
            $hours->push(['hour' => $h->copy(), 'people' => $byHour[$h->timestamp] ?? 0]);
        }

        return $hours;
    }

    /** Valid tickets set to go to WhatsApp: delivered, failed, not yet sent. */
    public function whatsapp(): array
    {
        $base = fn () => $this->event->tickets()->whereIn('status', self::VALID)->where('has_whatsapp', true);

        return [
            'sent'    => $base()->whereNotNull('whatsapp_delivered_at')->count(),
            'failed'  => $base()->with('client')->where('delivery_status', 'failed')->whereNull('whatsapp_delivered_at')->latest()->get(),
            'unsent'  => $base()->whereNull('whatsapp_delivered_at')->where(fn ($q) => $q->whereNull('delivery_status')->orWhere('delivery_status', '!=', 'failed'))->count(),
        ];
    }
}
