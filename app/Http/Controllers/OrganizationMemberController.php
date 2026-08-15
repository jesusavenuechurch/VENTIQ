<?php

namespace App\Http\Controllers;

use App\Mail\OrganizationInviteMail;
use App\Models\OrganizationInvite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class OrganizationMemberController extends Controller
{
    /**
     * The Team page: everyone in the org's shared workspace, plus
     * anyone who's been invited but hasn't joined yet.
     *
     * No role check here — per VENTIQ's model, any Member can see
     * and grow the roster. Only the four org-admin actions (billing,
     * delete session, etc.) are gated elsewhere; inviting isn't one
     * of them.
     */
    public function index()
    {
        $organization = Auth::user()->getOrganization();

        $members = $organization->members()->orderBy('name')->get();

        $pendingInvites = $organization->invites()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        return view('organization.members', compact('members', 'pendingInvites', 'organization'));
    }

    public function invite(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $organization = Auth::user()->getOrganization();

        if (User::where('email', $validated['email'])->exists()) {
            return back()->withErrors(['email' => 'Someone with that email already has a VENTIQ account.']);
        }

        // Don't spam a second invite while one is still live — refresh it instead.
        $invite = $organization->invites()
            ->where('email', $validated['email'])
            ->whereNull('accepted_at')
            ->first();

        if ($invite) {
            $invite->update(['expires_at' => now()->addDays(7)]);
        } else {
            $invite = OrganizationInvite::create([
                'organization_id' => $organization->id,
                'email' => $validated['email'],
                'invited_by' => Auth::id(),
            ]);
        }

        Mail::to($invite->email)->send(new OrganizationInviteMail($invite));

        return back()->with('status', "Invite sent to {$invite->email}.");
    }

    public function revoke(OrganizationInvite $invite)
    {
        abort_unless($invite->organization_id === Auth::user()->organization_id, 403);

        $invite->delete();

        return back()->with('status', 'Invite revoked.');
    }
}