<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\{Event, TicketPayment};
use Illuminate\Http\Request;

/**
 * The organizer's landing page: their events, and how many payments are
 * waiting on them.
 */
class HomeController extends Controller
{
    public function index(Request $request)
    {
        $organization = $request->attributes->get('organization');

        $events = Event::where('organization_id', $organization->id)
            ->ticketed()
            ->withCount([
                'tickets as active_count'   => fn ($q) => $q->whereIn('status', ['active', 'checked_in']),
                'tickets as awaiting_count' => fn ($q) => $q->where('status', 'pending')
                    ->whereDoesntHave('payments', fn ($p) => $p->where('status', 'pending')->whereNotNull('submitted_at')),
                'tickets as to_confirm_count' => fn ($q) => $q->whereIn('status', ['pending', 'active'])
                    ->whereHas('payments', fn ($p) => $p->where('status', 'pending')->whereNotNull('submitted_at')),
            ])
            ->orderByDesc('event_date')
            ->get();

        $toConfirm = TicketPayment::where('status', 'pending')
            ->whereNotNull('submitted_at')
            ->whereHas('ticket', fn ($q) => $q->whereIn('status', ['pending', 'active'])
                ->whereHas('event', fn ($e) => $e->where('organization_id', $organization->id)))
            ->count();

        // Money collected so far per event, from both sources.
        $events->each(fn ($event) => $event->setAttribute(
            'collected_total',
            \App\Services\Reports\EventFinance::for($event)->summary()['collected'],
        ));

        return \Inertia\Inertia::render('Organizer/Home', [
            'toConfirm'  => $toConfirm,
            'events'     => $events->map(fn (Event $e) => [
                'id'         => $e->id,
                'name'       => $e->name,
                'date'       => $e->event_date?->format('d M Y'),
                'status'     => $e->status,
                'isToday'    => (bool) $e->event_date?->isToday(),
                'active'     => (int) $e->active_count,
                'awaiting'   => (int) $e->awaiting_count,
                'toConfirm'  => (int) $e->to_confirm_count,
                'collected'  => (float) $e->collected_total,
                'sponsored'  => (bool) $e->fees_sponsored,
                'share'      => \App\Support\Organizer\EventCard::share($e),
            ])->values(),
            'scannerApp' => \App\Support\Organizer\EventCard::scannerApp(),
            'meta'       => ['title' => 'Events | VENTIQ'],
        ]);
    }
}
