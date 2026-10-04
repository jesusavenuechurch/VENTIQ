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

        return view('organizer.home', compact('events', 'toConfirm'));
    }
}
