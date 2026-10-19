<?php

namespace App\Http\Controllers\Ventiq;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Fees\FeeService;
use App\Support\Admin\AdminTable;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Every event on VENTIQ, for super admins: find it, sponsor its fees, open it in its organizer view. */
class EventsController extends Controller
{
    public function index(Request $request)
    {
        $table = AdminTable::for(Event::query()->with('organization')->withCount(['tickets', 'tiers']), $request)
            ->search(fn ($q, $term) => $q->where('name', 'like', AdminTable::like($term))
                ->orWhere('slug', 'like', AdminTable::like($term))
                ->orWhereHas('organization', fn ($o) => $o->where('name', 'like', AdminTable::like($term))))
            ->sorts(['date' => 'event_date', 'name' => 'name', 'tickets' => 'tickets_count', 'created' => 'created_at'], 'date')
            ->filters([
                'status'    => fn ($q, $v) => $q->where('status', $v),
                'when'      => fn ($q, $v) => $v === 'past' ? $q->where('event_date', '<', now()) : $q->where('event_date', '>=', now()->startOfDay()),
                'sponsored' => fn ($q, $v) => $q->where('fees_sponsored', $v === 'yes'),
                'mode'      => fn ($q, $v) => $q->where('payment_mode', $v),
            ]);

        return Inertia::render('Ventiq/Events', [
            'rows'     => $table->paginate()->through(fn (Event $e) => [
                'id'           => $e->id,
                'name'         => $e->name,
                'organization' => ['id' => $e->organization_id, 'name' => $e->organization?->name],
                'date'         => $e->event_date?->format('j M Y, H:i'),
                'status'       => $e->status,
                'mode'         => $e->payment_mode,
                'public'       => (bool) $e->is_public,
                'sponsored'    => (bool) $e->fees_sponsored,
                'tickets'      => $e->tickets_count,
                'tiers'        => $e->tiers_count,
                'url'          => $e->share_url,
            ]),
            'state'    => $table->state(),
            'statuses' => collect(config('constants.event_statuses'))->map(fn ($label, $value) => compact('value', 'label'))->values(),
            'meta'     => ['title' => 'Events | VENTIQ'],
        ]);
    }

    /** VENTIQ waives (or resumes) its fees on an event. */
    public function sponsor(Request $request, Event $event, FeeService $fees)
    {
        $fees->setSponsored($event, !$event->fees_sponsored, $request->user());

        return back()->with('status', $event->fresh()->fees_sponsored
            ? "VENTIQ is now sponsoring {$event->name}'s fees."
            : "VENTIQ fees are charged on {$event->name} again.");
    }
}
