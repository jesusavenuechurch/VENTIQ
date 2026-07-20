<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            return redirect()->intended(\App\Support\IntentRedirect::resolve($intent));
        }

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