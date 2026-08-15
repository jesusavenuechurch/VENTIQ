<?php

namespace App\Http\Controllers;

use App\Models\OrganizationInvite;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class OrganizationInviteAcceptController extends Controller
{
    public function show(string $token)
    {
        $invite = OrganizationInvite::where('token', $token)->firstOrFail();

        if (!$invite->isPending()) {
            return view('organization.invite-expired', compact('invite'));
        }

        return view('organization.invite-accept', compact('invite'));
    }

    public function submit(Request $request, string $token)
    {
        $invite = OrganizationInvite::where('token', $token)->firstOrFail();

        abort_unless($invite->isPending(), 410, 'This invite has expired.');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $invite->email,
            'password' => Hash::make($validated['password']),
            'organization_id' => $invite->organization_id,
            'email_verified_at' => now(), // trusted: they clicked a link sent to this exact email
        ]);

        $invite->markAccepted();

        event(new Registered($user));
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('sessions.index'));
    }
}