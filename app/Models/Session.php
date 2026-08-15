<?php

namespace App\Models;

use App\Support\SessionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Session extends Model
{
    use HasFactory, SoftDeletes;

    // Deliberately not `sessions` — see migration notes.
    protected $table = 'capture_sessions';

    protected $fillable = [
        'organization_id',
        'created_by',
        'event_id',
        'public_token',
        'session_code',
        'parent_session_id',
        'type',
        'title',
        'date',
        'location',
        'meta',
        'session_report',
        'report_last_opened_at',
        'status',
        'report_job_id',
        'start_time',
        'reviewed_at',
    ];

    protected $casts = [
        'date'                   => 'date',
        'meta'                   => 'array',
        'report_last_opened_at'  => 'datetime',
        'reviewed_at'            => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function parentSession(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'parent_session_id');
    }

    public function childSessions(): HasMany
    {
        return $this->hasMany(Session::class, 'parent_session_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(SessionSegment::class)->orderBy('order');
    }

    // ── Attendance — unchanged, still pulled live from the linked event ───

    public function getAttendanceAttribute(): ?object
    {
        if (!$this->event_id || !$this->event) return null;

        $checkedIn = $this->event->tickets()
            ->whereNotNull('checked_in_at')
            ->with('client')
            ->get();

        $total = $this->event->tickets()->count();

        return (object) [
            'checked_in' => $checkedIn,
            'total'      => $total,
            'count'      => $checkedIn->count(),
            'absent'     => $total - $checkedIn->count(),
        ];
    }

    // ── Status helpers — capture lifecycle, not AI-pipeline stage ─────────

    public function isDraft(): bool     { return $this->status === 'draft'; }
    public function isActive(): bool    { return $this->status === 'active'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isReported(): bool  { return $this->status === 'reported'; }

    // Created-for-later is the default — this is the explicit, separate
    // action that actually starts the clock, whether that happens
    // immediately after creation or days later.
    public function start(): void
    {
        $this->update(['status' => 'active']);
    }

    // Shared by the automatic trigger (last segment finishes) and the
    // manual "regenerate" action — one place, not duplicated logic.
    public function queueReportGeneration(): void
    {
        $jobId = (string) \Illuminate\Support\Str::uuid();

        \App\Models\AiGenerationResult::create([
            'job_id'  => $jobId,
            'user_id' => $this->created_by,
            'type'    => 'session_report',
            'status'  => 'pending',
            'payload' => json_encode(['session_id' => $this->id]),
        ]);

        $this->update(['report_job_id' => $jobId]);

        \App\Jobs\GenerateSessionReport::dispatch($jobId, $this->created_by, $this->id);
    }

    // ── Public check-in code ────────────────────────────────────────────
    // A second, human-typeable door into the same check-in form the QR
    // encodes — for anyone who can't scan. Short and ambiguity-free
    // (no 0/O/1/I/L) so it's easy to read off a printed pass and type in.
    public static function generateSessionCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $length = strlen($alphabet);

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, $length - 1)];
            }
        } while (static::where('session_code', $code)->exists());

        return $code;
    }

    // ── Title resolution ────────────────────────────────────────────────

    public function getResolvedTitleAttribute(): string
    {
        if ($this->title) return $this->title;
        if ($this->event) return $this->event->name;

        $type = SessionType::label($this->type);
        $date = $this->date?->format('d M Y') ?? $this->created_at->format('d M Y');
        return "{$type} — {$date}";
    }

    // ── Carry-forward — now sourced across the previous session's segments ─

    public function getPendingCarryForwardAttribute(): array
    {
        if (!$this->parent_session_id) return [];

        $segmentIds = $this->parentSession?->segments()->pluck('id') ?? collect();

        return [
            'action_items' => SegmentActionItem::whereIn('segment_id', $segmentIds)
                ->where('status', 'pending')->get(),
            'open_issues' => SegmentOpenIssue::whereIn('segment_id', $segmentIds)
                ->where('status', 'open')->get(),
        ];
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeReported($query)
    {
        return $query->where('status', 'reported');
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }
}