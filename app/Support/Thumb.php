<?php

namespace App\Support;

use Illuminate\Support\Facades\{Log, Storage};

/**
 * Small WebP copies of uploaded images (event posters) for listing cards and
 * pages. Organizers upload phone photos of several MB; a home page of cards
 * loading those is what made it slow. Each copy is made once, on first use
 * or at upload, and kept on the public disk under thumbs/{width}/.
 */
class Thumb
{
    public const WIDTHS = [480, 960];

    /** URL of the copy at this width, made now if missing; the original if it can't be. */
    public static function url(?string $path, int $width = 480): ?string
    {
        if (!$path) {
            return null;
        }

        $disk = Storage::disk('public');
        $thumb = self::path($path, $width);

        if (!$disk->exists($thumb) && !self::make($path, $width)) {
            return $disk->url($path);
        }

        return $disk->url($thumb);
    }

    public static function path(string $path, int $width): string
    {
        return "thumbs/{$width}/" . preg_replace('/\.[^.\/]+$/', '', $path) . '.webp';
    }

    /** Make every size of an image (called when a poster is uploaded). */
    public static function makeAll(string $path): void
    {
        foreach (self::WIDTHS as $width) {
            self::make($path, $width);
        }
    }

    public static function make(string $path, int $width): bool
    {
        $disk = Storage::disk('public');
        if (!function_exists('imagewebp') || !$disk->exists($path)) {
            return false;
        }

        try {
            $source = @imagecreatefromstring($disk->get($path));
            if (!$source) {
                return false;
            }

            $w = imagesx($source);
            $h = imagesy($source);
            $newW = min($width, $w);
            $newH = (int) round($h * $newW / $w);

            $copy = imagecreatetruecolor($newW, $newH);
            imagealphablending($copy, false);
            imagesavealpha($copy, true);
            imagecopyresampled($copy, $source, 0, 0, 0, 0, $newW, $newH, $w, $h);

            ob_start();
            imagewebp($copy, null, 78);
            $disk->put(self::path($path, $width), ob_get_clean());

            imagedestroy($source);
            imagedestroy($copy);

            return true;
        } catch (\Throwable $e) {
            Log::warning("Thumbnail failed for {$path}: {$e->getMessage()}");

            return false;
        }
    }
}
