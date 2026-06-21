<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSession extends Model
{
    protected $fillable = [
        'payable_type',
        'payable_id',
        'mopay_reference',
        'mopay_session_id',
        'mopay_payment_url',
        'amount',
        'status',
        'payment_method',
        'transaction_id',
        'organization_id',
        'initiated_by',
        'callback_payload',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'callback_payload' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isPending(): bool   { return $this->status === 'pending'; }
}