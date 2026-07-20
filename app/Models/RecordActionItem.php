<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordActionItem extends Model
{
      protected $fillable = [
        'record_id',
        'extraction_id',
        'description',
        'assigned_to_name',
        'assigned_to_user_id',
        'due_date',
        'status',
        'carried_from_id',
    ];
 
    protected $casts = [
        'due_date' => 'date',
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
 
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
 
    // Layer 2: the action item this was carried forward from
    public function carriedFrom(): BelongsTo
    {
        return $this->belongsTo(RecordActionItem::class, 'carried_from_id');
    }
 
    // ── Helpers ───────────────────────────────────────────────────────────────
 
    public function isPending(): bool        { return $this->status === 'pending'; }
    public function isCompleted(): bool      { return $this->status === 'completed'; }
    public function isCarriedForward(): bool { return $this->status === 'carried_forward'; }
 
    public function getAssigneeDisplayAttribute(): string
    {
        if ($this->assignedUser) return $this->assignedUser->name;
        return $this->assigned_to_name ?? 'Unassigned';
    }
 
    // ── Scopes ────────────────────────────────────────────────────────────────
 
    public function scopePending($query)        { return $query->where('status', 'pending'); }
    public function scopeCompleted($query)      { return $query->where('status', 'completed'); }
    public function scopeCarriedForward($query) { return $query->where('status', 'carried_forward'); }
}
