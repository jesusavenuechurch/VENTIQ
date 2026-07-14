<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Participant extends Model
{
    protected $fillable = [
        'organization_id',
        'event_id',
        'client_id',
        'session_segment_id',
        'ticket_id',
        'role',
        'source',
        'attended_at',
    ];

    protected $casts = [
        'attended_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(SessionSegment::class, 'session_segment_id');
    }

    public function isCheckedIn(): bool
    {
        return $this->attended_at !== null;
    }

    // The walk-in action — no QR, no ticket, just "they're here."
    public function checkIn(): void
    {
        $this->update(['attended_at' => now()]);
    }
}