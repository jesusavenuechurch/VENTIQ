<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Participant extends Model
{
    protected $fillable = [
        'organization_id',
        'event_id',
        'session_id',
        'client_id',
        'session_segment_id',
        'ticket_id',
        'role',
        'source',
        'attended_at',
        'institution',
        'position',
        'notified_at',
        'report_notified_at',
    ];

    protected $casts = [
        'attended_at' => 'datetime',
        'notified_at' => 'datetime',
        'report_notified_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    // The actual check-in boundary — this is what every attendance
    // count/query should scope through now, not event_id. event_id
    // stays on the record too (denormalized, handy for "everyone who
    // ever attended anything in this Programme" queries later) but
    // it's no longer the uniqueness key.
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
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

    public function checkIn(): void
    {
        $this->update(['attended_at' => now()]);
    }
}