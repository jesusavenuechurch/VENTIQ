<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginCode extends Model
{
    public const MAX_ATTEMPTS = 5;

    protected $fillable = ['user_id', 'code_hash', 'link_hash', 'attempts', 'intent', 'ip', 'expires_at', 'used_at'];

    protected $hidden = ['code_hash', 'link_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return is_null($this->used_at) && $this->expires_at->isFuture() && $this->attempts < self::MAX_ATTEMPTS;
    }
}
