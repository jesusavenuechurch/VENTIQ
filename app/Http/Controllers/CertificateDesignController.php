<?php

namespace App\Http\Controllers;

use App\Models\{Certificate, CertificateDesign, Event, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Storage};
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * The certificate designer: the organization's design, or a programme's
 * own, with a live preview made from the same view as the PDF.
 */
class CertificateDesignController extends Controller
{
    public function edit(Request $request)
    {
        $organization = Auth::user()->organization;
        $programme = $this->programme($request);
        $own = CertificateDesign::where('organization_id', $organization->id)->where('event_id', $programme?->id)->first();
        $design = $own ?? CertificateDesign::for($organization->id, $programme?->id);

        return Inertia::render('Programmes/CertificateDesign', [
            'design' => [
                'layout'        => $design->layout,
                'title'         => $design->title,
                'subtitle'      => $design->subtitle ?? '',
                'wording'       => $design->wording ?: CertificateDesign::DEFAULT_WORDING,
                'primary_color' => $design->primary_color,
                'accent_color'  => $design->accent_color,
                'show_logo'     => (bool) $design->show_logo,
                'signatories'   => array_values(array_slice(array_map('intval', $design->signatories ?? []), 0, 2)),
            ],
            // A programme with no design of its own uses the organization's.
            'programme'  => $programme ? ['id' => $programme->id, 'name' => $programme->name, 'own' => (bool) $own] : null,
            'programmes' => Event::where('organization_id', $organization->id)->where('is_programme', true)->latest('event_date')->get(['id', 'name'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]),
            'signers'    => User::where('organization_id', $organization->id)->where('can_sign_certificates', true)->orderBy('name')->get()->map(fn (User $u) => [
                'id'     => $u->id,
                'name'   => $u->name,
                'title'  => $u->signature_title,
                'signed' => (bool) $u->signature_path,
            ]),
            'layouts'      => collect(CertificateDesign::LAYOUTS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'placeholders' => CertificateDesign::PLACEHOLDERS,
            'hasLogo'      => (bool) ($organization->logo_path && Storage::disk('public')->exists($organization->logo_path)),
            'meta'         => ['title' => 'Certificate design | VENTIQ Sessions'],
        ]);
    }

    public function update(Request $request)
    {
        $programme = $this->programme($request);
        $data = $this->validated($request);

        CertificateDesign::updateOrCreate(
            ['organization_id' => Auth::user()->organization_id, 'event_id' => $programme?->id],
            $data,
        );

        return back()->with('status', $programme
            ? "Saved. {$programme->name}'s certificates use this design from now on; ones already issued keep theirs."
            : "Saved. Certificates issued from now on use it; ones already issued keep theirs.");
    }

    /** A programme goes back to the organization's design. */
    public function reset(Request $request)
    {
        $programme = $this->programme($request);
        abort_unless($programme, 404);
        CertificateDesign::where('organization_id', Auth::user()->organization_id)->where('event_id', $programme->id)->delete();

        return redirect()->route('sessions.certificates.design', ['programme' => $programme->id])
            ->with('status', "{$programme->name} uses the organization's design again.");
    }

    /** The certificate as it would look, for a design not yet saved: HTML from the PDF's own view. */
    public function preview(Request $request)
    {
        $design = new CertificateDesign(['organization_id' => Auth::user()->organization_id] + $this->validated($request));

        return response()->json(['html' => view('certificates.certificate', ['c' => CertificateDesign::view($design->snapshot(), $this->sampleFacts($request))])->render()]);
    }

    /** The saved design as a PDF, with a sample name. */
    public function sample(Request $request)
    {
        $programme = $this->programme($request);
        $design = CertificateDesign::for(Auth::user()->organization_id, $programme?->id);

        return Certificate::renderPdf($design->snapshot(), $this->sampleFacts($request))->download('certificate-sample.pdf');
    }

    private function sampleFacts(Request $request): array
    {
        $organization = Auth::user()->organization;
        $programme = $this->programme($request);

        return [
            'recipient'    => 'Nthabiseng Letsie',
            'programme'    => $programme?->name ?? 'Leadership in Practice',
            'organization' => $organization->name,
            'venue'        => $programme ? $programme->venue : 'Maseru',
            'dates'        => $programme ? CertificateDesign::programmeDates($programme) : now()->format('j F Y'),
            'days'         => '3 days',
            'issued'       => now()->format('j F Y'),
            'number'       => 'VQ-' . now()->year . '-000123',
            'verify_url'   => route('certificates.lookup'),
            'logo_path'    => $organization->logo_path,
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'layout'        => ['required', Rule::in(array_keys(CertificateDesign::LAYOUTS))],
            'title'         => 'required|string|max:60',
            'subtitle'      => 'nullable|string|max:80',
            'wording'       => 'required|string|max:600',
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color'  => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'show_logo'     => 'boolean',
            'signatories'   => 'array|max:2',
            'signatories.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('organization_id', Auth::user()->organization_id)->where('can_sign_certificates', true)],
        ], ['signatories.*.exists' => 'Someone chosen to sign is no longer allowed to.', 'signatories.max' => 'Up to two people sign a certificate.']);

        return $data + ['signatories' => [], 'show_logo' => false];
    }

    private function programme(Request $request): ?Event
    {
        $id = $request->input('programme', $request->query('programme'));
        if (!$id) {
            return null;
        }

        return Event::where('id', $id)->where('organization_id', Auth::user()->organization_id)->where('is_programme', true)->firstOrFail();
    }
}
