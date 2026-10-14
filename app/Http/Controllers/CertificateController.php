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
            return view('certificates.lookup');
        }

        $number = trim($request->query('number'));

        $certificate = Certificate::where('certificate_number', $number)->first();

        if (!$certificate) {
            return view('certificates.lookup', [
                'error'  => "No certificate found for \"{$number}\".",
                'number' => $number,
            ]);
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

        return view('certificates.verify', ['certificate' => $certificate]);
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
        $qrBase64 = base64_encode(
            \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(300)->margin(1)->generate($certificate->verify_url)
        );

        return \PDF::loadView('certificates.certificate', ['certificate' => $certificate, 'qrBase64' => $qrBase64])
            ->setPaper('a4', 'landscape');
    }
}
