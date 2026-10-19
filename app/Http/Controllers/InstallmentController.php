<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;

/**
 * "Find my ticket": the phone it was registered with plus its ticket
 * number or entry code (VQ-…) opens its private link, where the
 * balance is paid like any other payment (online, or to the organizer,
 * who confirms it). The old per-ticket installment page took payments the
 * organizer never saw; it's gone.
 */
class InstallmentController extends Controller
{
    public function search()
    {
        return view('public.installment.search');
    }

    public function find(Request $request)
    {
        $data = $request->validate([
            'ticket_number' => 'required|string|max:50',
            'phone'         => 'required|string|max:20',
        ]);

        $digits = preg_replace('/\D/', '', $data['phone']);
        $phone  = '+' . (str_starts_with($digits, '266') ? $digits : '266' . $digits);

        // Ticket number or entry code, typed any which way.
        $code = preg_replace('/\s+/', '', $data['ticket_number']);
        $ticket = Ticket::where(fn ($q) => $q->where('ticket_number', $code)->orWhere(fn ($v) => $v->byVoucherCode($code)))
            ->whereHas('client', fn ($q) => $q->where('phone', $phone))
            ->latest('id')->first();

        if (!$ticket) {
            return back()->withErrors(['ticket_number' => 'No ticket matches that phone and code. Check both and try again, or ask the organizer to send your ticket.'])->withInput();
        }

        return $ticket->payment_status === 'completed'
            ? redirect()->route('ticket.download', $ticket->qr_code)
            : redirect()->route('ticket.pay', $ticket->qr_code);
    }

    /** Old /installment/{id} links: the number isn't a credential. */
    public function show(int $ticket)
    {
        $found = Ticket::with(['client', 'event.organization'])->find($ticket);

        return app(LegacyTicketLinkController::class)->show(
            $found?->event?->organization?->slug ?? '', $found?->event?->slug ?? '', $ticket,
        );
    }
}
