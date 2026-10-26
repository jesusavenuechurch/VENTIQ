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
        'paid_until',
        'due_notice_for',
        'grace_notice_for',
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
        'paid_until'        => 'datetime',
        'due_notice_for'    => 'date',
        'grace_notice_for'  => 'date',
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

    /** Plans that are paid for month by month. */
    public const RENEWING = ['team', 'business'];

    /** Days a plan runs into an unpaid month before it goes back to Free. */
    public const GRACE_DAYS = 3;

    /** Paid month by month (a plan VENTIQ granted at no charge just carries on). */
    public function renews(): bool
    {
        return $this->paid_until !== null && in_array($this->tier, self::RENEWING, true);
    }

    /** This month is paid for. */
    public function isPaid(): bool
    {
        return !$this->renews() || ($this->period_end && $this->paid_until->toDateString() >= $this->period_end->toDateString());
    }

    /** Paid for this month but not the next. */
    public function nextUnpaid(): bool
    {
        return $this->renews() && $this->isPaid() && $this->paid_until->toDateString() <= $this->period_end->toDateString();
    }

    /** Running on credit into an unpaid month, until this. */
    public function graceEndsAt(): ?\Illuminate\Support\Carbon
    {
        return $this->isPaid() ? null : $this->period_start->copy()->addDays(self::GRACE_DAYS);
    }

    public function getRemainingSessionsAttribute(): int { return max(0, $this->sessions_included - $this->sessions_used); }
    public function getRemainingWhatsappAttribute(): int { return max(0, $this->whatsapp_included - $this->whatsapp_used); }
}
