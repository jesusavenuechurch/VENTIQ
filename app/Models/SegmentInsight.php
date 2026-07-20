<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Reconstructed from the Repeater bindings in OrganizationalRecordResource
// (agendaItems / discussionItems / decisionItems all pointed at this
// table via `category`). Confirm against your actual RecordExtraction
// model before running the migration. Category values widened to also
// cover the live sticky-note tags (theme, question) alongside the
// original meeting-note categories.
class SegmentInsight extends Model
{
    protected $fillable = [
        'segment_id',
        'category', // theme | decision | discussion_point | question | agenda
        'content',
        'is_ai_generated',
        'display_order',
    ];

    protected $casts = [
        'is_ai_generated' => 'boolean',
    ];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(SessionSegment::class, 'segment_id');
    }
}