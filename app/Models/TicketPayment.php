<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketPayment extends Model
{
    protected $fillable = [
        'ticket_id',
        'amount',
        'payment_method',
        'payment_reference',
        'status',
        'payment_date',
        'approved_by',
        'approved_at',
        'notes',
        'payment_type',
        'organization_payment_method_id',
        'source',
        'proof_path',
        'submitted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'approved_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** The organizer account the attendee paid into (organizer-direct only). */
    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationPaymentMethod::class, 'organization_payment_method_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scopes
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Get payment method label
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        $config = config('constants.payment_methods.' . $this->payment_method);
        return is_array($config) ? $config['label'] : ($config ?? ucfirst($this->payment_method));
    }
}