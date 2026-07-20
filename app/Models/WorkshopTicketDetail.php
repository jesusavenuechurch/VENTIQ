<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkshopTicketDetail extends Model
{
     protected $fillable = [
        'ticket_id',
        'position',
        'institution',
        'district',
        'signature_path',
        'signed_at',
        'signed_by',
        'signature_status',
        'signed_on_device',
    ];
 
    protected $casts = [
        'signed_at' => 'datetime',
    ];
 
    // ── Relationships ─────────────────────────────────────────────────────────
 
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
 
    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
 
    // ── Helpers ───────────────────────────────────────────────────────────────
 
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
 
    // ── Store signature from base64 ───────────────────────────────────────────
    // Called from the API after the mobile app submits the signature canvas
 
    public function storeSignature(
        string $base64Image,
        int $signedBy,
        ?string $deviceInfo = null
    ): bool {
        try {
            // Strip data URI prefix if present
            $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $base64Image);
            $decoded   = base64_decode($imageData);
 
            $path = 'signatures/workshops/'
                . $this->ticket->event->organization_id
                . '/ticket_' . $this->ticket_id . '.png';
 
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
                "Failed to store signature for ticket {$this->ticket_id}: " . $e->getMessage()
            );
            return false;
        }
    }
 
    // ── Mark as declined or skipped ───────────────────────────────────────────
 
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
