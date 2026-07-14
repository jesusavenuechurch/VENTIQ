<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Reconstructed from usage in OrganizationalRecordResource + the old
// getPendingCarryForwardAttribute() — confirm against your actual
// RecordOpenIssue model before running the migration.
class SegmentOpenIssue extends Model
{
    protected $fillable = [
        'segment_id',
        'description',
        'raised_by',
        'status', // open | resolved
    ];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(SessionSegment::class, 'segment_id');
    }
}