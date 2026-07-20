<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Reconstructed from usage in OrganizationalRecordResource + the old
// getPendingCarryForwardAttribute() — confirm against your actual
// RecordActionItem model before running the migration.
class SegmentActionItem extends Model
{
    protected $fillable = [
        'segment_id',
        'description',
        'assigned_to_name',
        'due_date',
        'status', // pending | done
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(SessionSegment::class, 'segment_id');
    }
}