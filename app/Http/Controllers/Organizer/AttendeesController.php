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
        $search = trim((string) $request->query('q'));

        $tickets = $event->tickets()
            ->with(['client', 'tier', 'payments'])
            ->when($search !== '', fn ($q) => $this->search($q, $search))
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
            'search'  => $search,
            'filters' => self::FILTERS,
        ]);
    }

    /**
     * Name, phone (any format, matched on its last digits), email, ticket
     * number or the entry code printed under the QR (VQ-XXXX).
     */
    private function search($query, string $term)
    {
        $digits = preg_replace('/\D/', '', $term);
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        return $query->where(fn ($q) => $q
            ->where('ticket_number', 'like', $like)
            ->orWhere('voucher_code', 'like', $like)
            ->orWhere('attendee_name', 'like', $like)
            ->orWhereHas('client', fn ($c) => $c
                ->where('full_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->when(strlen($digits) >= 4, fn ($c) => $c->orWhere('phone', 'like', '%' . $digits . '%'))));
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

        return back()->with('status', "{$ticket->holder_name}'s ticket is reserved again.");
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

        return back()->with('status', "{$ticket->holder_name}'s ticket has been cancelled.");
    }

    /** Super admins only: VENTIQ waives (or resumes) its fees on this event. */
    public function toggleFeeSponsorship(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);
        abort_unless($request->user()->isSuperAdmin(), 403);

        app(\App\Services\Fees\FeeService::class)->setSponsored($event, !$event->fees_sponsored, $request->user());

        return back()->with('status', $event->fresh()->fees_sponsored
            ? 'VENTIQ is now sponsoring this event\'s fees.'
            : 'VENTIQ fees are charged on this event again.');
    }

    /** Super admin: invoice the event now, for a customer paying ahead of it. */
    public function khoeboInvoiceNow(Request $request, Event $event, \App\Services\Khoebo\KhoeboInvoices $invoices)
    {
        $this->authorizeEvent($request, $event);
        abort_unless($request->user()->isSuperAdmin(), 403);

        try {
            $made = $invoices->invoiceNow($event->load('organization'));
        } catch (\App\Services\Khoebo\KhoeboException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', "Invoice {$made['reference']} for M" . number_format($made['total'], 2) . ' is in Khoebo. Anything beyond it is billed the day after the event.');
    }

    /** Super admin: a payment from the organizer against the event's Khoebo invoice. */
    public function khoeboPayment(Request $request, Event $event, \App\Services\Khoebo\KhoeboPayments $payments)
    {
        $this->authorizeEvent($request, $event);
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'date'      => ['required', 'date', 'before_or_equal:today'],
            'method'    => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Services\Khoebo\KhoeboPayments::METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
            'balance'   => ['nullable', 'boolean'],
        ]);

        try {
            $payments->record($event, (float) $data['amount'], $data['date'], $data['method'], $data['reference'] ?? null, $request->user(), $request->boolean('balance'));
        } catch (\App\Services\Khoebo\KhoeboException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', 'M' . number_format((float) $data['amount'], 2) . ' recorded in Khoebo.');
    }

    private function authorizeEvent(Request $request, Event $event): void
    {
        abort_unless($event->organization_id === $request->attributes->get('organization')->id, 404);
    }
}
