<?php

namespace App\Support;

/**
 * Phone numbers as the rest of the app stores them: +266 and eight digits
 * for Lesotho, or a full international number when someone gives one.
 */
class Phone
{
    /** "5949 4756", "26659494756" or "+266 5949-4756" → "+26659494756"; null if it isn't a number we can reach. */
    public static function normalize(?string $input): ?string
    {
        $raw = trim((string) $input);
        $digits = preg_replace('/\D/', '', $raw);

        return match (true) {
            strlen($digits) === 8                                  => '+266' . $digits,
            strlen($digits) === 11 && str_starts_with($digits, '266') => '+' . $digits,
            str_starts_with($raw, '+') && strlen($digits) >= 9 && strlen($digits) <= 15 => '+' . $digits,
            default                                                => null,
        };
    }
}
