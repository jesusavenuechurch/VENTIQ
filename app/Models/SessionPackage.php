<?php

namespace App\Models;

use App\Support\SessionPackageDefinition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'tier',
        'status',
        'sessions_included',
        'sessions_used',
        'whatsapp_included',
        'whatsapp_used',
        'sms_included',
        'sms_used',
        'price_paid',
        'period_start',
        'period_end',
        'notes',
    ];

    protected $casts = [
        'sessions_included' => 'integer',
        'sessions_used'     => 'integer',
        'whatsapp_included' => 'integer',
        'whatsapp_used'     => 'integer',
        'sms_included'      => 'integer',
        'sms_used'          => 'integer',
        'price_paid'        => 'decimal:2',
        'period_start'      => 'datetime',
        'period_end'        => 'datetime',
    ];

    public static function createFreePackage(int $organizationId): self
    {
        $def = SessionPackageDefinition::get('free');

        return self::create([
            'organization_id'   => $organizationId,
            'tier'              => 'free',
            'status'            => 'active',
            'sessions_included' => $def['sessions_included'],
            'whatsapp_included' => $def['whatsapp_included'],
            'sms_included'      => $def['sms_included'],
            'price_paid'        => 0,
            'period_start'      => now(),
            'period_end'        => now()->addMonth(),
        ]);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isActive(): bool     { return $this->status === 'active'; }
    public function isExhausted(): bool  { return $this->status === 'exhausted'; }
    public function isSubscription(): bool { return $this->tier !== 'payg'; }

    public function hasSessionsRemaining(): bool { return $this->sessions_used < $this->sessions_included; }
    public function hasWhatsappRemaining(): bool { return $this->whatsapp_used < $this->whatsapp_included; }

    public function getRemainingSessionsAttribute(): int { return max(0, $this->sessions_included - $this->sessions_used); }
    public function getRemainingWhatsappAttribute(): int { return max(0, $this->whatsapp_included - $this->whatsapp_used); }
}
