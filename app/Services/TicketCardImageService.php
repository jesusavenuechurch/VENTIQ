<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a shareable/attachable PNG version of a ticket, for WhatsApp
 * image-header delivery — same visual language as AttendanceCardImageService
 * (portrait, navy/orange, corner texture) so the two read as one product,
 * just carrying ticket fields (event, tier, date, venue, QR, voucher code)
 * instead of attendance fields. The existing avatar_path PDF stays as-is
 * for email/download; this is a separate, WhatsApp-specific artifact.
 */
class TicketCardImageService
{
    private const WIDTH = 1080;
    private const HEIGHT = 1560;

    private const NAVY = [29, 64, 105];
    private const ORANGE = [240, 127, 34];
    private const GOLD = [212, 175, 55];
    private const EMERALD = [16, 185, 129];
    private const WHITE = [255, 255, 255];
    private const SOFT_GRAY = [139, 149, 166];
    private const TINT = [243, 246, 250];
    private const HAIRLINE = [228, 233, 240];

    private string $fontPath;

    public function __construct()
    {
        $this->fontPath = resource_path('fonts/Inter.ttf');
    }

    public function generate(Ticket $ticket): string
    {
        $event = $ticket->event;
        $client = $ticket->client;
        $isVip = str_contains(strtolower($ticket->tier->tier_name ?? ''), 'vip');

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagesavealpha($canvas, true);

        $navy = imagecolorallocate($canvas, ...self::NAVY);
        $orange = imagecolorallocate($canvas, ...self::ORANGE);
        $accent = imagecolorallocate($canvas, ...($isVip ? self::GOLD : self::EMERALD));
        $white = imagecolorallocate($canvas, ...self::WHITE);
        $gray = imagecolorallocate($canvas, ...self::SOFT_GRAY);
        [$navyR, $navyG, $navyB] = self::NAVY;
        $mutedNavy = imagecolorallocatealpha($canvas, $navyR, $navyG, $navyB, 70);

        imagefilledrectangle($canvas, 0, 0, self::WIDTH, self::HEIGHT, $white);
        $this->drawCornerTexture($canvas);

        imagefilledrectangle($canvas, 0, 0, self::WIDTH, 10, $orange);
        imagefilledrectangle($canvas, 0, self::HEIGHT - 10, self::WIDTH, self::HEIGHT, $orange);

        $y = 56;
        $y = $this->drawCenteredText($canvas, 'VENTIQ', 26, self::WIDTH / 2, $y, $mutedNavy, 6) + 60;

        // Tier badge — VIP gold, everything else emerald, matching the web/PDF ticket.
        $tierLabel = mb_strtoupper($ticket->tier->tier_name ?? 'Standard') . ' TICKET';
        $y = $this->drawBadge($canvas, $tierLabel, $accent, $white, $y) + 50;

        $y = $this->drawWrappedText($canvas, $event->name ?? 'Event', 52, self::WIDTH / 2, $y, $navy, self::WIDTH - 140, 2) + 40;

        $name = $client?->full_name ?? 'Guest';
        $y = $this->drawCenteredText($canvas, mb_strtoupper($name), 28, self::WIDTH / 2, $y, $orange, 2) + 60;

        // QR — the functional heart of the ticket, so it gets the biggest
        // single element on the card.
        $qrSize = 420;
        $qrX = (int) ((self::WIDTH - $qrSize) / 2);
        $this->drawQrBox($canvas, $ticket, $qrX, (int) $y, $qrSize, $white, $navy);
        $y += $qrSize + 44;

        if ($ticket->voucher_code) {
            $y = $this->drawVoucherCode($canvas, $ticket->voucher_code, $y, $navy, $gray) + 40;
        } else {
            $y += 10;
        }

        $y = $this->drawInfoBar($canvas, $ticket, $y, $navy, $orange, $gray) + 50;

        $this->drawFooter($canvas, $ticket->ticket_number, $y, $navy, $gray);

        $path = "ticket-cards/{$ticket->id}.png";

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

    private function drawBadge(\GdImage $canvas, string $label, int $bg, int $textColor, float $y): float
    {
        $fontSize = 16;
        $box = imagettfbbox($fontSize, 0, $this->fontPath, $label);
        $textWidth = abs($box[4] - $box[0]);
        $textHeight = abs($box[5] - $box[1]);

        $padX = 28;
        $padY = 16;
        $width = $textWidth + $padX * 2;
        $height = $textHeight + $padY * 2;

        $x1 = (int) ((self::WIDTH - $width) / 2);
        $y1 = (int) $y;
        $x2 = (int) ($x1 + $width);
        $y2 = (int) ($y1 + $height);

        $this->roundedRect($canvas, $x1, $y1, $x2, $y2, (int) ($height / 2), $bg);
        imagettftext($canvas, $fontSize, 0, $x1 + $padX, $y2 - $padY, $textColor, $this->fontPath, $label);

        return (float) $y2;
    }

    private function drawQrBox(\GdImage $canvas, Ticket $ticket, int $x, int $y, int $size, int $white, int $navy): void
    {
        $pad = 24;
        $boxSize = $size + $pad * 2;
        $this->roundedRect($canvas, $x - $pad, $y - $pad, $x - $pad + $boxSize, $y - $pad + $boxSize, 32, $white);

        $qrPng = null;

        if ($ticket->qr_code_path && Storage::disk('public')->exists($ticket->qr_code_path)) {
            $qrPng = Storage::disk('public')->get($ticket->qr_code_path);
        } elseif ($ticket->qr_code) {
            $qrPng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size($size * 3)->margin(0)->generate($ticket->qr_code);
        }

        if ($qrPng) {
            $qrImage = @imagecreatefromstring($qrPng);
            if ($qrImage !== false) {
                imagecopyresampled($canvas, $qrImage, $x, $y, 0, 0, $size, $size, imagesx($qrImage), imagesy($qrImage));
                imagedestroy($qrImage);
            }
        }
    }

    private function drawVoucherCode(\GdImage $canvas, string $code, float $y, int $navy, int $gray): float
    {
        $y = $this->drawCenteredText($canvas, 'ENTRY CODE (IF QR CAN\'T SCAN)', 13, self::WIDTH / 2, $y, $gray, 2) + 20;
        $y = $this->drawCenteredText($canvas, $code, 34, self::WIDTH / 2, $y, $navy, 4);

        return $y;
    }

    private function drawInfoBar(\GdImage $canvas, Ticket $ticket, float $y, int $navy, int $orange, int $gray): float
    {
        $margin = 90;
        $left = $margin;
        $right = self::WIDTH - $margin;
        $height = 170;
        $top = (int) $y;
        $bottom = $top + $height;

        $tint = imagecolorallocate($canvas, ...self::TINT);
        $this->roundedRect($canvas, $left, $top, $right, $bottom, 28, $tint);

        $columns = [
            ['label' => 'DATE', 'value' => $ticket->event->event_date?->format('d M Y') ?? '—'],
            ['label' => 'VENUE', 'value' => str($ticket->event->venue ?? $ticket->event->location ?? '—')->limit(16)],
            ['label' => 'TICKET NO.', 'value' => $ticket->ticket_number],
        ];

        $colWidth = ($right - $left) / 3;
        $hairline = imagecolorallocate($canvas, ...self::HAIRLINE);

        foreach ($columns as $i => $col) {
            $colCenter = $left + $colWidth * $i + $colWidth / 2;

            if ($i > 0) {
                imagesetthickness($canvas, 1);
                imageline($canvas, (int) ($left + $colWidth * $i), $top + 30, (int) ($left + $colWidth * $i), $bottom - 30, $hairline);
            }

            $labelY = $top + 60;
            $this->drawCenteredText($canvas, $col['label'], 13, $colCenter, $labelY, $gray, 2);
            $this->drawCenteredText($canvas, (string) $col['value'], 18, $colCenter, $labelY + 34, $navy);
        }

        return $bottom;
    }

    private function drawFooter(\GdImage $canvas, string $ticketNumber, float $y, int $navy, int $gray): void
    {
        $website = config('ventiq.company.website', 'https://ventiq.co.ls');
        $label = mb_strtoupper(preg_replace('#^https?://#', '', $website));

        $this->drawCenteredText($canvas, 'POWERED BY VENTIQ  ·  ' . $label, 15, self::WIDTH / 2, $y, $gray, 2);
    }

    /* ------------------------------------------------------------
     | Primitives — same as AttendanceCardImageService
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
