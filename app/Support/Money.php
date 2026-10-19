<?php

namespace App\Support;

/** Prices as attendees see them: "Free", "M250", "M62.50". */
class Money
{
    public static function price($amount): string
    {
        $amount = (float) $amount;
        if ($amount <= 0) {
            return 'Free';
        }

        return 'M' . (floor($amount) == $amount ? number_format($amount) : number_format($amount, 2));
    }
}
