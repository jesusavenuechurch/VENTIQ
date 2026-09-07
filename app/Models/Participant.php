<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Participant extends Model
{
    protected $fillable = [
        'organization_id',
        'event_id',
        'session_id',
        'client_id',
        'session_segment_id',
        'ticket_id',
        'role',
        'source',
        'attended_at',
        'institution',
        'position',
        'district',
        'signature_path',
        'signed_at',
        'signed_by',
        'signature_status',
        'signed_on_device',
        'notified_at',
        'report_notified_at',
    ];

    protected $casts = [
        'attended_at' => 'datetime',
        'notified_at' => 'datetime',
        'report_notified_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    // The actual check-in boundary — this is what every attendance
    // count/query should scope through now, not event_id. event_id
    // stays on the record too (denormalized, handy for "everyone who
    // ever attended anything in this Programme" queries later) but
    // it's no longer the uniqueness key.
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(SessionSegment::class, 'session_segment_id');
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function isCheckedIn(): bool
    {
        return $this->attended_at !== null;
    }

    public function checkIn(): void
    {
        $this->update(['attended_at' => now()]);
    }

    // ── Signature capture — same shape as the (still separate) workshop
    // flow's WorkshopTicketDetail, ported here since Participant is the
    // Programme equivalent of Ticket for that purpose. `workshop_districts`
    // is reused as-is (already flagged in config/constants.php as a
    // reusable, misnamed list — not workshop-specific content).

    public function isSigned(): bool
    {
        return $this->signature_status === 'signed' && $this->signature_path !== null;
    }

    public function isPending(): bool
    {
        return $this->signature_status === 'pending';
    }

    public function getSignatureUrlAttribute(): ?string
    {
        return $this->signature_path
            ? Storage::disk('public')->url($this->signature_path)
            : null;
    }

    public function getDistrictLabelAttribute(): string
    {
        return config('constants.workshop_districts.' . $this->district)
            ?? $this->district
            ?? '—';
    }

    public function getStatusLabelAttribute(): string
    {
        return config('constants.signature_statuses.' . $this->signature_status . '.label')
            ?? ucfirst($this->signature_status);
    }

    public function getStatusColorAttribute(): string
    {
        return config('constants.signature_statuses.' . $this->signature_status . '.color')
            ?? 'gray';
    }

    public function storeSignature(string $base64Image, int $signedBy, ?string $deviceInfo = null): bool
    {
        try {
            $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $base64Image);
            $decoded   = base64_decode($imageData);

            $path = 'signatures/programmes/'
                . $this->organization_id
                . '/participant_' . $this->id . '.png';

            Storage::disk('public')->put($path, $decoded);

            $this->update([
                'signature_path'   => $path,
                'signed_at'        => now(),
                'signed_by'        => $signedBy,
                'signature_status' => 'signed',
                'signed_on_device' => $deviceInfo,
            ]);

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error(
                "Failed to store signature for participant {$this->id}: " . $e->getMessage()
            );
            return false;
        }
    }

    public function markDeclined(int $signedBy): void
    {
        $this->update([
            'signature_status' => 'declined',
            'signed_by'        => $signedBy,
            'signed_at'        => now(),
        ]);
    }

    public function markSkipped(int $signedBy): void
    {
        $this->update([
            'signature_status' => 'skipped',
            'signed_by'        => $signedBy,
            'signed_at'        => now(),
        ]);
    }
}