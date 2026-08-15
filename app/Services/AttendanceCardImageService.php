<?php

namespace App\Services;

use App\Models\Participant;
use Illuminate\Support\Facades\Storage;

class AttendanceCardImageService
{
    private const WIDTH = 1080;
    private const HEIGHT = 1350;

    private const NAVY = [29, 64, 105];
    private const ORANGE = [240, 127, 34];
    private const WHITE = [255, 255, 255];

    private string $fontPath;

    public function __construct()
    {
        $this->fontPath = resource_path('fonts/Inter.ttf');
    }

    /**
     * Renders a shareable PNG attendance card for the participant and
     * stores it on the public disk. Regenerating overwrites the same
     * path, so re-sends never orphan files.
     */
    public function generate(Participant $participant): string
    {
        $session = $participant->session;
        $client = $participant->client;

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagesavealpha($canvas, true);

        $navy = imagecolorallocate($canvas, ...self::NAVY);
        $orange = imagecolorallocate($canvas, ...self::ORANGE);
        $white = imagecolorallocate($canvas, ...self::WHITE);
        $softWhite = imagecolorallocatealpha($canvas, 255, 255, 255, 40);

        imagefilledrectangle($canvas, 0, 0, self::WIDTH, self::HEIGHT, $navy);

        // Thin accent line under the wordmark.
        imagefilledrectangle($canvas, 0, 150, self::WIDTH, 156, $orange);

        // Footer accent band.
        imagefilledrectangle($canvas, 0, self::HEIGHT - 140, self::WIDTH, self::HEIGHT, $orange);

        $this->drawCenteredText($canvas, 'VENTIQ', 80, self::WIDTH / 2, 100, $white, 8);

        $name = $client?->full_name ?? 'Attendee';
        $avatarSize = 420;
        $avatarY = 210;

        $this->drawAvatar($canvas, $client?->photo_path, $name, $avatarSize, (int) ((self::WIDTH - $avatarSize) / 2), $avatarY);

        $textY = $avatarY + $avatarSize + 90;
        $textY = $this->drawCenteredText($canvas, $name, 56, self::WIDTH / 2, $textY, $white) + 60;

        $textY = $this->drawCenteredText($canvas, 'ATTENDED', 26, self::WIDTH / 2, $textY, $softWhite, 6) + 50;

        $title = $session?->resolved_title ?? 'a Ventiq session';
        $textY = $this->drawWrappedText($canvas, $title, 44, self::WIDTH / 2, $textY, $white, self::WIDTH - 160, 2) + 40;

        if ($orgName = $session?->organization?->name) {
            $this->drawCenteredText($canvas, mb_strtoupper($orgName), 24, self::WIDTH / 2, $textY, $softWhite, 4);
        }

        $this->drawCenteredText($canvas, 'VENTIQ.CO.LS', 24, self::WIDTH / 2, self::HEIGHT - 65, $white, 4);

        $path = "attendance-cards/{$participant->id}.png";

        ob_start();
        imagepng($canvas);
        $contents = ob_get_clean();
        imagedestroy($canvas);

        Storage::disk('public')->put($path, $contents);

        return $path;
    }

    public function url(string $path): string
    {
        return Storage::disk('public')->url($path);
    }

    private function drawAvatar(\GdImage $canvas, ?string $photoPath, string $name, int $size, int $x, int $y): void
    {
        $circle = null;

        if ($photoPath && Storage::disk('public')->exists($photoPath)) {
            $raw = @imagecreatefromstring(Storage::disk('public')->get($photoPath));
            if ($raw !== false) {
                $circle = $this->circularCrop($raw, $size);
                imagedestroy($raw);
            }
        }

        if ($circle === null) {
            $circle = $this->initialsAvatar($name, $size);
        }

        imagecopy($canvas, $circle, $x, $y, 0, 0, $size, $size);
        imagedestroy($circle);
    }

    private function circularCrop(\GdImage $src, int $size): \GdImage
    {
        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $cropSize = min($srcW, $srcH);
        $srcX = intdiv($srcW - $cropSize, 2);
        $srcY = intdiv($srcH - $cropSize, 2);

        $square = imagecreatetruecolor($size, $size);
        imagecopyresampled($square, $src, 0, 0, $srcX, $srcY, $size, $size, $cropSize, $cropSize);

        return $this->maskToCircle($square, $size);
    }

    private function initialsAvatar(string $name, int $size): \GdImage
    {
        $square = imagecreatetruecolor($size, $size);
        $orange = imagecolorallocate($square, ...self::ORANGE);
        imagefilledrectangle($square, 0, 0, $size, $size, $orange);

        $white = imagecolorallocate($square, ...self::WHITE);
        $initials = $this->initialsFor($name);
        $this->drawCenteredText($square, $initials, (int) ($size * 0.32), $size / 2, $size / 2 + 20, $white);

        return $this->maskToCircle($square, $size);
    }

    private function maskToCircle(\GdImage $square, int $size): \GdImage
    {
        $out = imagecreatetruecolor($size, $size);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
        imagefilledrectangle($out, 0, 0, $size, $size, $transparent);

        $radius = $size / 2;

        for ($py = 0; $py < $size; $py++) {
            $dy = $py - $radius;
            for ($px = 0; $px < $size; $px++) {
                $dx = $px - $radius;
                if (($dx * $dx + $dy * $dy) <= ($radius * $radius)) {
                    imagesetpixel($out, $px, $py, imagecolorat($square, $px, $py));
                }
            }
        }

        imagedestroy($square);

        return $out;
    }

    private function initialsFor(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_filter($parts);

        if (empty($parts)) {
            return '?';
        }

        $initials = mb_strtoupper(mb_substr($parts[0], 0, 1));
        if (count($parts) > 1) {
            $initials .= mb_strtoupper(mb_substr(end($parts), 0, 1));
        }

        return $initials;
    }

    /**
     * Draws single-line text centered on $centerX, returns the y-coordinate
     * just below the text so callers can stack elements without hardcoding
     * gaps.
     */
    private function drawCenteredText(\GdImage $canvas, string $text, int $size, float $centerX, float $y, int $color, int $letterSpacing = 0): float
    {
        if ($letterSpacing > 0) {
            $text = implode("\xE2\x80\x89", mb_str_split($text));
        }

        $box = imagettfbbox($size, 0, $this->fontPath, $text);
        $textWidth = abs($box[4] - $box[0]);
        $x = $centerX - ($textWidth / 2);

        imagettftext($canvas, $size, 0, (int) $x, (int) $y, $color, $this->fontPath, $text);

        $height = abs($box[5] - $box[1]);

        return $y + $height;
    }

    /**
     * Wraps text across up to $maxLines lines within $maxWidth, centered.
     */
    private function drawWrappedText(\GdImage $canvas, string $text, int $size, float $centerX, float $y, int $color, float $maxWidth, int $maxLines): float
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $attempt = $current === '' ? $word : "{$current} {$word}";
            $box = imagettfbbox($size, 0, $this->fontPath, $attempt);
            $width = abs($box[4] - $box[0]);

            if ($width > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $attempt;
            }

            if (count($lines) >= $maxLines) {
                break;
            }
        }

        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
        }

        $lineHeight = $size * 1.3;

        foreach ($lines as $line) {
            $y = $this->drawCenteredText($canvas, $line, $size, $centerX, $y, $color) + ($lineHeight - $size);
        }

        return $y;
    }
}
