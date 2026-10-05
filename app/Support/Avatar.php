<?php

namespace App\Support;

/**
 * A friendly illustrated face for a person, the same one every time.
 * Picked from the fixed set in public/images/avatars (made by
 * scripts/generate-avatars.mjs) by a hash of something stable about them:
 * a user's email, an attendee's phone.
 */
class Avatar
{
    public const COUNT = 32;

    public static function url(?string $key): string
    {
        $index = crc32(strtolower(trim((string) $key))) % self::COUNT;

        return asset("images/avatars/{$index}.svg");
    }
}
