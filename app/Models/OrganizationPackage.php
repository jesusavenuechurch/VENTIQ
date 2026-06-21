<?php

namespace App\Models;

use App\Support\PackageDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrganizationPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'package_type',
        'price_paid',
        'events_included',
        'events_used',
        'tickets_included',
        'tickets_used',
        'comp_tickets_included',
        'comp_tickets_used',
        'max_scanners',
        'max_users',
        'scanners_used',
        'overage_ticket_rate',
        'features',
        'status',
        'purchased_at',
        'expires_at',
        'payment_method',
        'payment_reference',
        'purchased_by',
        'agent_commission_processed',
        'is_free_trial',
        'notes',
    ];

    protected $casts = [
        'price_paid'                => 'decimal:2',
        'events_included'           => 'integer',
        'events_used'               => 'integer',
        'tickets_included'          => 'integer',
        'tickets_used'              => 'integer',
        'comp_tickets_included'     => 'integer',
        'comp_tickets_used'         => 'integer',
        'max_scanners'              => 'integer',
        'max_users'                 => 'integer',
        'scanners_used'             => 'integer',
        'overage_ticket_rate'       => 'decimal:2',
        'features'                  => 'array',
        'purchased_at'              => 'datetime',
        'expires_at'                => 'datetime',
        'agent_commission_processed'=> 'boolean',
        'is_free_trial'             => 'boolean',
    ];

    // ===== PACKAGE DEFINITIONS (kept for backward compat, now delegates to PackageDefinition) =====

    public static function getPackageDefinitions(): array
    {
        return PackageDefinition::all();
    }

    public static function createFreeTrialPackage(int $organizationId): self
    {
        $def = PackageDefinition::get('free_trial');

        return self::create([
            'organization_id'       => $organizationId,
            'package_type'          => 'free_trial',
            'price_paid'            => 0,
            'events_included'       => $def['events'],
            'events_used'           => 0,
            'tickets_included'      => $def['tickets'],
            'tickets_used'          => 0,
            'comp_tickets_included' => $def['comp_tickets'],
            'comp_tickets_used'     => 0,
            'overage_ticket_rate'   => $def['overage_rate'],
            'status'                => 'active',
            'purchased_at'          => now(),
            'is_free_trial'         => true,
            // features, max_scanners, max_users stamped by Observer
        ]);
    }

    // ===== RELATIONSHIPS =====

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'organization_package_id');
    }

    public function overages(): HasMany
    {
        return $this->hasMany(PackageOverage::class);
    }

    public function addOns(): HasMany
    {
        return $this->hasMany(PackageAddOn::class);
    }

    public function purchasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by');
    }

    // ===== FEATURE CHECKS =====

    public function hasFeature(string $feature): bool
    {
        // Check base package features
        if (!empty($this->features[$feature])) return true;

        // Check purchased add-ons
        return $this->addOns()->where('feature_key', $feature)->exists();
    }

    public function getEnabledFeatures(): array
    {
        $base = collect($this->features ?? [])
            ->filter(fn ($enabled) => $enabled)
            ->keys()
            ->toArray();

        $addOns = $this->addOns()->pluck('feature_key')->toArray();

        return array_unique(array_merge($base, $addOns));
    }

    // ===== STATUS CHECKS =====

    public function isActive(): bool     { return $this->status === 'active'; }
    public function isExhausted(): bool  { return $this->status === 'exhausted'; }
    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
               ($this->expires_at && $this->expires_at->isPast());
    }

    // ===== AVAILABILITY CHECKS =====

    public function hasEventsRemaining(): bool               { return $this->events_used < $this->events_included; }
    public function hasTicketsRemaining(int $qty = 1): bool  { return ($this->tickets_used + $qty) <= $this->tickets_included; }
    public function hasCompTicketsRemaining(int $qty = 1): bool { return ($this->comp_tickets_used + $qty) <= $this->comp_tickets_included; }
    public function hasScannersAvailable(): bool             { return $this->scanners_used < $this->max_scanners; }

    // ===== GETTERS =====

    public function getRemainingEventsAttribute(): int       { return max(0, $this->events_included - $this->events_used); }
    public function getRemainingTicketsAttribute(): int      { return max(0, $this->tickets_included - $this->tickets_used); }
    public function getRemainingCompTicketsAttribute(): int  { return max(0, $this->comp_tickets_included - $this->comp_tickets_used); }
    public function getRemainingScannersAttribute(): int     { return max(0, $this->max_scanners - $this->scanners_used); }

    public function getUsagePercentageAttribute(): int
    {
        if ($this->tickets_included == 0) return 0;
        return (int) (($this->tickets_used / $this->tickets_included) * 100);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->is_free_trial) return '🎁 Free Trial';
        return PackageDefinition::get($this->package_type)['name'] ?? ucfirst($this->package_type);
    }

    public function getSummaryAttribute(): string
    {
        return "{$this->display_name} — {$this->remaining_events} events, {$this->remaining_tickets} tickets, {$this->remaining_comp_tickets} comp remaining";
    }

    // ===== USAGE TRACKING =====

    public function incrementEventsUsed(): void
    {
        $this->increment('events_used');
        $this->checkIfExhausted();
    }

    public function incrementTicketsUsed(int $qty = 1): void
    {
        $this->increment('tickets_used', $qty);
        $this->checkIfExhausted();
    }

    public function incrementCompTicketsUsed(int $qty = 1): void
    {
        $this->increment('comp_tickets_used', $qty);
    }

    protected function checkIfExhausted(): void
    {
        $this->refresh();
        if ($this->tickets_used >= $this->tickets_included) {
            $this->update(['status' => 'exhausted']);
        }
    }

    // ===== OVERAGE =====

    public function calculateOverage(int $ticketsNeeded, bool $isComp = false): array
    {
        $remaining = $isComp ? $this->remaining_comp_tickets : $this->remaining_tickets;
        $overage   = max(0, $ticketsNeeded - $remaining);
        $rate      = $isComp ? 0 : $this->overage_ticket_rate;

        return [
            'needs_overage'    => $overage > 0,
            'overage_quantity' => $overage,
            'overage_rate'     => $rate,
            'overage_amount'   => $overage * $rate,
            'can_create'       => true,
        ];
    }

    public function recordOverage(int $quantity, bool $isComp, ?int $eventId = null): PackageOverage
    {
        $rate = $isComp ? 0 : $this->overage_ticket_rate;

        return $this->overages()->create([
            'event_id'     => $eventId,
            'overage_type' => $isComp ? 'comp_tickets' : 'tickets',
            'quantity'     => $quantity,
            'rate_per_unit'=> $rate,
            'total_amount' => $quantity * $rate,
            'status'       => 'pending',
        ]);
    }
}