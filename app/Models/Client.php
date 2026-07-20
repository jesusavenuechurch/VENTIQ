<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Client extends Model
{
    use Notifiable, HasFactory;
    
    protected $fillable = ['organization_id', 'full_name', 'email', 'phone', 'created_by', 'notes', 'status'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function checkins()
    {
        return $this->hasManyThrough(
            Ticket::class,
            Client::class,
            'id',
            'client_id'
        )->whereNotNull('checked_in_at');
    }

    public function isRegistered()
    {
        return $this->created_by !== null;
    }

    public function participations(): HasMany
    {
        return $this->hasMany(Participant::class);
    }
}