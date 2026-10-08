<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Mail\OrganizationInviteMail;
use App\Models\{Organization, OrganizationInvite, User};
use App\Support\TeamRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Log, Mail};
use Illuminate\Validation\Rule;

/**
 * The organization's people and what each may do. Everyone can see the
 * team; only people with manage_staff change it. Nobody changes their own
 * access, and the last Admin can't be demoted or removed, so an
 * organization can't lock itself out.
 */
class TeamController extends Controller
{
    public function index(Request $request)
    {
        $organization = $this->organization($request);

        return view('organizer.team', [
            'members'   => $organization->members()->with('roles')->orderBy('name')->get(),
            'invites'   => $organization->invites()->whereNull('accepted_at')->where('expires_at', '>', now())->latest()->get(),
            'roles'     => TeamRoles::ROLES,
            'canManage' => $request->user()->can('manage_staff'),
            'seatsLeft' => $this->seatsLeft($organization),
        ]);
    }

    public function invite(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role'  => ['required', Rule::in(array_keys(TeamRoles::ROLES))],
        ]);
        $email = strtolower($data['email']);

        if ($existing = User::where('email', $email)->first()) {
            return back()->withInput()->withErrors(['email' => $existing->organization_id === $organization->id
                ? "{$existing->name} is already on your team."
                : 'That email already has a VENTIQ account with another organization. They\'ll need to use a different email.']);
        }

        $invite = $organization->invites()->where('email', $email)->whereNull('accepted_at')->first();

        if (!$invite && $this->seatsLeft($organization) === 0) {
            return back()->withInput()->withErrors(['email' => 'Your package has no team places left. Remove someone or upgrade to add more.']);
        }

        $invite
            ? $invite->update(['role' => $data['role'], 'expires_at' => now()->addDays(7), 'invited_by' => $request->user()->id])
            : $invite = OrganizationInvite::create([
                'organization_id' => $organization->id, 'email' => $email, 'role' => $data['role'], 'invited_by' => $request->user()->id,
            ]);

        Mail::to($invite->email)->send(new OrganizationInviteMail($invite));
        Log::info("Team invite {$invite->id} sent by user {$request->user()->id}", ['role' => $data['role']]);

        return back()->with('status', "Invite sent to {$invite->email} as " . TeamRoles::label($data['role']) . '. It works for 7 days.');
    }

    public function revokeInvite(Request $request, OrganizationInvite $invite)
    {
        abort_unless($invite->organization_id === $this->organization($request)->id, 404);
        $invite->delete();

        return back()->with('status', "The invite to {$invite->email} no longer works.");
    }

    public function changeRole(Request $request, User $member)
    {
        $organization = $this->organization($request);
        $this->guardMember($request, $member, $organization);
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(TeamRoles::ROLES))]]);

        if ($member->hasRole('org_admin') && $data['role'] !== 'org_admin' && $this->adminCount($organization) <= 1) {
            return back()->with('status', "{$member->name} is your only Admin. Make someone else an Admin first.");
        }

        $member->syncRoles([$data['role']]);
        Log::info("User {$member->id} given role {$data['role']} by user {$request->user()->id}");

        return back()->with('status', "{$member->name} is now " . TeamRoles::label($data['role']) . '.');
    }

    /** Take someone off the team. Their account stays, without access to this organization. */
    public function remove(Request $request, User $member)
    {
        $organization = $this->organization($request);
        $this->guardMember($request, $member, $organization);

        if ($member->hasRole('org_admin') && $this->adminCount($organization) <= 1) {
            return back()->with('status', "{$member->name} is your only Admin, so they can't be removed.");
        }

        $member->syncRoles([]);
        $member->forceFill(['organization_id' => null])->save();
        Log::info("User {$member->id} removed from organization {$organization->id} by user {$request->user()->id}");

        return back()->with('status', "{$member->name} no longer has access to {$organization->name}.");
    }

    private function organization(Request $request): Organization
    {
        return $request->attributes->get('organization');
    }

    private function guardMember(Request $request, User $member, Organization $organization): void
    {
        abort_unless($member->organization_id === $organization->id, 404);
        abort_if($member->is($request->user()), 403, 'You can\'t change your own access.');
        abort_if($member->isSuperAdmin(), 403);
    }

    private function adminCount(Organization $organization): int
    {
        return $organization->members()->role('org_admin')->count();
    }

    /** Places left under the organization's package, or null when nothing limits the team. */
    private function seatsLeft(Organization $organization): ?int
    {
        $limit = $organization->activePackages()->max('max_users');
        if (!$limit) {
            return null;
        }

        $used = $organization->members()->count()
            + $organization->invites()->whereNull('accepted_at')->where('expires_at', '>', now())->count();

        return max(0, $limit - $used);
    }
}
