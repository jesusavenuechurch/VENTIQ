<?php

namespace App\Support;

/**
 * A friendly illustrated person, the same one every time: VENTIQ's own
 * Basotho characters in blankets, mokorotlo and head wraps, from the fixed
 * set in public/images/avatars (made by scripts/generate-avatars.mjs),
 * picked by a hash of something stable about them: a user's email, an
 * attendee's phone.
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
