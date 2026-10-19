<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** VENTIQ's fee on one ticket. See FeeService. */
class TicketFee extends Model
{
    public const COLLECT_FROM_PAYOUT = 'deduct_from_payout';
    public const COLLECT_BY_INVOICE  = 'invoice';

    protected $fillable = [
        'ticket_id', 'event_id', 'organization_id', 'source', 'ticket_amount', 'people',
        'service_fee', 'operational_fee', 'total_fee', 'sponsored', 'collection', 'invoiced_at',
        'invoice_reference', 'invoice_paid_at',
    ];

    protected $casts = [
        'invoice_paid_at' => 'datetime',
        'ticket_amount'   => 'decimal:2',
        'service_fee'     => 'decimal:2',
        'operational_fee' => 'decimal:2',
        'total_fee'       => 'decimal:2',
        'people'          => 'integer',
        'sponsored'       => 'boolean',
        'invoiced_at'     => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** What VENTIQ actually charges: nothing when sponsored. */
    public function chargeable(): float
    {
        return $this->sponsored ? 0.0 : (float) $this->total_fee;
    }
}
