<?php

namespace App\Models;

use App\Support\Qr;
use App\Support\Signature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * How an organization's certificates look: a layout, the title and
 * wording, its colours and logo, and up to two signatories from the team.
 * A programme can have its own; otherwise it uses the organization's.
 * Each certificate keeps a snapshot of the design it was issued with, so
 * changing the design later doesn't change certificates already out.
 */
class CertificateDesign extends Model
{
    public const LAYOUTS = [
        'banner'  => 'Banner',
        'classic' => 'Classic',
        'minimal' => 'Minimal',
    ];

    /** The words between the name and the signatures; {placeholders} are filled in for each person. */
    public const PLACEHOLDERS = ['programme', 'organization', 'venue', 'dates', 'days'];

    public const DEFAULT_WORDING = 'participated in {programme}, hosted by {organization} at {venue} on {dates}, and is recognized for their attendance and contribution.';

    protected $fillable = ['organization_id', 'event_id', 'layout', 'title', 'subtitle', 'wording', 'primary_color', 'accent_color', 'show_logo', 'signatories'];

    protected $casts = ['show_logo' => 'boolean', 'signatories' => 'array'];

    protected $attributes = [
        'layout' => 'banner', 'title' => 'Certificate', 'subtitle' => 'of attendance',
        'primary_color' => '#1D4069', 'accent_color' => '#F07F22', 'show_logo' => true,
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** The design a programme's certificates use: its own, else the organization's, else the default. */
    public static function for(int $organizationId, ?int $programmeId = null): self
    {
        return ($programmeId ? self::where('organization_id', $organizationId)->where('event_id', $programmeId)->first() : null)
            ?? self::where('organization_id', $organizationId)->whereNull('event_id')->first()
            ?? new self(['organization_id' => $organizationId]);
    }

    /** Its signatories who are still on the team and still allowed to sign, in order. */
    public function signatoryUsers(): Collection
    {
        $ids = collect($this->signatories ?? [])->filter()->take(2)->values();
        $users = User::whereIn('id', $ids)->where('organization_id', $this->organization_id)->where('can_sign_certificates', true)->get()->keyBy('id');

        return $ids->map(fn ($id) => $users[$id] ?? null)->filter()->values();
    }

    /** What an issued certificate keeps: the design, and each signatory's signature as it is now. */
    public function snapshot(): array
    {
        return [
            'layout'      => array_key_exists($this->layout, self::LAYOUTS) ? $this->layout : 'banner',
            'title'       => $this->title ?: 'Certificate',
            'subtitle'    => $this->subtitle,
            'wording'     => $this->wording ?: self::DEFAULT_WORDING,
            'primary'     => $this->primary_color ?: '#1D4069',
            'accent'      => $this->accent_color ?: '#F07F22',
            'show_logo'   => (bool) $this->show_logo,
            'signatories' => $this->signatoryUsers()->map(fn (User $u) => [
                'name'      => $u->name,
                'title'     => $u->signature_title,
                'signature' => $u->signature_path,
            ])->all(),
        ];
    }

    /**
     * Everything the certificate view needs, from a snapshot and the facts
     * of one certificate (or a sample, for the designer's preview).
     *
     * @param array{recipient: string, programme: string, organization: string, venue: ?string, dates: ?string, days: ?string, issued: string, number: string, verify_url: string, logo_path: ?string} $facts
     */
    public static function view(array $design, array $facts): array
    {
        return [
            'layout'       => $design['layout'] ?? 'banner',
            'title'        => $design['title'] ?? 'Certificate',
            'subtitle'     => $design['subtitle'] ?? null,
            'primary'      => self::color($design['primary'] ?? null, '#1D4069'),
            'accent'       => self::color($design['accent'] ?? null, '#F07F22'),
            'logo'         => ($design['show_logo'] ?? true) ? self::logo($facts['logo_path'] ?? null) : null,
            'organization' => $facts['organization'],
            'recipient'    => $facts['recipient'],
            'wording'      => self::renderWording($design['wording'] ?? self::DEFAULT_WORDING, $facts),
            'issued'       => $facts['issued'],
            'number'       => $facts['number'],
            'qr'           => Qr::dataUri($facts['verify_url'], 300),
            'signatories'  => collect($design['signatories'] ?? [])->map(fn ($s) => [
                'name'  => $s['name'] ?? '',
                'title' => $s['title'] ?? null,
                'image' => Signature::dataUri($s['signature'] ?? null),
            ])->all(),
        ];
    }

    /**
     * The wording as HTML: escaped, with each {placeholder} in bold. One
     * with nothing to fill it drops out with the little word before it
     * ("at {venue}" when there's no venue).
     */
    public static function renderWording(string $template, array $values): string
    {
        $html = e($template);
        foreach (self::PLACEHOLDERS as $key) {
            $value = trim((string) ($values[$key] ?? ''));
            $html = $value === ''
                ? preg_replace('/(\s+(at|in|on|from|by|for|over))?\s*\{' . $key . '\}/i', '', $html)
                : str_replace('{' . $key . '}', '<b>' . e($value) . '</b>', $html);
        }

        return preg_replace('/\s+([,.;])/', '$1', $html);
    }

    /** "19 to 21 October 2026", "19 October 2026", or null. */
    public static function programmeDates(Event $programme): ?string
    {
        $dates = Session::where('event_id', $programme->id)->whereNotNull('date')->orderBy('date')->pluck('date');
        if ($dates->isEmpty()) {
            return $programme->event_date?->format('j F Y');
        }
        [$first, $last] = [$dates->first(), $dates->last()];

        return match (true) {
            $first->isSameDay($last)        => $first->format('j F Y'),
            $first->isSameMonth($last)      => $first->format('j') . ' to ' . $last->format('j F Y'),
            $first->isSameYear($last)       => $first->format('j F') . ' to ' . $last->format('j F Y'),
            default                         => $first->format('j F Y') . ' to ' . $last->format('j F Y'),
        };
    }

    private static function color(?string $value, string $fallback): string
    {
        return $value && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $fallback;
    }

    private static function logo(?string $path): ?string
    {
        return \App\Support\Logo::dataUri($path);
    }
}
