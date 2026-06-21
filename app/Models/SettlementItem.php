<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementItem extends Model
{
    protected $fillable = [
        'settlement_id',
        'payment_session_id',
        'ticket_id',
        'organization_id',
        'ticket_amount',
        'gross_paid',
        'gateway_fee',
        'amount_received',
        'amount_owed_to_org',
    ];

    protected $casts = [
        'ticket_amount'      => 'decimal:2',
        'gross_paid'         => 'decimal:2',
        'gateway_fee'        => 'decimal:2',
        'amount_received'    => 'decimal:2',
        'amount_owed_to_org' => 'decimal:2',
    ];

    // ===== RELATIONSHIPS =====

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function paymentSession(): BelongsTo
    {
        return $this->belongsTo(PaymentSession::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    // ===== COMPUTED =====

    public function getVentiqRevenueAttribute(): float
    {
        return round($this->amount_received - $this->amount_owed_to_org, 2);
    }
}