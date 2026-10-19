<?php

namespace App\Http\Controllers\Ventiq;

use App\Http\Controllers\Controller;
use App\Models\EventTier;
use App\Support\Admin\AdminTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};
use Inertia\Inertia;

/** Every ticket type on VENTIQ, with changes to many at once: price, and on sale or not. */
class TiersController extends Controller
{
    public function index(Request $request)
    {
        $table = AdminTable::for(EventTier::query()->with('event.organization'), $request)
            ->search(fn ($q, $term) => $q->where('tier_name', 'like', AdminTable::like($term))
                ->orWhereHas('event', fn ($e) => $e->where('name', 'like', AdminTable::like($term))))
            ->sorts(['name' => 'tier_name', 'price' => 'price', 'sold' => 'quantity_sold', 'created' => 'created_at'], 'created')
            ->filters([
                'active' => fn ($q, $v) => $q->where('is_active', $v === 'yes'),
                'low'    => fn ($q) => $q->whereNotNull('quantity_available')->whereRaw('quantity_sold >= quantity_available * 0.8'),
                'event'  => fn ($q, $v) => $q->where('event_id', (int) $v),
            ]);

        return Inertia::render('Ventiq/Tiers', [
            'rows'  => $table->paginate()->through(fn (EventTier $t) => [
                'id'        => $t->id,
                'name'      => $t->tier_name,
                'event'     => ['id' => $t->event_id, 'name' => $t->event?->name, 'organizationId' => $t->event?->organization_id, 'organization' => $t->event?->organization?->name],
                'price'     => (float) $t->price,
                'available' => $t->quantity_available,
                'sold'      => (int) $t->quantity_sold,
                'admits'    => max(1, (int) ($t->quantity_per_purchase ?? 1)),
                'color'     => $t->color,
                'active'    => (bool) $t->is_active,
            ]),
            'state' => $table->state(),
            'meta'  => ['title' => 'Ticket types | VENTIQ'],
        ]);
    }

    /**
     * Many ticket types at once: a new price (set, or up or down by a
     * percentage), or on sale or not. A new price applies to tickets
     * bought from now on; tickets already issued keep theirs.
     */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids'    => ['required', 'array', 'max:200'],
            'ids.*'  => ['integer'],
            'action' => ['required', 'in:set_price,change_price,activate,deactivate'],
            'value'  => ['required_if:action,set_price,change_price', 'nullable', 'numeric'],
        ]);
        $tiers = EventTier::whereIn('id', $data['ids']);
        $value = (float) ($data['value'] ?? 0);

        $count = DB::transaction(fn () => match ($data['action']) {
            'set_price'    => $value >= 0 ? $tiers->update(['price' => round($value, 2)]) : 0,
            'change_price' => $tiers->get()->each(fn (EventTier $t) => $t->update(['price' => max(0, round((float) $t->price * (1 + $value / 100), 2))]))->count(),
            'activate'     => $tiers->update(['is_active' => true]),
            'deactivate'   => $tiers->update(['is_active' => false]),
        });
        Log::info("Super admin {$request->user()->id} changed {$count} ticket types", $data);

        return back()->with('status', "{$count} ticket " . ($count === 1 ? 'type' : 'types') . ' updated.');
    }
}
