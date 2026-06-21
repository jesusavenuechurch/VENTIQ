<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordExtraction extends Model
{
        protected $fillable = [
        'record_id',
        'category',
        'content',
        'is_ai_generated',
        'display_order',
    ];
 
    protected $casts = [
        'is_ai_generated' => 'boolean',
        'display_order'   => 'integer',
    ];
 
    // ── Relationships ─────────────────────────────────────────────────────────
 
    public function record(): BelongsTo
    {
        return $this->belongsTo(OrganizationalRecord::class, 'record_id');
    }
 
    // ── Scopes ────────────────────────────────────────────────────────────────
 
    public function scopeAgenda($query)           { return $query->where('category', 'agenda'); }
    public function scopeDiscussionPoints($query) { return $query->where('category', 'discussion_point'); }
    public function scopeDecisions($query)        { return $query->where('category', 'decision'); }
    public function scopeActionItems($query)      { return $query->where('category', 'action_item'); }
    public function scopeOpenIssues($query)       { return $query->where('category', 'open_issue'); }
}
