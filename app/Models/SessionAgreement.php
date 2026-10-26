<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An organization's corporate rate for Sessions (see App\Services\Sessions\CorporateBilling). */
class SessionAgreement extends Model
{
    protected $fillable = ['organization_id', 'rate', 'free_per_month', 'billing_email', 'notes', 'starts_on', 'ends_on', 'end_notice_at', 'ended_at', 'created_by'];

    /** How long a pilot runs, and is renewed for. */
    public const PILOT_MONTHS = 3;

    /** Days before a pilot ends that VENTIQ is told. */
    public const PILOT_NOTICE_DAYS = 7;

    protected $casts = ['rate' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date', 'end_notice_at' => 'datetime', 'ended_at' => 'datetime', 'free_per_month' => 'integer'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isActive(): bool
    {
        return !$this->ended_at && $this->starts_on->lte(today()) && (!$this->ends_on || $this->ends_on->gte(today()));
    }

    /** A pilot: sessions at no charge, for a limited time. */
    public function isPilot(): bool
    {
        return (float) $this->rate === 0.0;
    }
}
