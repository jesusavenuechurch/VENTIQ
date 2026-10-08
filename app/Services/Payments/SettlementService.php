<?php

namespace App\Services\Payments;

use App\Models\{Settlement, SettlementItem, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Log};
use InvalidArgumentException;

/**
 * Paying organizers the online money VENTIQ collected for them.
 *
 * Each online payment already has a payout line (SettlementItem) with
 * VENTIQ's fee taken off. A batch claims exactly the unpaid lines for
 * tickets that are still valid, in one transaction, and its totals are the
 * sum of those lines; marking it paid re-adds them, so the figure paid is
 * always the lines'. A line belongs to one batch only, so nothing is paid
 * twice.
 */
class SettlementService
{
    /** Unpaid lines for valid tickets: what the organization is owed now. */
    public function payable(int $organizationId)
    {
        return SettlementItem::where('organization_id', $organizationId)
            ->whereNull('settlement_id')
            ->whereHas('ticket', fn ($q) => $q->whereIn('status', ['active', 'checked_in']));
    }

    /** @return array{lines: int, received: float, fees: float, owed: float} */
    public function totals(Collection $items): array
    {
        return [
            'lines'    => $items->count(),
            'received' => round((float) $items->sum('amount_received'), 2),
            'fees'     => round((float) $items->sum('gateway_fee'), 2),
            'owed'     => round((float) $items->sum('amount_owed_to_org'), 2),
        ];
    }

    /** Batch everything owed to an organization; null when nothing is. */
    public function createBatch(int $organizationId, string $trigger = 'manual', ?User $by = null): ?Settlement
    {
        return DB::transaction(function () use ($organizationId, $trigger, $by) {
            $items = $this->payable($organizationId)->lockForUpdate()->get();
            if ($items->isEmpty()) {
                return null;
            }

            $t = $this->totals($items);
            $settlement = Settlement::create([
                'organization_id'    => $organizationId,
                'trigger_type'       => $trigger,
                'gross_paid'         => (float) $items->sum('gross_paid'),
                'gateway_fees'       => $t['fees'],      // VENTIQ's fees, as on the lines
                'amount_received'    => $t['received'],
                'amount_owed_to_org' => $t['owed'],
                'ventiq_revenue'     => $t['fees'],
                'status'             => 'pending',
            ]);

            SettlementItem::whereIn('id', $items->pluck('id'))->update(['settlement_id' => $settlement->id]);
            Log::info("Settlement {$settlement->id} created", ['organization' => $organizationId, 'by' => $by?->id] + $t);

            return $settlement;
        });
    }

    /** Record that the organizer has been paid this batch. */
    public function markPaid(Settlement $settlement, string $method, string $reference, ?string $notes, User $by): Settlement
    {
        return DB::transaction(function () use ($settlement, $method, $reference, $notes, $by) {
            $settlement = Settlement::whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            if ($settlement->isSettled()) {
                throw new InvalidArgumentException('This payout is already marked as paid.');
            }

            $t = $this->totals($settlement->items()->get());
            $settlement->update([
                'amount_received'      => $t['received'],
                'gateway_fees'         => $t['fees'],
                'amount_owed_to_org'   => $t['owed'],
                'ventiq_revenue'       => $t['fees'],
                'status'               => 'settled',
                'settlement_method'    => $method,
                'settlement_reference' => $reference,
                'notes'                => $notes,
                'settled_at'           => now(),
                'settled_by'           => $by->id,
            ]);
            Log::info("Settlement {$settlement->id} paid", ['by' => $by->id, 'reference' => $reference] + $t);

            return $settlement;
        });
    }
}
