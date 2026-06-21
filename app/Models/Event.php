<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Event extends Model
{
    use HasFactory;
    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'event_date',
        'duration_days',
        'location',
        'capacity',
        'status',
        'slug',
        'is_public',
        'tagline',
        'venue',
        'registration_deadline',
        'allow_installments',
        'minimum_deposit_percentage',
        'installment_instructions',
        'banner_image',
        'organization_package_id',
        'event_type',
        'payment_mode',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'registration_deadline' => 'datetime',

        // ✅ NEW
        'allow_installments' => 'boolean',
        'minimum_deposit_percentage' => 'decimal:2',
    ];

    /* ------------------------------------------------------------
     | Boot
     ------------------------------------------------------------ */

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            // Auto-generate slug if not provided
            if (empty($event->slug)) {
                $event->slug = static::generateUniqueSlug(
                    $event->name,
                    $event->organization_id
                );
            }

            // Auto-populate tagline from name if left blank
            if (empty($event->tagline)) {
                $event->tagline = $event->name;
            }
        });

        static::updating(function ($event) {
            if ($event->isDirty('name') && ! $event->isDirty('slug')) {
                $event->slug = static::generateUniqueSlug(
                    $event->name,
                    $event->organization_id
                );
            }
        });
    }

    protected static function generateUniqueSlug($name, $organizationId)
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (
            static::where('slug', $slug)
                ->where('organization_id', $organizationId)
                ->exists()
        ) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    /* ------------------------------------------------------------
     | Accessors
     ------------------------------------------------------------ */

    public function getPublicUrlAttribute(): ?string
    {
        if ($this->organization && $this->slug && $this->organization->slug) {
            return route('public.event', [
                'orgSlug' => $this->organization->slug,
                'eventSlug' => $this->slug,
            ]);
        }

        return null;
    }

    /**
     * Helper for deposits (used later in controllers / views)
     */
    public function requiresDeposit(): bool
    {
        return $this->allow_installments === true
            && ! is_null($this->minimum_deposit_percentage);
    }

    /* ------------------------------------------------------------
     | Relationships
     ------------------------------------------------------------ */

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(EventTier::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function checkins()
    {
        return $this->tickets()
            ->whereNotNull('checked_in_at');
    }

    public function package()
    {
        return $this->belongsTo(OrganizationPackage::class, 'organization_package_id');
    }

    /**
     * Check if this specific event can issue more tickets 
     * based on the package it was created under.
     */
    public function hasPackageCapacity(int $quantity = 1, bool $isComp = false): bool
    {
        if (!$this->package) {
            return false;
        }

        if ($isComp) {
            return ($this->package->comp_tickets_used + $quantity) <= $this->package->comp_tickets_included;
        }

        return ($this->package->tickets_used + $quantity) <= $this->package->tickets_included;
    }

    public function isWorkshop(): bool
    {
        return $this->event_type === 'workshop';
    }
    
    public function isStandard(): bool
    {
        return $this->event_type === 'standard' || empty($this->event_type);
    }
    
    public function getEventTypeLabelAttribute(): string
    {
        return config('constants.event_types.' . $this->event_type . '.label')
            ?? ucfirst($this->event_type ?? 'standard');
    }
}