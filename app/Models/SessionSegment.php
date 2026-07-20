<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SessionSegment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'session_id',
        'presenter_name',
        'title',
        'order',
        'started_at',
        'ended_at',
        'status',
        'raw_log',
        'ai_summary',
        'extraction_job_id',
        'paused_at',
        'paused_seconds',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
        'raw_log'    => 'array',
        'ai_summary' => 'array',
        'paused_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(SegmentActionItem::class, 'segment_id');
    }

    public function openIssues(): HasMany
    {
        return $this->hasMany(SegmentOpenIssue::class, 'segment_id');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(SegmentInsight::class, 'segment_id')->orderBy('display_order');
    }

    // ── Live capture helpers ────────────────────────────────────────────

    public function appendLogLine(string $text): void
    {
        $log = $this->raw_log ?? [];
        $log[] = ['time' => now()->format('H:i:s'), 'text' => $text];
        $this->update(['raw_log' => $log]);
    }

    public function getDurationSecondsAttribute(): ?int
    {
        if (!$this->started_at) return null;

        $elapsed = $this->started_at->diffInSeconds($this->ended_at ?? now());
        $paused = $this->paused_seconds;

        // still mid-pause and not finished — subtract the open pause too
        if ($this->paused_at && !$this->ended_at) {
            $paused += $this->paused_at->diffInSeconds(now());
        }

        return max(0, $elapsed - $paused);
    }

    public function pause(): void
    {
        if ($this->paused_at) return; // already paused, ignore double-calls
        $this->update(['paused_at' => now()]);
    }

    public function resume(): void
    {
        if (!$this->paused_at) return;
        $this->update([
            'paused_seconds' => $this->paused_seconds + $this->paused_at->diffInSeconds(now()),
            'paused_at' => null,
        ]);
    }

    // ── Status helpers ───────────────────────────────────────────────────

    public function isUpcoming(): bool  { return $this->status === 'upcoming'; }
    public function isActive(): bool    { return $this->status === 'active'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }

    public function start(): void
    {
        $this->update(['status' => 'active', 'started_at' => now()]);
    }

    public function finish(): void
    {
        $this->update(['status' => 'completed', 'ended_at' => now()]);
    }

    public function participant(): HasOne
    {
        return $this->hasOne(Participant::class, 'session_segment_id');
    }
}