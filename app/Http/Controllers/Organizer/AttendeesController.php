<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\{Event, Ticket};
use App\Support\{PaymentWindow, TierCapacity};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Who registered for an event, grouped by where their ticket stands, with
 * the two corrections an organizer needs: bring an expired ticket back,
 * or cancel one that isn't going to be paid.
 */
class AttendeesController extends Controller
{
    public const FILTERS = [
        'all'        => 'All',
        'to_confirm' => 'Payment to confirm',
        'awaiting'   => 'Awaiting payment',
        'active'     => 'Active',
        'used'       => 'Used',
        'expired'    => 'Expired',
        'cancelled'  => 'Cancelled',
    ];

    public function index(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);

        $filter = array_key_exists($request->query('filter'), self::FILTERS) ? $request->query('filter') : 'all';
        $submitted = fn ($p) => $p->where('status', 'pending')->whereNotNull('submitted_at');

        $tickets = $event->tickets()
            ->with(['client', 'tier', 'payments'])
            ->when($filter === 'to_confirm', fn ($q) => $q->whereIn('status', ['pending', 'active'])->whereHas('payments', $submitted))
            ->when($filter === 'awaiting', fn ($q) => $q->where('status', 'pending')->whereDoesntHave('payments', $submitted))
            ->when($filter === 'active', fn ($q) => $q->where('status', 'active'))
            ->when($filter === 'used', fn ($q) => $q->where('status', 'checked_in'))
            ->when($filter === 'expired', fn ($q) => $q->where('status', 'expired'))
            ->when($filter === 'cancelled', fn ($q) => $q->whereIn('status', ['cancelled', 'void', 'refunded']))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('organizer.attendees', [
            'event'   => $event,
            'finance' => \App\Services\Reports\EventFinance::for($event)->summary(),
            'tickets' => $tickets,
            'filter'  => $filter,
            'filters' => self::FILTERS,
        ]);
    }

    /** Give an expired ticket a fresh payment window, if its tier still has room. */
    public function reinstate(Request $request, Ticket $ticket)
    {
        $this->authorizeEvent($request, $ticket->event);
        abort_unless($request->user()->can('approve_payment'), 403);

        if ($ticket->status !== 'expired') {
            return back()->with('status', 'Only expired tickets can be reinstated.');
        }

        if (!TierCapacity::hasRoom($ticket->tier)) {
            return back()->with('status', "{$ticket->tier->tier_name} is full, so this ticket can't be reinstated.");
        }

        $ticket->update(['status' => 'pending', 'payment_due_at' => PaymentWindow::dueAt($ticket->event)]);
        Log::info("Ticket {$ticket->id} reinstated by user {$request->user()->id}");

        return back()->with('status', "{$ticket->client->full_name}'s ticket is reserved again.");
    }

    /** Withdraw an unpaid ticket. Paid tickets need a refund decision first, so they're left alone. */
    public function cancel(Request $request, Ticket $ticket)
    {
        $this->authorizeEvent($request, $ticket->event);
        abort_unless($request->user()->can('approve_payment'), 403);

        if (!in_array($ticket->status, ['pending', 'expired'], true)) {
            return back()->with('status', 'Only unpaid tickets can be cancelled here.');
        }

        $ticket->update(['status' => 'cancelled', 'payment_due_at' => null]);
        Log::info("Ticket {$ticket->id} cancelled by user {$request->user()->id}");

        return back()->with('status', "{$ticket->client->full_name}'s ticket has been cancelled.");
    }

    private function authorizeEvent(Request $request, Event $event): void
    {
        abort_unless($event->organization_id === $request->attributes->get('organization')->id, 404);
    }
}
