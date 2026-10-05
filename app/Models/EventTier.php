<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventTier extends Model
{
    protected $fillable = [
        'event_id',
        'tier_name',
        'price',
        'color',
        'description',
        'quantity_available',
        'quantity_sold',
        'is_active',
        'quantity_per_purchase',
        'is_group_ticket',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    // ===== RELATIONSHIPS =====

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'event_tier_id');
    }

    // ===== ACCESSORS =====

    /**
     * Get tier color as RGB array (for QR generation)
     */
    public function getTierColorRgbAttribute(): array
    {
        if ($this->color) {
            return $this->hexToRgb($this->color);
        }

        // Default colors by tier name if no custom color set
        return $this->getDefaultColorForName($this->tier_name);
    }

    /**
     * Get tier display name with color indicator
     */
    public function getTierDisplayAttribute(): string
    {
        $name = $this->tier_name;

        // Add color emoji or indicator
        if ($this->color) {
            // Use the color to determine an emoji
            $emoji = $this->getEmojiForColor($this->color);
            return "{$emoji} {$name}";
        }

        // Default emojis by name
        return match(strtolower(trim($name))) {
            'general', 'standard' => "🎫 {$name}",
            'silver' => "🥈 {$name}",
            'gold' => "🥇 {$name}",
            'vip' => "👑 {$name}",
            'diamond', 'premium' => "💠 {$name}",
            'platinum' => "✨ {$name}",
            'ruby' => "💎 {$name}",
            'emerald' => "💚 {$name}",
            default => "🎟️ {$name}",
        };
    }

    /**
     * Get remaining tickets available
     */
    public function getAvailableCountAttribute(): int
    {
        if (!$this->quantity_available) {
            return PHP_INT_MAX; // Unlimited
        }

        return $this->quantity_available - $this->quantity_sold;
    }

    /**
     * Check if tier has availability
     */
    public function hasAvailability(): bool
    {
        if (!$this->quantity_available) {
            return true; // Unlimited
        }

        return $this->quantity_sold < $this->quantity_available;
    }

    /**
     * Get availability percentage
     */
    public function getAvailabilityPercentageAttribute(): int
    {
        if (!$this->quantity_available) {
            return 100; // Unlimited = 100%
        }

        $percentage = (($this->quantity_available - $this->quantity_sold) / $this->quantity_available) * 100;
        return (int)$percentage;
    }

    // ===== HELPER METHODS =====

    /**
     * Convert hex color to RGB array
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        // Handle 3-digit hex
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return ['r' => 0, 'g' => 0, 'b' => 0]; // Fallback to black
        }

        $color = hexdec($hex);
        return [
            'r' => ($color >> 16) & 0xFF,
            'g' => ($color >> 8) & 0xFF,
            'b' => $color & 0xFF,
        ];
    }

    /**
     * Get default color for tier name
     */
    private function getDefaultColorForName(string $name): array
    {
        $normalized = strtolower(trim($name));

        return match($normalized) {
            'general', 'standard' => ['r' => 0, 'g' => 100, 'b' => 200],        // Blue: #0064C8
            'silver' => ['r' => 192, 'g' => 192, 'b' => 192],                   // Silver: #C0C0C0
            'gold' => ['r' => 255, 'g' => 215, 'b' => 0],                       // Gold: #FFD700
            'vip' => ['r' => 139, 'g' => 69, 'b' => 19],                        // Brown: #8B4513
            'diamond', 'premium' => ['r' => 128, 'g' => 0, 'b' => 128],         // Purple: #800080
            'platinum' => ['r' => 230, 'g' => 230, 'b' => 250],                 // Lavender: #E6E6FA
            'ruby' => ['r' => 155, 'g' => 17, 'b' => 30],                       // Ruby: #9B111E
            'emerald' => ['r' => 80, 'g' => 200, 'b' => 120],                   // Green: #50C878
            default => ['r' => 0, 'g' => 0, 'b' => 0],                          // Black fallback
        };
    }

    /**
     * Get emoji based on color
     */
    private function getEmojiForColor(string $hex): string
    {
        $hex = strtolower(ltrim($hex, '#'));

        return match($hex) {
            'c0c0c0' => '🥈',      // Silver
            'ffd700' => '🥇',      // Gold
            '800080' => '💠',      // Purple/Diamond
            '9b111e' => '💎',      // Ruby Red
            'e6e6fa' => '✨',      // Platinum
            '50c878' => '💚',      // Emerald Green
            '8b4513' => '👑',      // VIP Brown
            '0064c8' => '🎫',      // Blue
            default => '🎟️',        // Generic ticket
        };
    }

    // ===== MUTATORS =====

    /**
     * Ensure color is stored in #RRGGBB format
     */
    public function setColorAttribute(?string $value): void
    {
        if (!$value) {
            $this->attributes['color'] = null;
            return;
        }

        // If it's already hex format, use it
        if (preg_match('/^#?[0-9A-Fa-f]{6}$/', $value)) {
            $this->attributes['color'] = '#' . ltrim($value, '#');
            return;
        }

        // Try to find color by name
        $colorMap = [
            'silver' => '#C0C0C0',
            'gold' => '#FFD700',
            'blue' => '#0064C8',
            'purple' => '#800080',
            'diamond' => '#800080',
            'ruby' => '#9B111E',
            'red' => '#9B111E',
            'emerald' => '#50C878',
            'green' => '#50C878',
            'platinum' => '#E6E6FA',
            'vip' => '#8B4513',
        ];

        $normalized = strtolower(trim($value));
        $this->attributes['color'] = $colorMap[$normalized] ?? null;
    }
}
