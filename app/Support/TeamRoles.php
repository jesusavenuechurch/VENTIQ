<?php

namespace App\Support;

/** The roles an organization can give its own people, in plain words. */
class TeamRoles
{
    public const ROLES = [
        'org_admin' => ['Admin', 'Everything, including the team, payment accounts and editing events.'],
        'staff'     => ['Staff', 'Confirms payments, issues complimentary tickets, resends tickets and downloads reports.'],
        'scanner'   => ['Door', 'Scans tickets at the entrance. Nothing else.'],
        'viewer'    => ['Viewer', 'Sees events, attendees and reports, but can\'t change anything.'],
    ];

    public static function valid(?string $role): bool
    {
        return array_key_exists((string) $role, self::ROLES);
    }

    public static function label(?string $role): string
    {
        return self::ROLES[$role][0] ?? ucfirst(str_replace('_', ' ', (string) $role));
    }
}
