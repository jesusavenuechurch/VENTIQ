<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrganizationalRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'created_by',
        'event_id',
        'parent_record_id',
        'record_type',
        'title',
        'meeting_date',
        'location',
        'raw_input',
        'ai_extracted',
        'final_output',
        'status',
        'extraction_job_id',
    ];

    protected $casts = [
        'meeting_date' => 'date',
        'ai_extracted' => 'array',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

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

    // Layer 2: the record this one follows on from
    public function parentRecord(): BelongsTo
    {
        return $this->belongsTo(OrganizationalRecord::class, 'parent_record_id');
    }

    // Layer 2: records that follow on from this one
    public function childRecords(): HasMany
    {
        return $this->hasMany(OrganizationalRecord::class, 'parent_record_id');
    }

    public function extractions(): HasMany
    {
        return $this->hasMany(RecordExtraction::class, 'record_id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(RecordActionItem::class, 'record_id');
    }

    public function openIssues(): HasMany
    {
        return $this->hasMany(RecordOpenIssue::class, 'record_id');
    }

    // ── Extraction category helpers ───────────────────────────────────────────

    public function agendaItems(): HasMany
    {
        return $this->hasMany(RecordExtraction::class, 'record_id')
                    ->where('category', 'agenda')
                    ->orderBy('display_order');
    }

    public function discussionPoints(): HasMany
    {
        return $this->hasMany(RecordExtraction::class, 'record_id')
                    ->where('category', 'discussion_point')
                    ->orderBy('display_order');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(RecordExtraction::class, 'record_id')
                    ->where('category', 'decision')
                    ->orderBy('display_order');
    }

    // ── Attendance — pulled from event, never stored separately ──────────────

    public function getAttendanceAttribute(): ?object
    {
        if (!$this->event_id || !$this->event) return null;

        $checkedIn = $this->event->tickets()
            ->whereNotNull('checked_in_at')
            ->with('client')
            ->get();

        $total = $this->event->tickets()->count();

        return (object) [
            'checked_in'  => $checkedIn,
            'total'       => $total,
            'count'       => $checkedIn->count(),
            'absent'      => $total - $checkedIn->count(),
        ];
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isDraft(): bool      { return $this->status === 'draft'; }
    public function isExtracted(): bool  { return $this->status === 'extracted'; }
    public function isFinalized(): bool  { return $this->status === 'finalized'; }

    // ── Title resolution ──────────────────────────────────────────────────────
    // Priority: explicit title → event name → AI suggested → type + date

    public function getResolvedTitleAttribute(): string
    {
        if ($this->title) return $this->title;
        if ($this->event) return $this->event->name;

        $suggested = $this->ai_extracted['suggested_title'] ?? null;
        if ($suggested) return $suggested;

        $type = ucfirst($this->record_type);
        $date = $this->meeting_date?->format('d M Y') ?? $this->created_at->format('d M Y');
        return "{$type} — {$date}";
    }

    // ── Layer 2: unresolved items from previous records in this org ───────────

    public function getPendingCarryForwardAttribute(): array
    {
        if (!$this->parent_record_id) return [];

        $actionItems = RecordActionItem::where('record_id', $this->parent_record_id)
            ->where('status', 'pending')
            ->get();

        $openIssues = RecordOpenIssue::where('record_id', $this->parent_record_id)
            ->where('status', 'open')
            ->get();

        return [
            'action_items' => $actionItems,
            'open_issues'  => $openIssues,
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeFinalized($query)
    {
        return $query->where('status', 'finalized');
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }
}