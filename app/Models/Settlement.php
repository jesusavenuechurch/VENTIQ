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

    // Batches are made by App\Services\Payments\SettlementService.
}
