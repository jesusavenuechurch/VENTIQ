<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordOpenIssue extends Model
{
     protected $fillable = [
        'record_id',
        'extraction_id',
        'description',
        'raised_by',
        'status',
        'carried_from_id',
        'resolved_in_record_id',
    ];
 
    // ── Relationships ─────────────────────────────────────────────────────────
 
    public function record(): BelongsTo
    {
        return $this->belongsTo(OrganizationalRecord::class, 'record_id');
    }
 
    public function extraction(): BelongsTo
    {
        return $this->belongsTo(RecordExtraction::class, 'extraction_id');
    }
 
    // Layer 2: where this issue first appeared
    public function carriedFrom(): BelongsTo
    {
        return $this->belongsTo(RecordOpenIssue::class, 'carried_from_id');
    }
 
    // Layer 2: which record finally closed this issue
    public function resolvedIn(): BelongsTo
    {
        return $this->belongsTo(OrganizationalRecord::class, 'resolved_in_record_id');
    }
 
    // ── Helpers ───────────────────────────────────────────────────────────────
 
    public function isOpen(): bool           { return $this->status === 'open'; }
    public function isResolved(): bool       { return $this->status === 'resolved'; }
    public function isCarriedForward(): bool { return $this->status === 'carried_forward'; }
 
    // ── Scopes ────────────────────────────────────────────────────────────────
 
    public function scopeOpen($query)           { return $query->where('status', 'open'); }
    public function scopeResolved($query)       { return $query->where('status', 'resolved'); }
    public function scopeCarriedForward($query) { return $query->where('status', 'carried_forward'); }
}
