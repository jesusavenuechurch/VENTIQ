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

        return \Inertia\Inertia::render('Organization/InviteAccept', [
            'invite' => [
                'token'        => $invite->token,
                'email'        => $invite->email,
                'organization' => $invite->organization->name,
                'role'         => \App\Support\TeamRoles::label($invite->role),
                'pending'      => $invite->isPending(),
                'accepted'     => (bool) $invite->accepted_at,
            ],
            'meta'   => ['title' => "Join {$invite->organization->name} | VENTIQ"],
        ])->withViewData(['noindex' => true]);
    }

    public function submit(Request $request, string $token)
    {
        $invite = OrganizationInvite::where('token', $token)->firstOrFail();

        abort_unless($invite->isPending(), 410, 'This invite has expired.');

        if (User::where('email', $invite->email)->exists()) {
            return back()->withErrors(['name' => "There's already a VENTIQ account for {$invite->email}. Sign in with it instead, and ask your team's admin to add you."]);
        }

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

        $user->assignRole(\App\Support\TeamRoles::valid($invite->role) ? $invite->role : 'staff');
        $invite->markAccepted();

        event(new Registered($user));
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(\App\Support\IntentRedirect::resolve(null));
    }
}