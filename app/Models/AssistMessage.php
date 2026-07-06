<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistMessage extends Model
{
    protected $fillable = ['conversation_id', 'role', 'content', 'status', 'job_id'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistConversation::class, 'conversation_id');
    }
}
