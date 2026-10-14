<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Certificate extends Model
{
    protected $fillable = [
        'organization_id',
        'event_id',
        'client_id',
        'token',
        'certificate_number',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Certificate $cert) {
            if (empty($cert->token)) {
                $cert->token = Str::random(48);
            }

            if (empty($cert->certificate_number)) {
                $cert->certificate_number = static::nextCertificateNumber();
            }
        });
    }

    // Human-referenceable filing number, resets each year — "VQ-2026-000184".
    // The token remains the real credential (QR/verify link); this is purely
    // for someone to type in or file against. Locked per-year to avoid two
    // concurrent issues colliding on the same number.
    private static function nextCertificateNumber(): string
    {
        $year = now()->year;
        $prefix = "VQ-{$year}-";

        return DB::transaction(function () use ($year, $prefix) {
            $last = static::where('certificate_number', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->orderByDesc('certificate_number')
                ->value('certificate_number');

            $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

            return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function getVerifyUrlAttribute(): string
    {
        return route('certificates.verify', $this->token);
    }

    // LinkedIn's no-OAuth "Add to Profile" deep link for certifications —
    // one click adds this as a real Certification entry on the recipient's
    // profile, linked back to our verify page as the credential URL.
    public function getLinkedInAddUrlAttribute(): string
    {
        return 'https://www.linkedin.com/profile/add?' . http_build_query([
            'startTask'        => 'CERTIFICATION_NAME',
            'name'             => $this->programme?->name,
            'organizationName' => $this->organization?->name,
            'issueYear'        => $this->issued_at?->year,
            'issueMonth'       => $this->issued_at?->month,
            'certUrl'          => $this->verify_url,
            'certId'           => (string) $this->id,
        ]);
    }
}