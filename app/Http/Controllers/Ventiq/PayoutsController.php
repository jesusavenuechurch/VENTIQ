<?php

namespace App\Http\Controllers\Ventiq;

use App\Http\Controllers\Controller;
use App\Models\Settlement;
use App\Support\Admin\AdminTable;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Every payout batch VENTIQ has made to organizers, and the payments in each. Making and paying them is on the money page. */
class PayoutsController extends Controller
{
    public function index(Request $request)
    {
        $table = AdminTable::for(Settlement::query()->with('organization')->withCount('items'), $request)
            ->search(fn ($q, $term) => $q->where('settlement_reference', 'like', AdminTable::like($term))
                ->orWhereHas('organization', fn ($o) => $o->where('name', 'like', AdminTable::like($term))))
            ->sorts(['created' => 'created_at', 'amount' => 'amount_owed_to_org', 'settled' => 'settled_at'], 'created')
            ->filters([
                'status'       => fn ($q, $v) => $q->where('status', $v),
                'organization' => fn ($q, $v) => $q->where('organization_id', (int) $v),
            ]);

        return Inertia::render('Ventiq/Payouts', [
            'rows'   => $table->paginate()->through(fn (Settlement $s) => [
                'id'           => $s->id,
                'organization' => ['id' => $s->organization_id, 'name' => $s->organization?->name],
                'created'      => $s->created_at?->format('j M Y'),
                'trigger'      => $s->trigger_type,
                'items'        => $s->items_count,
                'received'     => (float) $s->amount_received,
                'fees'         => (float) $s->gateway_fees,
                'owed'         => (float) $s->amount_owed_to_org,
                'status'       => $s->status,
                'settled'      => $s->settled_at?->format('j M Y'),
                'method'       => $s->settlement_method,
                'reference'    => $s->settlement_reference,
            ]),
            'state'  => $table->state(),
            // The payments in one batch, when it's opened (?batch=).
            'batch'  => fn () => ($id = $request->integer('batch')) ? $this->batch($id) : null,
            'meta'   => ['title' => 'Payouts | VENTIQ'],
        ]);
    }

    /** @return array<string, mixed>|null */
    private function batch(int $id): ?array
    {
        $settlement = Settlement::with(['organization', 'items.ticket.client', 'items.ticket.event', 'items.paymentSession'])->find($id);

        return $settlement ? [
            'id'           => $settlement->id,
            'organization' => $settlement->organization?->name,
            'notes'        => $settlement->notes,
            'items'        => $settlement->items->map(fn ($item) => [
                'id'       => $item->id,
                'attendee' => $item->ticket?->client?->full_name,
                'event'    => $item->ticket?->event?->name,
                'via'      => trim(ucfirst((string) $item->paymentSession?->payment_method) . ' ' . $item->paymentSession?->transaction_id),
                'received' => (float) $item->amount_received,
                'fee'      => (float) $item->gateway_fee,
                'owed'     => (float) $item->amount_owed_to_org,
            ])->values(),
        ] : null;
    }
}
