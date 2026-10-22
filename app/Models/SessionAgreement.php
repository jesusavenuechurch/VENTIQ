<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An organization's corporate rate for Sessions (see App\Services\Sessions\CorporateBilling). */
class SessionAgreement extends Model
{
    protected $fillable = ['organization_id', 'rate', 'free_per_month', 'billing_email', 'notes', 'starts_on', 'ended_at', 'created_by'];

    protected $casts = ['rate' => 'decimal:2', 'starts_on' => 'date', 'ended_at' => 'datetime', 'free_per_month' => 'integer'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isActive(): bool
    {
        return !$this->ended_at && $this->starts_on->lte(today());
    }
}
