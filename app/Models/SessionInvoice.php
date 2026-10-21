<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A month's corporate Sessions invoice: the sessions held, at the agreed rate. */
class SessionInvoice extends Model
{
    protected $fillable = [
        'organization_id', 'number', 'period_start', 'period_end', 'rate', 'sessions_count', 'free_count', 'total', 'due_date', 'status',
        'khoebo_invoice_id', 'khoebo_reference', 'khoebo_error', 'emailed_at', 'paid_amount', 'paid_at', 'payment_method', 'payment_reference', 'paid_by',
    ];

    protected $casts = [
        'period_start' => 'date', 'period_end' => 'date', 'due_date' => 'date', 'emailed_at' => 'datetime', 'paid_at' => 'datetime',
        'rate' => 'decimal:2', 'total' => 'decimal:2', 'paid_amount' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'session_invoice_id')->orderBy('counted_at');
    }

    public function outstanding(): float
    {
        return max(0, round((float) $this->total - (float) $this->paid_amount, 2));
    }

    public function isOverdue(): bool
    {
        return $this->status === 'unpaid' && $this->due_date->lt(today());
    }

    /** "October 2026". */
    public function periodLabel(): string
    {
        return $this->period_start->format('F Y');
    }
}
