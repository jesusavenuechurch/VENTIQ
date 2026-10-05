<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\{Client, Event, Ticket};
use App\Services\TicketDeliveryService;
use App\Services\Tickets\ComplimentaryTicketService;
use App\Support\{Phone, TierCapacity};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Tickets the organizer hands out or looks after themselves: complimentary
 * tickets, and resending a ticket that didn't reach someone.
 */
class TicketsController extends Controller
{
    public function createComp(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);

        $tiers = $event->tiers()->orderBy('price')->get()
            ->each(fn ($tier) => $tier->setAttribute('is_full', !TierCapacity::hasRoom($tier)));

        return view('organizer.comp', [
            'event'  => $event,
            'tiers'  => $tiers,
            'feeEach' => (float) config('constants.fees.operational_per_person'),
        ]);
    }

    public function storeComp(Request $request, Event $event, ComplimentaryTicketService $comps)
    {
        $this->authorizeEvent($request, $event);

        $request->merge(['phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone')]);
        $data = $request->validate([
            'event_tier_id' => ['required', Rule::exists('event_tiers', 'id')->where('event_id', $event->id)],
            'full_name'     => ['required', 'string', 'max:255'],
            'phone'         => ['required', 'regex:/^\+[0-9]{9,15}$/'],
            'email'         => ['nullable', 'email', 'max:255'],
            'reason'        => ['nullable', 'string', 'max:255'],
            'send_whatsapp' => ['nullable', 'boolean'],
        ], [
            'phone.regex' => 'Enter a Lesotho number (8 digits) or a full international number starting with +.',
        ]);

        try {
            $ticket = $comps->issue(
                $event,
                $event->tiers()->findOrFail($data['event_tier_id']),
                ['full_name' => $data['full_name'], 'phone' => $data['phone'], 'email' => $data['email'] ?? null],
                $request->user(),
                $data['reason'] ?? null,
                $request->boolean('send_whatsapp'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['event_tier_id' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.attendees', $event)->with('status',
            "{$ticket->client->full_name} is on the guest list!" . ($request->boolean('send_whatsapp') ? ' Their ticket is on its way on WhatsApp.' : ''));
    }

    /**
     * Send a valid ticket to the attendee's WhatsApp again, optionally to a
     * corrected number: a wrong number is the usual reason it never arrived.
     */
    public function resend(Request $request, Ticket $ticket, TicketDeliveryService $delivery)
    {
        $this->authorizeEvent($request, $ticket->event);

        if (!in_array($ticket->status, ['active', 'checked_in'], true)) {
            return back()->with('status', 'Only active tickets can be sent. This one isn\'t valid for entry yet.');
        }

        $client = $ticket->client;

        if ($request->filled('phone')) {
            $phone = Phone::normalize($request->input('phone'));
            if (!$phone) {
                return back()->with('status', 'That phone number doesn\'t look right. Use 8 digits, or a full number starting with +.');
            }

            if ($phone !== $client->phone) {
                $taken = Client::where('organization_id', $client->organization_id)->where('phone', $phone)->whereKeyNot($client->id)->first();
                if ($taken) {
                    return back()->with('status', "{$phone} already belongs to {$taken->full_name}, so it can't be moved to {$client->full_name}.");
                }
                $client->update(['phone' => $phone]);
            }
        }

        // Each send is a paid WhatsApp template; one a minute per ticket
        // stops a double tap from sending it twice.
        if (!Cache::add("ticket-resend:{$ticket->id}", true, 60)) {
            return back()->with('status', 'This ticket was just sent. Give it a minute before sending again.');
        }

        $ticket->update(['has_whatsapp' => true, 'preferred_delivery' => $ticket->client->email ? 'both' : 'whatsapp']);
        $sent = $delivery->deliver($ticket->fresh(['client', 'event', 'tier']));

        return back()->with('status', $sent
            ? "Ticket sent to {$client->full_name} on WhatsApp ({$client->phone})."
            : "WhatsApp didn't accept the ticket for {$client->phone}. Check the number, or share the ticket link with {$client->full_name} directly.");
    }

    private function authorizeEvent(Request $request, Event $event): void
    {
        abort_unless($event->organization_id === $request->attributes->get('organization')->id, 404);
    }
}
