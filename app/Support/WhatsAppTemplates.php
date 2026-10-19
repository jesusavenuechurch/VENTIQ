<?php

namespace App\Support;

/** The WhatsApp templates in config/constants.php (whatsapp_templates). */
class WhatsAppTemplates
{
    /** The template's name, or null while Meta hasn't approved it. */
    public static function name(string $key): ?string
    {
        $template = config("constants.whatsapp_templates.{$key}");

        return ($template['approved'] ?? false) ? ($template['name'] ?? $key) : null;
    }

    public static function hasButton(string $key): bool
    {
        return (bool) config("constants.whatsapp_templates.{$key}.button", false);
    }
}
