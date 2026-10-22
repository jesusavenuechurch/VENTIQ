<?php

namespace App\Http\Controllers;

use App\Support\Signature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Your own signature, for the certificates you sign: drawn once, used on
 * every certificate issued after, until you draw it again. Only for
 * people an Admin has allowed to sign (Team).
 */
class SignatureController extends Controller
{
    public function show()
    {
        $user = Auth::user();

        return Inertia::render('Sessions/Signature', [
            'allowed'   => (bool) $user->can_sign_certificates,
            'signature' => $user->signatureDataUri(),
            'title'     => $user->signature_title ?? '',
            'updated'   => $user->signature_updated_at?->format('j M Y'),
            'name'      => $user->name,
            'meta'      => ['title' => 'My signature | VENTIQ Sessions'],
        ])->withViewData(['noindex' => true]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->can_sign_certificates, 403, 'An Admin has to allow you to sign certificates first.');

        $data = $request->validate([
            'signature' => 'nullable|string|max:800000',
            'title'     => 'nullable|string|max:80',
        ]);

        if ($request->exists('title')) {
            $user->signature_title = trim($data['title'] ?? '') ?: null;
        }
        if (!empty($data['signature'])) {
            $path = Signature::store($data['signature'], "signatures/users/{$user->id}");
            if (!$path) {
                return back()->withErrors(['signature' => "That signature didn't come through. Draw it again."]);
            }
            // The old file stays: certificates already issued still use it.
            $user->signature_path = $path;
            $user->signature_updated_at = now();
        }
        $user->save();

        return back()->with('status', !empty($data['signature']) ? 'Saved. Certificates issued from now on carry this signature.' : 'Saved.');
    }
}
