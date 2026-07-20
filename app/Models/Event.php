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
        'city',        
        'category',    
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
        'is_sponsored',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'registration_deadline' => 'datetime',
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
            if (empty($event->slug)) {
                $event->slug = static::generateUniqueSlug(
                    $event->name,
                    $event->organization_id
                );
            }

            if (empty($event->tagline)) {
                $event->tagline = $event->name;
            }

            // Mirrors the pattern already used for tagline: if city is
            // left blank for any reason, default at the model level too,
            // not just in the form — protects data created outside the
            // Filament form (seeders, imports, API).
            if (empty($event->city)) {
                $event->city = 'Maseru';
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

    public function requiresDeposit(): bool
    {
        return $this->allow_installments === true
            && ! is_null($this->minimum_deposit_percentage);
    }

    /**
     * Resolves to the predefined category's color, or a neutral navy
     * fallback for custom/unrecognized categories so the homepage
     * cards never end up with an undefined or blank accent.
     */
    public function getCategoryColorAttribute(): string
    {
        return config('constants.categories.' . $this->category . '.color', '#1D4069');
    }

    /**
     * Predefined categories resolve to their nice label; a custom
     * category (one an organizer typed in that isn't in the config
     * list) just displays as typed, title-cased.
     */
    public function getCategoryLabelAttribute(): string
    {
        return config('constants.categories.' . $this->category . '.label')
            ?? ucfirst($this->category ?? '');
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

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }
}