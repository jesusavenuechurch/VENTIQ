<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistConversation extends Model
{
    protected $fillable = ['organization_id', 'user_id', 'title'];

    public function messages(): HasMany
    {
        return $this->hasMany(AssistMessage::class, 'conversation_id')->orderBy('created_at');
    }
}
