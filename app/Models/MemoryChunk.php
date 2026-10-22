<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A passage of an organization's records, for Ask Ventiq (see App\Services\Memory). */
class MemoryChunk extends Model
{
    protected $fillable = ['organization_id', 'session_id', 'source_type', 'source_id', 'title', 'body', 'date', 'content_hash', 'embedding', 'embedding_model'];

    protected $casts = ['date' => 'date'];

    protected $hidden = ['embedding'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    /** The embedding as floats, or null. */
    public function vector(): ?array
    {
        return $this->embedding ? array_values(unpack('g*', $this->embedding)) : null;
    }

    /** Floats to what's stored: float32, normalised so a dot product is the cosine. */
    public static function pack(array $vector): string
    {
        $norm = sqrt(array_sum(array_map(fn ($v) => $v * $v, $vector))) ?: 1.0;

        return pack('g*', ...array_map(fn ($v) => $v / $norm, $vector));
    }
}
