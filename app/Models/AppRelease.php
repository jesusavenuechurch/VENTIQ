<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A build of the VENTIQ Scanner app (Android). The current one is offered
 * on the organizer dashboard: its APK to download, and the Play Store link
 * once the app is listed there. Super admins upload them in Filament.
 */
class AppRelease extends Model
{
    public const DISK = 'local';

    protected $fillable = ['version', 'apk_path', 'apk_size', 'play_store_url', 'notes', 'is_current', 'uploaded_by'];

    protected $casts = ['is_current' => 'boolean', 'apk_size' => 'integer'];

    protected static function booted(): void
    {
        // A new release is current unless said otherwise.
        static::creating(function (AppRelease $release) {
            $release->is_current ??= true;
        });
        // One current release: making one current retires the others.
        static::saved(function (AppRelease $release) {
            if ($release->is_current) {
                static::whereKeyNot($release->id)->where('is_current', true)->update(['is_current' => false]);
            }
        });
        static::saving(function (AppRelease $release) {
            if ($release->isDirty('apk_path')) {
                $release->apk_size = $release->apk_path && Storage::disk(self::DISK)->exists($release->apk_path)
                    ? Storage::disk(self::DISK)->size($release->apk_path) : null;
            }
        });
        static::deleted(function (AppRelease $release) {
            if ($release->apk_path) {
                Storage::disk(self::DISK)->delete($release->apk_path);
            }
        });
    }

    public static function current(): ?self
    {
        return static::where('is_current', true)->latest('id')->first();
    }

    public function hasApk(): bool
    {
        return $this->apk_path && Storage::disk(self::DISK)->exists($this->apk_path);
    }

    public function sizeLabel(): ?string
    {
        return $this->apk_size ? number_format($this->apk_size / 1048576, 1) . ' MB' : null;
    }

    public function downloadName(): string
    {
        return 'VENTIQ-Scanner-' . preg_replace('/[^A-Za-z0-9.\-]/', '', $this->version) . '.apk';
    }
}
