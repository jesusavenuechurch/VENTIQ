<?php
// app/Http/Controllers/Api/TicketScanController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;

class TicketScanController extends Controller
{
    /**
     * Get upcoming events for the authenticated user's organization
     */
    /**
 * Get upcoming events for the authenticated user's organization
 */
public function getEvents(Request $request)
{
    try {
        $user = $request->user();

        // Events that are on, about to be, or just finished (a few days back,
        // for multi-day events), soonest first.
        $query = Event::where('status', 'published')
            ->where('event_date', '>=', now()->subDays(3)->startOfDay());

        // Filter by organization (unless super admin)
        if (!$user->hasRole('super_admin')) {
            $query->where('organization_id', $user->organization_id);
        }

        $events = $query->with(['tiers'])
            ->orderBy('event_date')
            ->get()
            ->map(function ($event) {
                $totalTickets = $event->tickets()->count();
                $checkedIn = $event->tickets()->whereNotNull('checked_in_at')->count();

                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'date' => $event->event_date,  // ← Changed from 'date' to 'event_date'
                    'date_formatted' => Carbon::parse($event->event_date)->format('F j, Y g:i A'),
                    'venue' => $event->venue ?? null,
                    'capacity' => $event->capacity ?? null,
                    'total_tickets' => $totalTickets,
                    'checked_in' => $checkedIn,
                ];
            });

        return response()->json([
            'success' => true,
            'events' => $events,
            'count' => $events->count(),
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Get events error: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'error' => 'Failed to fetch events',
        ], 500);
    }
}

    /**
     * Download all tickets for a specific event
     */
    public function downloadTickets(Request $request, int $eventId)
    {
        try {
            $user = $request->user();

            // Get event
            $event = Event::findOrFail($eventId);

            // Check permissions (unless super admin)
            if (!$user->hasRole('super_admin') && $event->organization_id !== $user->organization_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access to this event',
                ], 403);
            }

            // Get all tickets for this event
            $tickets = Ticket::where('event_id', $eventId)
                ->where('status', '!=', 'void')
                ->with(['client', 'tier'])
                ->get()
                ->map(fn ($ticket) => \App\Support\ScannerTicket::present($ticket, $eventId));

            return response()->json([
                'success' => true,
                'tickets' => $tickets,
                'count' => $tickets->count(),
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'date' => $event->event_date,
                ],
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Download tickets error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to download tickets',
            ], 500);
        }
    }

    /**
     * One ticket by QR code, live: for a ticket the phone hasn't downloaded
     * (registered after the download) or whose payment may have been
     * confirmed since. ?event_id= is the event the door is scanning.
     */
    public function verifyTicket(Request $request, string $qrCode)
    {
        $ticket = Ticket::where('qr_code', $qrCode)->with(['client', 'tier', 'event'])->first();

        $user = $request->user();
        if (!$ticket || (!$user->hasRole('super_admin') && $ticket->event?->organization_id !== $user->organization_id)) {
            return response()->json(['success' => false, 'error' => 'Ticket not found'], 404);
        }

        return response()->json([
            'success' => true,
            'ticket'  => \App\Support\ScannerTicket::present($ticket, $request->integer('event_id') ?: null),
        ]);
    }

    /**
     * Bulk check-in (sync from mobile app)
     */
    public function bulkCheckIn(Request $request)
    {
        try {
            $validated = $request->validate([
                'checkins' => 'required|array',
                'checkins.*.ticket_id' => 'required|integer',
                'checkins.*.checked_in_at' => 'required|date',
            ]);

            $user = $request->user();
            $synced = 0;
            $errors = [];
            $results = [];

            foreach ($validated['checkins'] as $checkinData) {
                try {
                    $ticket = Ticket::find($checkinData['ticket_id']);

                    if (!$ticket) {
                        $errors[] = "Ticket ID {$checkinData['ticket_id']} not found";
                        $results[] = ['ticket_id' => $checkinData['ticket_id'], 'outcome' => Ticket::SCAN_INVALID, 'checked_in' => false];
                        continue;
                    }

                    // Check permissions
                    if (!$user->hasRole('super_admin') && $ticket->event->organization_id !== $user->organization_id) {
                        $errors[] = "Unauthorized for ticket {$ticket->ticket_number}";
                        $results[] = ['ticket_id' => $ticket->id, 'outcome' => Ticket::SCAN_INVALID, 'checked_in' => false];
                        continue;
                    }

                    // The server decides, not the app: an inactive,
                    // expired or cancelled ticket is never recorded as
                    // checked in, whatever the app sent. Each entry is one
                    // admission; a group ticket accepts as many as it has.
                    $outcome = $ticket->scanOutcome();

                    if ($outcome === Ticket::SCAN_VALID && $ticket->admit($user->id, $checkinData['checked_in_at'])) {
                        $synced++;
                        $results[] = $this->checkinResult($ticket, Ticket::SCAN_VALID, true);
                        continue;
                    }

                    $outcome = $ticket->fresh()->scanOutcome();

                    // Fully used (e.g. the same scan synced twice) is
                    // reported but isn't an error, as before.
                    if ($outcome !== Ticket::SCAN_ALREADY_USED) {
                        $errors[] = "Ticket {$ticket->ticket_number} not checked in: {$outcome}";
                    }
                    $results[] = $this->checkinResult($ticket->fresh(), $outcome, false);

                } catch (\Exception $e) {
                    $errors[] = "Error with ticket {$checkinData['ticket_id']}: " . $e->getMessage();
                    \Log::error("Bulk check-in error for ticket {$checkinData['ticket_id']}: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'synced' => $synced,
                'errors' => $errors,
                'results' => $results,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Bulk check-in error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Bulk check-in failed',
            ], 500);
        }
    }

    private function checkinResult(Ticket $ticket, string $outcome, bool $checkedIn): array
    {
        return [
            'ticket_id'      => $ticket->id,
            'outcome'        => $outcome,
            'checked_in'     => $checkedIn,
            'admissions'     => $ticket->admissions,
            'admitted_count' => $ticket->admitted_count,
        ];
    }

    /**
     * Get check-in stats for an event
     */
    public function getStats(Request $request, int $eventId)
    {
        try {
            $user = $request->user();
            $event = Event::findOrFail($eventId);

            // Check permissions
            if (!$user->hasRole('super_admin') && $event->organization_id !== $user->organization_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                ], 403);
            }

            $totalTickets = $event->tickets()->count();
            $checkedIn = $event->tickets()->whereNotNull('checked_in_at')->count();
            $pending = $totalTickets - $checkedIn;

            // Get check-ins per hour (last 24 hours)
            $checkinsPerHour = $event->tickets()
                ->whereNotNull('checked_in_at')
                ->where('checked_in_at', '>=', now()->subDay())
                ->selectRaw('HOUR(checked_in_at) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->pluck('count', 'hour');

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_tickets' => $totalTickets,
                    'checked_in' => $checkedIn,
                    'pending' => $pending,
                    'percentage_checked_in' => $totalTickets > 0 ? round(($checkedIn / $totalTickets) * 100, 2) : 0,
                    'checkins_per_hour' => $checkinsPerHour,
                ],
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'date' => $event->event_date,
                ],
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Get stats error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get stats',
            ], 500);
        }
    }

    /**
     * Check sync status (what needs to be synced)
     */
    public function syncStatus(Request $request)
    {
        try {
            $user = $request->user();

            // Get events that have tickets needing sync
            $eventsQuery = Event::where('status', 'published')
                ->where('event_date', '>=', now()->subDays(1));

            if (!$user->hasRole('super_admin')) {
                $eventsQuery->where('organization_id', $user->organization_id);
            }

            $events = $eventsQuery->with('tickets')
                ->get()
                ->map(function ($event) {
                    $totalTickets = $event->tickets()->count();
                    $checkedIn = $event->tickets()->whereNotNull('checked_in_at')->count();

                    return [
                        'event_id' => $event->id,
                        'event_name' => $event->name,
                        'total_tickets' => $totalTickets,
                        'checked_in' => $checkedIn,
                        'needs_sync' => $totalTickets > 0,
                    ];
                })
                ->filter(function ($event) {
                    return $event['needs_sync'];
                })
                ->values();

            return response()->json([
                'success' => true,
                'events' => $events,
                'total_events_needing_sync' => $events->count(),
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Sync status error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get sync status',
            ], 500);
        }
    }
}