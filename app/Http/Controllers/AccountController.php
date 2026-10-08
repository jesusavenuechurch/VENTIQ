<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash, Log};
use Illuminate\Validation\Rules\Password;

/**
 * Your own account: your name, and a password. People who signed up with
 * Google, or sign in with emailed codes, can add a password here; changing
 * an existing one needs the current one.
 */
class AccountController extends Controller
{
    public function edit(Request $request)
    {
        return view('account', ['user' => $request->user(), 'needsCurrent' => $this->needsCurrent($request)]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $request->user()->update($data);

        return back()->with('status', 'Your name has been updated.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $rules = ['password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]];
        if ($this->needsCurrent($request)) {
            $rules['current_password'] = ['required', 'current_password'];
        }
        $request->validate($rules, ['current_password.current_password' => 'That isn\'t your current password.']);

        $user->forceFill(['password' => Hash::make($request->input('password'))])->save();

        // Sign out other browsers that used the old password.
        Auth::logoutOtherDevices($request->input('password'));
        Log::info("User {$user->id} changed their password");

        return back()->with('status', 'Password saved. You can now sign in with it.');
    }

    /**
     * The current password is asked for unless nobody knows it (Google
     * sign-ups get a random one) or they've just proved they own the inbox
     * by signing in with an emailed code, which is how a forgotten
     * password gets replaced.
     */
    private function needsCurrent(Request $request): bool
    {
        $codeAt = (int) $request->session()->get('signed_in_with_code_at');

        return !$request->user()->google_id && $codeAt < now()->subMinutes(15)->timestamp;
    }
}
