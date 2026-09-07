<?php

namespace App\Models;

use App\Models\Client;
use App\Models\Participant;
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

    // ── Attendee check-in ────────────────────────────────────────────────
    // Canonical "find-or-create Client, find-or-create Participant scoped
    // to this session, mark attended" logic. Shared by the public
    // self-service form (PublicSessionCheckinController) and the
    // authenticated scanner API (ProgrammeScannerController) so the two
    // never drift into different rules for what a "check-in" means.
    //
    // Accepts either an existing `client_id` (picked from a search result)
    // or raw `full_name`/`email` for a brand-new walk-in — `email` is
    // required either way since it's how Client is deduped org-wide.
    //
    // Idempotent: re-checking in someone already marked present for this
    // session does not overwrite their original attended_at — the caller
    // can tell this happened via $participant->wasRecentlyCheckedIn.
    public function checkInAttendee(array $data): Participant
    {
        if (!empty($data['client_id'])) {
            $client = Client::where('organization_id', $this->organization_id)
                ->findOrFail($data['client_id']);

            $client->update([
                'full_name' => $data['full_name'] ?? $client->full_name,
                'phone'     => $data['phone'] ?? $client->phone,
            ]);
        } else {
            $client = Client::firstOrCreate(
                ['email' => $data['email'], 'organization_id' => $this->organization_id],
                ['full_name' => $data['full_name'], 'phone' => $data['phone'] ?? null, 'status' => 'active']
            );

            if (!$client->wasRecentlyCreated) {
                $client->update([
                    'full_name' => $data['full_name'],
                    'phone'     => $data['phone'] ?? $client->phone,
                ]);
            }
        }

        // Keyed on session_id — see the fix note on the participants
        // migration. A returning attendee on Day 2 gets their own row here
        // instead of colliding with (or silently reusing) their Day 1 one.
        $participant = Participant::firstOrNew([
            'session_id' => $this->id,
            'client_id'  => $client->id,
        ]);

        $wasAlreadyCheckedIn = $participant->exists && $participant->attended_at !== null;
        $isNew = !$participant->exists;

        $participant->organization_id = $this->organization_id;
        $participant->event_id        = $this->event_id;
        $participant->role            = $participant->role ?? 'attendee';
        $participant->source          = $isNew ? ($data['source'] ?? 'walk_in') : $participant->source;
        $participant->institution     = $data['institution'] ?? $participant->institution;
        $participant->position        = $data['position']    ?? $participant->position;
        if ($isNew) {
            // Set explicitly rather than relying on the DB column default —
            // the in-memory model wouldn't reflect that default until a
            // fresh fetch, and callers read $participant straight off this
            // method's return value.
            $participant->signature_status = 'pending';
        }
        if (!$wasAlreadyCheckedIn) {
            $participant->attended_at = now();
        }
        $participant->save();

        $participant->wasAlreadyCheckedIn = $wasAlreadyCheckedIn;

        return $participant;
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