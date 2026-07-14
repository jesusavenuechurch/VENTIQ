<?php

namespace App\Support;

class IntentRedirect
{
    public static function resolve(?string $intent): string
    {
        return $intent === 'session'
            ? route('sessions.index')
            : route('filament.admin.pages.dashboard');
    }
}