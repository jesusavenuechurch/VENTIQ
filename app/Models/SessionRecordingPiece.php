<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 30 seconds of a recorded session, and what Whisper heard in it. */
class SessionRecordingPiece extends Model
{
    protected $fillable = [
        'session_id', 'segment_id', 'seq', 'recorder', 'recorded_at', 'duration_ms',
        'path', 'mime', 'status', 'text', 'attempts', 'error',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'duration_ms' => 'integer',
        'seq'         => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(SessionSegment::class);
    }
}
