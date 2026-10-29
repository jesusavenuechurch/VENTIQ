<?php

namespace App\Support;

/**
 * Minutes don't guess anyone's gender. Ventiq Assist is told not to, but a
 * small model sometimes still writes "He reported…"; this turns he, she
 * and his into they and their, with the verbs that follow agreeing.
 */
class Pronouns
{
    public static function neutral(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $text = preg_replace_callback('/\b(he|she)\s+(is|was|has|does)\b/i', function ($m) {
            $verb = ['is' => 'are', 'was' => 'were', 'has' => 'have', 'does' => 'do'][strtolower($m[2])];

            return (ctype_upper($m[1][0]) ? 'They' : 'they') . ' ' . $verb;
        }, $text);
        $text = preg_replace_callback('/\b(he|she)\b/i', fn ($m) => ctype_upper($m[1][0]) ? 'They' : 'they', $text);

        return preg_replace_callback('/\bhis\b/i', fn ($m) => ctype_upper($m[0][0]) ? 'Their' : 'their', $text);
    }
}
