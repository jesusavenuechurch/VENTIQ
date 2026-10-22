<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CertificateController extends Controller
{
    // Public lookup by the human-typed certificate number — for someone
    // who has the printed number but not the QR (an employer checking a
    // physical copy, say). Same GET-with-query-string pattern already used
    // by PublicSessionCheckinController::show(). A match redirects to the
    // canonical token URL — the same page the QR code encodes.
    public function lookup(Request $request)
    {
        if (!$request->filled('number')) {
            return $this->lookupPage('', null);
        }

        $number = trim($request->query('number'));

        $certificate = Certificate::where('certificate_number', $number)->first();

        if (!$certificate) {
            return $this->lookupPage($number, "No certificate found for \"{$number}\". Check the number on the document.");
        }

        return redirect()->route('certificates.verify', $certificate->token);
    }

    // Public — the token itself is the credential, same pattern as the
    // session check-in and organization invite links.
    public function verify(string $token)
    {
        $certificate = Certificate::where('token', $token)
            ->with(['client', 'programme', 'organization'])
            ->firstOrFail();

        return \Inertia\Inertia::render('Certificates/Verify', [
            'certificate' => [
                'holder'       => $certificate->client->full_name,
                'programme'    => $certificate->programme->name,
                'organization' => $certificate->organization->name,
                'issued'       => $certificate->issued_at->format('j M Y'),
                'number'       => $certificate->certificate_number,
                'linkedIn'     => $certificate->linked_in_add_url,
                'download'     => route('certificates.download.public', $certificate->token),
            ],
            'meta' => ['title' => "Certificate · {$certificate->client->full_name} | VENTIQ", 'description' => "{$certificate->client->full_name} took part in {$certificate->programme->name}, hosted by {$certificate->organization->name}."],
        ]);
    }

    private function lookupPage(string $number, ?string $error)
    {
        return \Inertia\Inertia::render('Certificates/Lookup', ['number' => $number, 'error' => $error, 'meta' => ['title' => 'Verify a certificate | VENTIQ']]);
    }

    // Public, same token-as-credential model as verify() — the recruiter
    // or connection landing on the verify page needs to be able to pull the
    // PDF without an account.
    public function downloadPublic(string $token)
    {
        $certificate = Certificate::where('token', $token)
            ->with(['client', 'programme', 'organization'])
            ->firstOrFail();

        return $this->renderPdf($certificate)->download("ventiq-certificate-{$certificate->id}.pdf");
    }

    // Org-scoped copy, reached from the Programme page.
    public function download(Event $programme, Certificate $certificate)
    {
        abort_unless($certificate->event_id === $programme->id, 404);
        abort_unless($certificate->organization_id === Auth::user()->organization_id, 403);

        $certificate->load(['client', 'programme', 'organization']);

        return $this->renderPdf($certificate)->download("ventiq-certificate-{$certificate->id}.pdf");
    }

    private function renderPdf(Certificate $certificate)
    {
        return $certificate->pdf();
    }
}
