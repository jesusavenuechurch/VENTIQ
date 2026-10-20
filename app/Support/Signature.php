<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/** Drawn signatures: kept on the private disk, never overwritten, inlined where they're used. */
class Signature
{
    public const DISK = 'local';

    /** Store a drawn PNG (a data URL from the signature pad); the path, or null if it isn't one. */
    public static function store(string $dataUrl, string $folder): ?string
    {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            return null;
        }
        $png = base64_decode($m[1], true);
        // A real PNG, of a size a signature is.
        if ($png === false || strlen($png) > 512 * 1024 || !str_starts_with($png, "\x89PNG")) {
            return null;
        }
        $path = trim($folder, '/') . '/' . now()->format('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.png';
        Storage::disk(self::DISK)->put($path, $png);

        return $path;
    }

    public static function dataUri(?string $path): ?string
    {
        if (!$path || !Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(Storage::disk(self::DISK)->get($path));
    }
}
