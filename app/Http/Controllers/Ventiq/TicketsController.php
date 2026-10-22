<?php

namespace App\Http\Controllers\Ventiq;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\TicketDeliveryService;
use App\Support\Admin\AdminTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

/**
 * Every ticket on VENTIQ, for super admins helping someone find theirs:
 * by name, phone, email, ticket number or entry code. Changing a ticket
 * (mark paid, cancel) happens in its event's organizer view.
 */
class TicketsController extends Controller
{
    public function index(Request $request)
    {
        $table = AdminTable::for(Ticket::query()->with(['client', 'tier', 'event.organization']), $request)
            ->search(function ($q, $term) {
                $digits = preg_replace('/\D/', '', $term);
                $q->where('ticket_number', 'like', AdminTable::like($term))
                    ->orWhere('voucher_code', 'like', AdminTable::like($term))
                    ->orWhere('attendee_name', 'like', AdminTable::like($term))
                    ->orWhereHas('client', fn ($c) => $c->where('full_name', 'like', AdminTable::like($term))
                        ->orWhere('email', 'like', AdminTable::like($term))
                        ->when(strlen($digits) >= 4, fn ($c) => $c->orWhere('phone', 'like', '%' . $digits . '%')));
            })
            ->sorts(['created' => 'created_at', 'amount' => 'amount'], 'created')
            ->filters([
                'status'  => fn ($q, $v) => $q->where('status', $v),
                'payment' => fn ($q, $v) => $q->where('payment_status', $v),
                'comp'    => fn ($q, $v) => $q->where('is_complimentary', $v === 'yes'),
                'event'   => fn ($q, $v) => $q->where('event_id', (int) $v),
            ]);

        return Inertia::render('Ventiq/Tickets', [
            'rows'  => $table->paginate()->through(fn (Ticket $t) => [
                'id'           => $t->id,
                'holder'       => $t->holder_name,
                'phone'        => $t->client?->phone,
                'email'        => $t->client?->email,
                'number'       => $t->ticket_number,
                'code'         => $t->voucher_code,
                'tier'         => $t->tier?->tier_name,
                'amount'       => (float) $t->amount,
                'status'       => $t->status,
                'payment'      => $t->payment_status,
                'comp'         => (bool) $t->is_complimentary,
                'delivery'     => $t->delivery_status,
                'event'        => ['id' => $t->event_id, 'name' => $t->event?->name, 'organizationId' => $t->event?->organization_id, 'organization' => $t->event?->organization?->name],
                'ticketUrl'    => in_array($t->status, ['active', 'checked_in'], true) && $t->qr_code ? route('ticket.download', $t->qr_code) : null,
                'created'      => $t->created_at?->format('j M Y, H:i'),
            ]),
            'state' => $table->state(),
            'meta'  => ['title' => 'Tickets | VENTIQ'],
        ]);
    }

    /** Send a valid ticket to its holder's WhatsApp again. */
    public function resend(Ticket $ticket, TicketDeliveryService $delivery)
    {
        if (!in_array($ticket->status, ['active', 'checked_in'], true)) {
            return back()->with('error', "This ticket isn't valid for entry yet, so it can't be sent.");
        }
        // Each send is a paid WhatsApp template: one a minute per ticket.
        if (!Cache::add("ticket-resend:{$ticket->id}", true, 60)) {
            return back()->with('error', 'This ticket was just sent. Give it a minute before sending again.');
        }

        $ticket->update(['has_whatsapp' => true, 'preferred_delivery' => $ticket->client?->email ? 'both' : 'whatsapp']);
        $sent = $delivery->deliver($ticket->fresh(['client', 'event', 'tier']));

        return back()->with($sent ? 'status' : 'error', $sent
            ? "Ticket sent to {$ticket->holder_name} on WhatsApp ({$ticket->client->phone})."
            : "WhatsApp didn't accept the ticket for {$ticket->client->phone}. Check the number in the event's organizer view.");
    }
}
