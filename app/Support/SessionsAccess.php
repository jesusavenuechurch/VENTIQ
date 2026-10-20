<?php

namespace App\Support;

use App\Models\User;

/**
 * What each team role may do in Sessions (TeamRoles has what they do in
 * Events):
 *
 * - desk: see the desk.            Everyone.
 * - checkin: check people in.      Admin, Staff, Door.
 * - view: read reports, programmes. Admin, Staff, Viewer.
 * - run: schedule and run sessions, write and review reports, set up
 *   programmes and certificates.   Admin, Staff.
 * - plan: buy a plan or sessions.  Admin.
 *
 * Someone on the team without a role is a Viewer, unless the
 * organization has no Admin at all: then they're it, so no one is
 * locked out of their own account.
 */
class SessionsAccess
{
    public const ABILITIES = ['desk', 'checkin', 'view', 'run', 'plan'];

    private const ROLES = [
        'org_admin' => ['desk', 'checkin', 'view', 'run', 'plan'],
        'staff'     => ['desk', 'checkin', 'view', 'run'],
        'scanner'   => ['desk', 'checkin'],
        'viewer'    => ['desk', 'view'],
    ];

    public static function role(User $user): string
    {
        foreach (array_keys(self::ROLES) as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        $hasAdmin = $user->organization_id
            && User::where('organization_id', $user->organization_id)->role('org_admin')->exists();

        return $hasAdmin ? 'viewer' : 'org_admin';
    }

    public static function can(User $user, string $ability): bool
    {
        return in_array($ability, self::ROLES[static::role($user)], true);
    }

    /** @return array<string, bool> */
    public static function all(User $user): array
    {
        $allowed = self::ROLES[static::role($user)];

        return collect(self::ABILITIES)->mapWithKeys(fn ($a) => [$a => in_array($a, $allowed, true)])->all();
    }

    /** The ability a Sessions route needs. Anything not listed needs `run`. */
    public static function abilityFor(?string $routeName): string
    {
        return match (true) {
            in_array($routeName, ['sessions.index'], true) => 'desk',
            in_array($routeName, [
                'sessions.checkin', 'sessions.checkin.store', 'sessions.checkin.update', 'sessions.checkin.pdf', 'sessions.checkin.card',
                'sessions.checkin.qr', 'sessions.checkin.pass', 'sessions.checkin.pass.pdf', 'sessions.participants.count', 'programmes.participants.pdf',
            ], true) => 'checkin',
            in_array($routeName, [
                'sessions.reports', 'sessions.report', 'sessions.series.show', 'sessions.actions.index', 'sessions.report.pdf', 'sessions.report.status',
                'programmes.index', 'programmes.show', 'programmes.report', 'programmes.report.status', 'programmes.certificates.download',
            ], true) => 'view',
            in_array($routeName, ['sessions.plan', 'organization.session-plan.payment', 'paylesotho.session-package.initiate'], true) => 'plan',
            default => 'run',
        };
    }
}
