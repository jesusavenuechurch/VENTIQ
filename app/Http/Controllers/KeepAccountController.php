<?php

namespace App\Http\Controllers;

use App\Models\{Organization, User};

/** The warning email's "Keep my account" link: counts as using the account. */
class KeepAccountController extends Controller
{
    public function __invoke(User $user)
    {
        $user->forceFill(['last_active_at' => now(), 'removal_warned_at' => null])->saveQuietly();
        if ($user->organization_id) {
            Organization::whereKey($user->organization_id)->update(['removal_warned_at' => null]);
        }

        return view('accounts.kept', ['user' => $user]);
    }
}
