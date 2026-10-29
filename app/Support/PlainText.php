<?php

namespace App\Support;

/**
 * Text from Ventiq Assist, made safe for the PDFs: the PDF fonts have no
 * non-breaking hyphen, thin or narrow spaces, and showed them as "?".
 */
class PlainText
{
    public static function normalize(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        return strtr($text, [
            "\u{2010}" => '-', "\u{2011}" => '-', "\u{2012}" => '-', "\u{2212}" => '-',
            "\u{00A0}" => ' ', "\u{2007}" => ' ', "\u{2009}" => ' ', "\u{200A}" => ' ', "\u{202F}" => ' ',
            "\u{200B}" => '', "\u{FEFF}" => '',
        ]);
    }
}
