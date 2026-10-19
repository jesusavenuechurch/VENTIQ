<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Screenshots attendees upload as proof of a payment. Kept on the private
 * disk: only the organizer (or VENTIQ, for its own merchant) sees them,
 * through a route that checks who's asking.
 */
class PaymentProof
{
    public const RULES = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:5120'];

    public static function store(?UploadedFile $file): ?string
    {
        if (!$file) {
            return null;
        }

        return $file->storeAs('payment-proofs/' . now()->format('Y-m'), Str::uuid() . '.' . strtolower($file->extension() ?: 'jpg'), 'local');
    }

    public static function response(?string $path)
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store']);
    }
}
