<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Reports\EventDay;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** The door view: check-in progress, arrivals and undelivered tickets. */
class EventDayController extends Controller
{
    public function show(Request $request, Event $event)
    {
        abort_unless($event->organization_id === $request->attributes->get('organization')->id && $event->kind === \App\Models\Event::KIND_EVENT, 404);

        $day = EventDay::for($event);

        // The live parts reload every 30 seconds (usePoll on the page), the
        // WhatsApp list doesn't: it holds the Send forms.
        return Inertia::render('Organizer/EventDay', [
            'event'    => ['id' => $event->id, 'name' => $event->name, 'status' => $event->status],
            'summary'  => fn () => $day->summary(),
            'tiers'    => fn () => array_values($day->byTier()->all()),
            'recent'   => fn () => $day->recent()->map(fn ($t) => [
                'id'         => $t->id,
                'name'       => $t->holder_name,
                'phone'      => $t->client->phone,
                'tier'       => $t->tier->tier_name,
                'admissions' => (int) ($t->admissions ?? 1),
                'admitted'   => (int) $t->admitted_count,
                'at'         => $t->checked_in_at->format('H:i'),
            ])->values(),
            'arrivals' => fn () => $day->arrivalsByHour()->map(fn ($slot) => ['hour' => $slot['hour']->format('H:i'), 'people' => (int) $slot['people']])->values(),
            'updated'  => fn () => now()->format('H:i:s'),
            'whatsapp' => function () use ($day) {
                $w = $day->whatsapp();

                return [
                    'sent'   => $w['sent'],
                    'unsent' => $w['unsent'],
                    'failed' => $w['failed']->map(fn ($t) => [
                        'id' => $t->id, 'name' => $t->holder_name, 'phone' => $t->client->phone, 'ticketUrl' => route('ticket.download', $t->qr_code),
                    ])->values(),
                ];
            },
            'meta'     => ['title' => "{$event->name} · Event day | VENTIQ"],
        ]);
    }
}
