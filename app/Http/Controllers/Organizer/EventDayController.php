<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Reports\EventDay;
use Illuminate\Http\Request;

/** The door view: check-in progress, arrivals and undelivered tickets. */
class EventDayController extends Controller
{
    public function show(Request $request, Event $event)
    {
        abort_unless($event->organization_id === $request->attributes->get('organization')->id, 404);

        $day = EventDay::for($event);
        $live = [
            'event'    => $event,
            'summary'  => $day->summary(),
            'tiers'    => $day->byTier(),
            'recent'   => $day->recent(),
            'arrivals' => $day->arrivalsByHour(),
        ];

        // The page refreshes its live numbers every 30 seconds.
        if ($request->boolean('partial')) {
            return view('organizer.partials.event-day-live', $live);
        }

        return view('organizer.event-day', $live + ['whatsapp' => $day->whatsapp()]);
    }
}
