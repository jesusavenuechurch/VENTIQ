<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistConversation extends Model
{
    protected $fillable = ['organization_id', 'user_id', 'title'];

    public function messages(): HasMany
    {
        return $this->hasMany(AssistMessage::class, 'conversation_id')->orderBy('created_at');
    }
}
