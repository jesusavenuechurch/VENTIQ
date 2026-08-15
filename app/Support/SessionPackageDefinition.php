<?php

namespace App\Support;

class SessionPackageDefinition
{
    public static function tiers(): array
    {
        return [
            'free' => [
                'label'             => 'Free',
                'rank'              => 0,
                'sessions_included' => 1,
                'whatsapp_included' => 0,
                'sms_included'      => 0,
                'price'             => 0,
            ],
            'team' => [
                'label'             => 'Team',
                'rank'              => 1,
                'sessions_included' => 3,
                'whatsapp_included' => 50,
                'sms_included'      => 50,
                'price'             => 500,
            ],
            'business' => [
                'label'             => 'Business',
                'rank'              => 2,
                'sessions_included' => 6,
                'whatsapp_included' => 150,
                'sms_included'      => 150,
                'price'             => 1200,
            ],
            'enterprise' => [
                'label'             => 'Enterprise',
                'rank'              => 3,
                // Custom, quote-based — no default numbers, admin enters
                // the real agreed figures per deal when creating the row.
                'sessions_included' => 0,
                'whatsapp_included' => 0,
                'sms_included'      => 0,
                'price'             => null,
            ],
        ];
    }

    public static function get(string $tier): ?array
    {
        return self::tiers()[$tier] ?? null;
    }

    public static function rankOf(string $tier): int
    {
        return self::get($tier)['rank'] ?? -1;
    }

    /**
     * PAYG bundle price for a given quantity of session credits.
     * 1 => M150, 3 => M400 (bundle prices, not per-unit), 4-9 => M125/session,
     * 10-19 => M110/session, 20+ => M100/session.
     */
    public static function paygBundlePrice(int $quantity): float
    {
        return match (true) {
            $quantity <= 0 => 0.0,
            $quantity === 1 => 150.0,
            $quantity === 2 => 150.0 * 2, // no bundle break below 3, priced per-unit at the base rate
            $quantity === 3 => 400.0,
            $quantity >= 4 && $quantity <= 9 => round($quantity * 125, 2),
            $quantity >= 10 && $quantity <= 19 => round($quantity * 110, 2),
            default => round($quantity * 100, 2),
        };
    }

    /**
     * Preset PAYG quick-add shortcuts shown in the purchase UI.
     */
    public static function paygPresets(): array
    {
        return [
            1  => self::paygBundlePrice(1),
            3  => self::paygBundlePrice(3),
            10 => self::paygBundlePrice(10),
            20 => self::paygBundlePrice(20),
        ];
    }
}
