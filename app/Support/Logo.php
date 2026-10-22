<?php

namespace App\Support;

use App\Models\Organization;
use Illuminate\Support\Facades\Storage;

/** An organization's logo (from its settings), inlined for a PDF; null when it has none. */
class Logo
{
    public static function dataUri(?string $path): ?string
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }
        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';
        if (!str_starts_with($mime, 'image/')) {
            return null;
        }

        return "data:{$mime};base64," . base64_encode(Storage::disk('public')->get($path));
    }

    public static function of(?Organization $organization): ?string
    {
        return self::dataUri($organization?->logo_path);
    }
}
