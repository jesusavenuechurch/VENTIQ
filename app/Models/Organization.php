<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\HasPackageEntitlements;

class Organization extends Model
{
    use HasFactory, HasPackageEntitlements;
    protected $fillable = [
        'name',
        'email',
        'phone',
        'description',
        'website',
        'logo_path',
        'is_active',
        'slug',
        'tagline',
        'contact_email',
        'agent_id',
        'registered_via_agent_at',
        'registration_source',
        'agent_commission_packages_count',
        'agent_commission_packages_limit',
        'total_agent_commission_paid',
        'first_payment_at',
        'otp_code',
        'otp_expires_at',
        'phone_verified_at',
        'workshop_enabled',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'registered_via_agent_at' => 'datetime',
        'agent_commission_events_count' => 'integer',
        'agent_commission_events_limit' => 'integer',
        'total_agent_commission_paid' => 'decimal:2',
        'first_payment_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'phone_verified_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($organization) {
            if (empty($organization->slug)) {
                $organization->slug = static::generateUniqueSlug($organization->name);
            }
        });

        static::updating(function ($organization) {
            // Only update slug if name changed and we haven't manually set a slug
            if ($organization->isDirty('name') && !$organization->isDirty('slug')) {
                $organization->slug = static::generateUniqueSlug($organization->name);
            }
        });
    }

    protected static function generateUniqueSlug($name)
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    // ===== RELATIONSHIPS =====

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(OrganizationPaymentMethod::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function agentCommissions(): HasMany
    {
        return $this->hasMany(AgentCommission::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(OrganizationPackage::class);
    }

    public function activePackages(): HasMany
    {
        return $this->packages()->where('status', 'active');
    }

    // ===== PACKAGE & QUOTA HELPERS =====

    /**
     * Get total events remaining across all active packages
     */
    public function getTotalEventsRemainingAttribute(): int
    {
        return (int) $this->activePackages->sum(fn($p) => $p->events_included - $p->events_used);
    }

    /**
     * Get total tickets remaining across all active packages
     */
    public function getTotalTicketsRemainingAttribute(): int
    {
        return (int) $this->activePackages->sum(fn($p) => $p->tickets_included - $p->tickets_used);
    }

    /**
     * Check if organization can create a new event
     */
    public function canCreateEvent(): bool
    {
        return $this->activePackages()
            ->whereRaw('events_used < events_included')
            ->exists();
    }

    /**
     * Check if organization can create tickets
     */
    public function canCreateTickets(int $quantity = 1, bool $isComp = false): bool
    {
        foreach ($this->activePackages as $package) {
            if ($isComp && $package->hasCompTicketsRemaining($quantity)) {
                return true;
            }
            if (!$isComp && $package->hasTicketsRemaining($quantity)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Automatically picks the best package to "bill" for a new ticket
     */
    public function getBestPackageForTickets(int $quantity = 1, bool $isComp = false): ?OrganizationPackage
    {
        $packages = $this->activePackages;

        if ($isComp) {
            return $packages
                ->filter(fn($p) => $p->hasCompTicketsRemaining($quantity))
                ->sortBy('expires_at') // Use the one expiring soonest first
                ->first();
        }

        return $packages
            ->filter(fn($p) => $p->hasTicketsRemaining($quantity))
            ->sortBy('expires_at')
            ->first();
    }

    /**
     * Check if the organization is completely out of resources
     */
    public function isQuotaExhausted(): bool
    {
        return !$this->canCreateEvent() && !$this->canCreateTickets(1);
    }

    // ===== AGENT & COMMISSION HELPERS =====

    public function wasReferredByAgent(): bool
    {
        return !is_null($this->agent_id);
    }

    public function canEarnAgentCommission(): bool
    {
        return $this->wasReferredByAgent() 
            && $this->agent_commission_events_count < $this->agent_commission_events_limit;
    }

    public function markFirstPayment(): void
    {
        if (is_null($this->first_payment_at)) {
            $this->update(['first_payment_at' => now()]);
            
            if ($this->agent_id && $this->agent) {
                $this->agent->increment('total_paid_organizations');
            }
        }
    }

    // ===== IDENTITY & SECURITY =====

    public function generateOTP(): string
    {
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        $this->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);
        
        Log::info("OTP generated for {$this->name}: {$otp}");
        return $otp;
    }

    public function verifyOTP(string $code): bool
    {
        if ($this->otp_code === $code && 
            $this->otp_expires_at && 
            $this->otp_expires_at->isFuture()) {
            
            $this->update([
                'phone_verified_at' => now(),
                'otp_code' => null,
                'otp_expires_at' => null,
            ]);
            
            return true;
        }
        return false;
    }

    public function isPhoneVerified(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    public function hasWorkshopAccess(): bool
    {
        return $this->workshop_enabled === true;
    }
    
    public function settlementItems(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(OrganizationInvite::class);
    }
}