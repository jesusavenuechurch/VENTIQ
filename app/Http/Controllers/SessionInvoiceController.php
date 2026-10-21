<?php

namespace App\Http\Controllers;

use App\Models\SessionAgreement;
use App\Models\SessionInvoice;
use App\Support\Logo;
use Illuminate\Support\Facades\Auth;

/** A corporate Sessions invoice as a PDF: for the organization's admins (Plan) and super admins. */
class SessionInvoiceController extends Controller
{
    public function download(SessionInvoice $invoice)
    {
        $user = Auth::user();
        // The organization's Admins (who see its plan), or VENTIQ.
        abort_unless($user->isSuperAdmin() || ($invoice->organization_id === $user->organization_id && \App\Support\SessionsAccess::can($user, 'plan')), 404);

        return self::pdf($invoice)->download("{$invoice->number}.pdf");
    }

    public static function pdf(SessionInvoice $invoice)
    {
        $invoice->loadMissing(['organization', 'sessions']);
        $agreement = SessionAgreement::where('organization_id', $invoice->organization_id)->first();
        $ventiq = public_path('images/ventiq-logo-112.png');

        return \PDF::loadView('sessions.invoice-pdf', [
            'invoice'      => $invoice,
            'orgLogo'      => Logo::of($invoice->organization),
            'ventiqLogo'   => is_file($ventiq) ? 'data:image/png;base64,' . base64_encode(file_get_contents($ventiq)) : '',
            'billingEmail' => $agreement?->billing_email,
            'freePerMonth' => $agreement?->free_per_month ?? 1,
        ])->setPaper('a4', 'portrait');
    }
}
