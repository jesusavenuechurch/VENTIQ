<?php

namespace App\Services;

use App\Models\Participant;
use Illuminate\Support\Facades\Storage;

class AttendanceCardImageService
{
    private const WIDTH = 1080;
    private const HEIGHT = 1560;

    private const NAVY = [29, 64, 105];
    private const ORANGE = [240, 127, 34];
    private const WHITE = [255, 255, 255];
    private const SOFT_GRAY = [139, 149, 166];
    private const TINT = [243, 246, 250];
    private const HAIRLINE = [228, 233, 240];

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
        $gray = imagecolorallocate($canvas, ...self::SOFT_GRAY);
        [$navyR, $navyG, $navyB] = self::NAVY;
        $mutedNavy = imagecolorallocatealpha($canvas, $navyR, $navyG, $navyB, 70);

        imagefilledrectangle($canvas, 0, 0, self::WIDTH, self::HEIGHT, $white);
        $this->drawCornerTexture($canvas);

        // Top and bottom accent bars — a card, not a poster.
        imagefilledrectangle($canvas, 0, 0, self::WIDTH, 10, $orange);
        imagefilledrectangle($canvas, 0, self::HEIGHT - 10, self::WIDTH, self::HEIGHT, $orange);

        // Small and muted on purpose — this is the attendee's card, not a
        // Ventiq ad. The wordmark just needs to be present.
        $y = 56;
        $y = $this->drawCenteredText($canvas, 'VENTIQ', 26, self::WIDTH / 2, $y, $mutedNavy, 6) + 50;

        $name = $client?->full_name ?? 'Attendee';
        $avatarSize = 340;
        $avatarBlock = $avatarSize + 44; // ring + gap allowance, see drawRingedAvatar()
        $this->drawRingedAvatar($canvas, $client?->photo_path, $name, $avatarSize, (int) ((self::WIDTH - $avatarBlock) / 2), $y);
        $y += $avatarBlock + 90;

        // The name is the whole point of the card — largest, boldest text
        // on it, right under the face so there's no ambiguity who this is.
        $y = $this->drawWrappedText($canvas, $name, 54, self::WIDTH / 2, $y, $navy, self::WIDTH - 140, 2) + 30;

        // "I was there" — now a secondary line, not the headline.
        $y = $this->drawTwoToneText($canvas, 'I WAS ', 'THERE.', 30, self::WIDTH / 2, $y, $gray, $orange) + 50;

        $title = $session?->resolved_title ?? 'a Ventiq session';
        $y = $this->drawWrappedText($canvas, $title, 40, self::WIDTH / 2, $y, $navy, self::WIDTH - 180, 2) + 20;

        if ($orgName = $session?->organization?->name) {
            $y = $this->drawCenteredText($canvas, mb_strtoupper($orgName), 22, self::WIDTH / 2, $y, $gray, 4) + 44;
        } else {
            $y += 20;
        }

        $y = $this->drawMetaRow($canvas, $session, $y, $navy) + 56;

        $y = $this->drawInfoBar($canvas, $session, $client, $y, $navy, $orange, $gray) + 60;

        $this->drawFooter($canvas, $y, $navy, $gray);

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

    /* ------------------------------------------------------------
     | Sections
     ------------------------------------------------------------ */

    // A handful of small navy squares in two corners — enough texture to
    // avoid a flat, empty page without competing with the content.
    private function drawCornerTexture(\GdImage $canvas): void
    {
        [$navyR, $navyG, $navyB] = self::NAVY;
        $square = imagecolorallocatealpha($canvas, $navyR, $navyG, $navyB, 112);
        $side = 7;

        foreach ([0, 1] as $cornerX) {
            for ($row = 0; $row < 6; $row++) {
                for ($col = 0; $col < 6 - $row; $col++) {
                    $x = $cornerX === 0 ? 40 + $col * 34 : self::WIDTH - 40 - $col * 34;
                    $y = 40 + $row * 34;
                    imagefilledrectangle($canvas, $x - $side, $y - $side, $x + $side, $y + $side, $square);
                }
            }
        }
    }

    private function drawRingedAvatar(\GdImage $canvas, ?string $photoPath, string $name, int $size, int $x, int $y): void
    {
        $ringWidth = 12;
        $gap = 10;
        $outerDiameter = $size + 2 * ($ringWidth + $gap);
        $centerX = $x + (int) ($outerDiameter / 2);
        $centerY = $y + (int) ($outerDiameter / 2);

        $orange = imagecolorallocate($canvas, ...self::ORANGE);
        $white = imagecolorallocate($canvas, ...self::WHITE);

        imagefilledellipse($canvas, $centerX, $centerY, $outerDiameter, $outerDiameter, $orange);
        imagefilledellipse($canvas, $centerX, $centerY, $outerDiameter - $ringWidth * 2, $outerDiameter - $ringWidth * 2, $white);

        $photo = null;

        if ($photoPath && Storage::disk('public')->exists($photoPath)) {
            $raw = @imagecreatefromstring(Storage::disk('public')->get($photoPath));
            if ($raw !== false) {
                $photo = $this->circularCrop($raw, $size);
                imagedestroy($raw);
            }
        }

        if ($photo === null) {
            $photo = $this->initialsAvatar($name, $size);
        }

        imagecopy($canvas, $photo, $centerX - (int) ($size / 2), $centerY - (int) ($size / 2), 0, 0, $size, $size);
        imagedestroy($photo);
    }

    private function drawTwoToneText(\GdImage $canvas, string $partA, string $partB, int $fontSize, float $centerX, float $y, int $colorA, int $colorB): float
    {
        $boxA = imagettfbbox($fontSize, 0, $this->fontPath, $partA);
        $boxB = imagettfbbox($fontSize, 0, $this->fontPath, $partB);
        $widthA = abs($boxA[4] - $boxA[0]);
        $widthB = abs($boxB[4] - $boxB[0]);
        $totalWidth = $widthA + $widthB;

        $startX = $centerX - ($totalWidth / 2);

        imagettftext($canvas, $fontSize, 0, (int) $startX, (int) $y, $colorA, $this->fontPath, $partA);
        imagettftext($canvas, $fontSize, 0, (int) ($startX + $widthA), (int) $y, $colorB, $this->fontPath, $partB);

        $height = abs($boxA[5] - $boxA[1]);

        return $y + $height;
    }

    private function drawMetaRow(\GdImage $canvas, $session, float $y, int $navy): float
    {
        $parts = [];

        if ($date = $session?->date) {
            $parts[] = ['icon' => 'calendar', 'text' => $date->format('d M Y')];
        }

        if ($location = $session?->location) {
            $parts[] = ['icon' => 'pin', 'text' => str($location)->limit(28)];
        }

        if (empty($parts)) {
            return $y;
        }

        $fontSize = 22;
        $iconSize = 24;
        $gap = 14;
        $groupGap = 40;

        // Measure each [icon + text] group to center the whole row.
        $groupWidths = [];
        foreach ($parts as $part) {
            $box = imagettfbbox($fontSize, 0, $this->fontPath, (string) $part['text']);
            $groupWidths[] = $iconSize + $gap + abs($box[4] - $box[0]);
        }

        $totalWidth = array_sum($groupWidths) + $groupGap * (count($parts) - 1);
        $x = self::WIDTH / 2 - $totalWidth / 2;
        $rowHeight = 0;

        foreach ($parts as $i => $part) {
            $iconY = (int) $y;
            $this->drawIcon($canvas, $part['icon'], (int) $x, $iconY, $iconSize, $navy);

            $textX = $x + $iconSize + $gap;
            $box = imagettfbbox($fontSize, 0, $this->fontPath, (string) $part['text']);
            imagettftext($canvas, $fontSize, 0, (int) $textX, (int) ($y + $iconSize - 4), $navy, $this->fontPath, (string) $part['text']);

            $rowHeight = max($rowHeight, $iconSize);
            $x += $groupWidths[$i] + $groupGap;
        }

        return $y + $rowHeight;
    }

    private function drawInfoBar(\GdImage $canvas, $session, $client, float $y, int $navy, int $orange, int $gray): float
    {
        $margin = 90;
        $left = $margin;
        $right = self::WIDTH - $margin;
        $height = 190;
        $top = (int) $y;
        $bottom = $top + $height;

        $tint = imagecolorallocate($canvas, ...self::TINT);
        $this->roundedRect($canvas, $left, $top, $right, $bottom, 28, $tint);

        $columns = [
            ['icon' => 'people', 'label' => 'SESSION', 'value' => str($session?->resolved_title ?? '—')->limit(16)],
            ['icon' => 'building', 'label' => 'ORGANIZED BY', 'value' => str($session?->organization?->name ?? '—')->limit(16)],
            ['icon' => 'calendar', 'label' => 'DATE', 'value' => $session?->date?->format('d M Y') ?? '—'],
        ];

        $colWidth = ($right - $left) / 3;
        $hairline = imagecolorallocate($canvas, ...self::HAIRLINE);

        foreach ($columns as $i => $col) {
            $colCenter = $left + $colWidth * $i + $colWidth / 2;

            if ($i > 0) {
                imagesetthickness($canvas, 1);
                imageline($canvas, (int) ($left + $colWidth * $i), $top + 30, (int) ($left + $colWidth * $i), $bottom - 30, $hairline);
            }

            $iconSize = 30;
            $this->drawIcon($canvas, $col['icon'], (int) ($colCenter - $iconSize / 2), $top + 30, $iconSize, $orange);

            $labelY = $top + 90;
            $this->drawCenteredText($canvas, $col['label'], 13, $colCenter, $labelY, $gray, 2);

            $this->drawCenteredText($canvas, (string) $col['value'], 18, $colCenter, $labelY + 30, $navy);
        }

        return $bottom;
    }

    private function drawFooter(\GdImage $canvas, float $y, int $navy, int $gray): void
    {
        $website = config('ventiq.company.website', 'https://ventiq.co.ls');

        $qrSize = 130;
        $qrPng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size($qrSize * 3)->margin(0)->generate($website);
        $qrImage = imagecreatefromstring($qrPng);
        $qrX = (int) ((self::WIDTH - $qrSize) / 2);
        imagecopyresampled($canvas, $qrImage, $qrX, (int) $y, 0, 0, $qrSize, $qrSize, imagesx($qrImage), imagesy($qrImage));
        imagedestroy($qrImage);

        $y += $qrSize + 24;
        $y = $this->drawCenteredText($canvas, 'SCAN TO VISIT', 13, self::WIDTH / 2, $y, $gray, 3) + 32;

        $label = mb_strtoupper(preg_replace('#^https?://#', '', $website));
        $this->drawCenteredText($canvas, $label, 20, self::WIDTH / 2, $y, $navy, 2);
    }

    /* ------------------------------------------------------------
     | Small hand-drawn icon glyphs — deliberately simple line marks,
     | not a copied icon set.
     ------------------------------------------------------------ */

    private function drawIcon(\GdImage $canvas, string $name, int $x, int $y, int $size, int $color): void
    {
        imagesetthickness($canvas, 2);

        match ($name) {
            'calendar' => $this->iconCalendar($canvas, $x, $y, $size, $color),
            'pin'      => $this->iconPin($canvas, $x, $y, $size, $color),
            'people'   => $this->iconPeople($canvas, $x, $y, $size, $color),
            'building' => $this->iconBuilding($canvas, $x, $y, $size, $color),
            'globe'    => $this->iconGlobe($canvas, $x, $y, $size, $color),
            default    => null,
        };
    }

    private function iconCalendar(\GdImage $canvas, int $x, int $y, int $size, int $color): void
    {
        imagerectangle($canvas, $x, $y + (int) ($size * 0.15), $x + $size, $y + $size, $color);
        imageline($canvas, $x, $y + (int) ($size * 0.4), $x + $size, $y + (int) ($size * 0.4), $color);
        imageline($canvas, $x + (int) ($size * 0.22), $y, $x + (int) ($size * 0.22), $y + (int) ($size * 0.3), $color);
        imageline($canvas, $x + (int) ($size * 0.78), $y, $x + (int) ($size * 0.78), $y + (int) ($size * 0.3), $color);
    }

    private function iconPin(\GdImage $canvas, int $x, int $y, int $size, int $color): void
    {
        $cx = $x + (int) ($size / 2);
        $r = (int) ($size * 0.32);
        imageellipse($canvas, $cx, $y + $r, $r * 2, $r * 2, $color);
        imagefilledpolygon($canvas, [
            $cx - (int) ($r * 0.7), $y + $r + (int) ($r * 0.6),
            $cx + (int) ($r * 0.7), $y + $r + (int) ($r * 0.6),
            $cx, $y + $size,
        ], $color);
    }

    private function iconPeople(\GdImage $canvas, int $x, int $y, int $size, int $color): void
    {
        $r = (int) ($size * 0.28);
        imageellipse($canvas, $x + $r, $y + $r, $r * 2, $r * 2, $color);
        imageellipse($canvas, $x + $size - $r, $y + $r, $r * 2, $r * 2, $color);
        imagearc($canvas, $x + $r, $y + $size - $r + 2, (int) ($r * 2.4), (int) ($r * 2.2), 180, 360, $color);
        imagearc($canvas, $x + $size - $r, $y + $size - $r + 2, (int) ($r * 2.4), (int) ($r * 2.2), 180, 360, $color);
    }

    private function iconBuilding(\GdImage $canvas, int $x, int $y, int $size, int $color): void
    {
        imagerectangle($canvas, $x + (int) ($size * 0.1), $y, $x + (int) ($size * 0.9), $y + $size, $color);
        $winSize = (int) ($size * 0.14);
        foreach ([0.28, 0.62] as $rowFrac) {
            foreach ([0.28, 0.62] as $colFrac) {
                $wx = $x + (int) ($size * $colFrac);
                $wy = $y + (int) ($size * $rowFrac);
                imagerectangle($canvas, $wx, $wy, $wx + $winSize, $wy + $winSize, $color);
            }
        }
    }

    private function iconGlobe(\GdImage $canvas, int $x, int $y, int $size, int $color): void
    {
        $cx = $x + (int) ($size / 2);
        $cy = $y + (int) ($size / 2);
        imageellipse($canvas, $cx, $cy, $size, $size, $color);
        imageellipse($canvas, $cx, $cy, (int) ($size * 0.5), $size, $color);
        imageline($canvas, $x, $cy, $x + $size, $cy, $color);
    }

    /* ------------------------------------------------------------
     | Primitives
     ------------------------------------------------------------ */

    private function roundedRect(\GdImage $canvas, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagefilledrectangle($canvas, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($canvas, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($canvas, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($canvas, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($canvas, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($canvas, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
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
