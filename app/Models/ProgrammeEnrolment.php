<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Someone on a programme (see App\Services\Sessions\ProgrammeProgress). */
class ProgrammeEnrolment extends Model
{
    protected $fillable = ['organization_id', 'event_id', 'client_id', 'institution', 'position', 'source', 'withdrawn_at'];

    protected $casts = ['withdrawn_at' => 'datetime'];

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }
}
