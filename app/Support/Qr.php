<?php

namespace App\Support;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

/** A QR code as PNG where the server can make one (Imagick), else SVG. Both work in PDFs and pages. */
class Qr
{
    /** @return array{0: string, 1: string} the image and its type */
    public static function make(string $url, int $size = 400): array
    {
        $make = fn (string $format) => (string) QrCode::format($format)->size($size)->margin(1)->generate($url);

        if (extension_loaded('imagick')) {
            try {
                return [$make('png'), 'image/png'];
            } catch (\Throwable) {
                // Falls through to the SVG.
            }
        }

        return [$make('svg'), 'image/svg+xml'];
    }

    /** For an <img src>: data:...;base64,... */
    public static function dataUri(string $url, int $size = 400): string
    {
        [$image, $type] = self::make($url, $size);

        return "data:{$type};base64," . base64_encode($image);
    }
}
