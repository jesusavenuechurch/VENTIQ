<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;

/**
 * "Pay the rest of my ticket": find a ticket by its number and the phone
 * it was registered with, then carry on at its private link, where the
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

        $ticket = Ticket::where('ticket_number', trim($data['ticket_number']))
            ->whereHas('client', fn ($q) => $q->where('phone', $phone))
            ->first();

        if (!$ticket) {
            return back()->withErrors(['ticket_number' => 'No ticket matches that number and phone. Please check both and try again.'])->withInput();
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
