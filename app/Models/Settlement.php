<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends Model
{
    protected $fillable = [
        'organization_id',
        'settled_by',
        'trigger_type',
        'gross_paid',
        'gateway_fees',
        'amount_received',
        'amount_owed_to_org',
        'ventiq_revenue',
        'status',
        'settlement_method',
        'settlement_reference',
        'notes',
        'settled_at',
    ];

    protected $casts = [
        'gross_paid'          => 'decimal:2',
        'gateway_fees'        => 'decimal:2',
        'amount_received'     => 'decimal:2',
        'amount_owed_to_org'  => 'decimal:2',
        'ventiq_revenue'      => 'decimal:2',
        'settled_at'          => 'datetime',
    ];

    // ===== RELATIONSHIPS =====

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    // ===== COMPUTED =====

    /**
     * Ventiq revenue is stored but can also be derived.
     * amount_received - amount_owed_to_org
     */
    public function getComputedVentiqRevenueAttribute(): float
    {
        return round($this->amount_received - $this->amount_owed_to_org, 2);
    }

    public function isSettled(): bool  { return $this->status === 'settled'; }
    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isPartial(): bool  { return $this->status === 'partial'; }

    // ===== FACTORY METHOD =====

    /**
     * Create a settlement batch from a collection of unsettled PaymentSessions.
     * Call this from the Filament action or scheduled job.
     */
    public static function createFromSessions(
        int $organizationId,
        \Illuminate\Support\Collection $sessions,
        string $triggerType = 'manual'
    ): self {
        $surchargeRate = config('constants.payment.surcharge_rate');
        $gatewayRate   = config('constants.payment.gateway_fee_rate');

        $totals = [
            'gross_paid'         => 0,
            'gateway_fees'       => 0,
            'amount_received'    => 0,
            'amount_owed_to_org' => 0,
        ];

        $settlement = self::create([
            'organization_id'    => $organizationId,
            'trigger_type'       => $triggerType,
            'gross_paid'         => 0,
            'gateway_fees'       => 0,
            'amount_received'    => 0,
            'amount_owed_to_org' => 0,
            'ventiq_revenue'     => 0,
            'status'             => 'pending',
        ]);

        foreach ($sessions as $session) {
            $ticket        = Ticket::find($session->payable_id);
            $ticketAmount  = $ticket->amount;
            $grossPaid     = round($ticketAmount * (1 + $surchargeRate), 2);
            $gatewayFee    = round($grossPaid * $gatewayRate, 2);
            $amtReceived   = round($grossPaid - $gatewayFee, 2);

            SettlementItem::create([
                'settlement_id'      => $settlement->id,
                'payment_session_id' => $session->id,
                'ticket_id'          => $ticket->id,
                'organization_id'    => $organizationId,
                'ticket_amount'      => $ticketAmount,
                'gross_paid'         => $grossPaid,
                'gateway_fee'        => $gatewayFee,
                'amount_received'    => $amtReceived,
                'amount_owed_to_org' => $ticketAmount,
            ]);

            $totals['gross_paid']         += $grossPaid;
            $totals['gateway_fees']       += $gatewayFee;
            $totals['amount_received']    += $amtReceived;
            $totals['amount_owed_to_org'] += $ticketAmount;
        }

        $settlement->update([
            'gross_paid'         => round($totals['gross_paid'], 2),
            'gateway_fees'       => round($totals['gateway_fees'], 2),
            'amount_received'    => round($totals['amount_received'], 2),
            'amount_owed_to_org' => round($totals['amount_owed_to_org'], 2),
            'ventiq_revenue'     => round($totals['amount_received'] - $totals['amount_owed_to_org'], 2),
        ]);

        return $settlement->fresh();
    }
}