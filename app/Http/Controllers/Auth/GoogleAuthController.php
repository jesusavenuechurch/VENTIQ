<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OrganizationInvite;
use App\Models\User;
use App\Services\AccountProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request)
    {
        session(['auth_intent' => $request->query('intent', 'host')]);
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, AccountProvisioningService $provisioning)
    {
        $intent = session()->pull('auth_intent', 'host'); // pull = read + forget

        $googleUser = Socialite::driver('google')->stateless()->user();
        $existing = User::where('email', $googleUser->getEmail())->first();

        if ($existing) {
            if (!$existing->google_id) {
                $existing->update(['google_id' => $googleUser->getId()]);
            }
            Auth::guard('web')->login($existing, true);
            $request->session()->regenerate();
            return redirect()->to(\App\Support\IntentRedirect::resolve($intent));
        }

        // No account with this email yet — first honor a pending invite to
        // an existing org (same as the password-based invite-accept flow).
        // Only fall through to auto-provisioning a brand new org if there's
        // no invite waiting for them.
        $invite = OrganizationInvite::where('email', $googleUser->getEmail())
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($invite) {
            $user = User::create([
                'name'              => $googleUser->getName(),
                'email'             => $invite->email,
                'password'          => Hash::make(Str::random(40)),
                'google_id'         => $googleUser->getId(),
                'organization_id'   => $invite->organization_id,
                'email_verified_at' => now(),
            ]);

            $invite->markAccepted();

            Auth::guard('web')->login($user, true);
            $request->session()->regenerate();

            return redirect()->to(\App\Support\IntentRedirect::resolve($intent));
        }

        // Brand new account, no invite — auto-provision an org named after
        // the person signing in. They can rename it from their profile page
        // once that exists; this keeps Google sign-in a single step instead
        // of bouncing to a separate registration form.
        $user = $provisioning->provision([
            'org_name'          => $googleUser->getName(),
            'org_phone'         => '',
            'org_district'      => '',
            'user_name'         => $googleUser->getName(),
            'user_email'        => $googleUser->getEmail(),
            'google_id'         => $googleUser->getId(),
            'email_verified_at' => now(),
            'source'            => 'google',
        ]);

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        return redirect()->to(\App\Support\IntentRedirect::resolve($intent));
    }
}