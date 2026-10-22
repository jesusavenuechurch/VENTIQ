<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory; 
use Illuminate\Database\Eloquent\Relations\HasOne;

class Agent extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'email',
        'phone',
        'referral_token',
        'is_active',
        'notes',
        'created_by',
        'total_paid_organizations',
        'last_milestone_tier',
        'total_commissions_earned',
        'total_bonuses_earned',
        'total_earnings',
        'city_district',
        'access_types',
        'motivation',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'access_types' => 'array', 
        'approved_at' => 'datetime',
        'total_paid_organizations' => 'integer',
        'last_milestone_tier' => 'integer',
        'total_commissions_earned' => 'decimal:2',
        'total_bonuses_earned' => 'decimal:2',
        'total_earnings' => 'decimal:2',
    ];

    // ===== RELATIONSHIPS =====

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function certification(): HasOne
    {
        return $this->hasOne(AgentCertification::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(AgentEarning::class);
    }

    public function commissions()
    {
        return $this->earnings()->where('type', 'commission');
    }

    public function bonuses()
    {
        return $this->earnings()->where('type', 'milestone_bonus');
    }

    // ===== HELPERS =====

    public function getRegistrationUrlAttribute(): string
    {
        return url("/org/register/{$this->referral_token}");
    }

    public function getActiveOrganizationsCountAttribute(): int
    {
        return $this->organizations()->where('is_active', true)->count();
    }

    public function getTotalReferralsAttribute(): int
    {
        return $this->organizations()->count();
    }

    public function getPaidOrganizationsCountAttribute(): int
    {
        return $this->organizations()
            ->whereNotNull('first_payment_at')
            ->count();
    }

    public function getMilestoneProgressAttribute(): array
    {
        $paidOrgs = $this->total_paid_organizations;
        $currentTier = (int) floor($paidOrgs / 5);
        $nextTier = $currentTier + 1;
        $nextMilestone = $nextTier * 5;
        $remainingOrgs = $nextMilestone - $paidOrgs;
        $progress = ($paidOrgs % 5) * 20;

        return [
            'current_count' => $paidOrgs,
            'next_milestone' => $nextMilestone,
            'remaining' => $remainingOrgs,
            'progress_percent' => $progress,
            'next_bonus_amount' => AgentEarning::calculateMilestoneBonus($nextTier),
        ];
    }

    public function getNextBonusAmountAttribute(): float
    {
        $nextTier = (int) floor($this->total_paid_organizations / 5) + 1;
        return AgentEarning::calculateMilestoneBonus($nextTier);
    }

    public function getPendingCommissionsAttribute(): float
    {
        return $this->commissions()
            ->where('status', 'pending')
            ->sum('amount');
    }

    public function getPendingBonusesAttribute(): float
    {
        return $this->bonuses()
            ->where('status', 'pending')
            ->sum('amount');
    }

    public function updateEarnings(): void
    {
        // Worked out first: adding the attributes inside update() would add the old totals.
        $commissions = (float) $this->commissions()->paid()->sum('amount');
        $bonuses = (float) $this->bonuses()->paid()->sum('amount');

        $this->update([
            'total_commissions_earned' => $commissions,
            'total_bonuses_earned' => $bonuses,
            'total_earnings' => $commissions + $bonuses,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'active']);
    }

    // ===== HOOKS =====

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($agent) {
            if (empty($agent->referral_token)) {
                $agent->referral_token = static::generateUniqueToken();
            }
        });
    }

    protected static function generateUniqueToken(): string
    {
        do {
            $token = 'AG-' . strtoupper(Str::random(6));
        } while (static::where('referral_token', $token)->exists());

        return $token;
    }
}