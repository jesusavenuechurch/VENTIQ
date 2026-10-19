<?php

namespace App\Console\Commands;

use App\Models\{Organization, User};
use App\Notifications\Accounts\UnusedAccountWarning;
use App\Services\Accounts\UnusedAccounts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Log, Notification};

/**
 * Accounts that never created anything on VENTIQ and haven't been used for
 * two months: warned by email a week ahead, then removed. Signing in, the
 * email's "Keep my account" link, or creating anything keeps them.
 * --dry-run lists who would be warned or removed and changes nothing.
 */
class RemoveUnusedAccounts extends Command
{
    protected $signature = 'accounts:remove-unused {--dry-run : List who would be warned or removed, without doing it}';
    protected $description = 'Warn, then remove, accounts that never created anything and have not been used for two months';

    public function handle(UnusedAccounts $unused): int
    {
        $dry = (bool) $this->option('dry-run');
        $warnBefore = now()->subDays(UnusedAccounts::WARN_AFTER_DAYS);
        $removeBefore = now()->subDays(UnusedAccounts::REMOVE_AFTER_DAYS);
        $removed = [];

        // Organizations, with everyone in them.
        foreach (Organization::with('members')->get() as $org) {
            if (!$unused->organizationIsUntouched($org)) {
                continue;
            }
            $lastActive = $unused->organizationLastActive($org);

            if ($org->removal_warned_at && $lastActive->lte($removeBefore) && $org->removal_warned_at->lte(now()->subDays(6))) {
                $removed[] = "{$org->name} (organization, " . $org->members->pluck('email')->join(', ') . ')';
                if (!$dry) {
                    $unused->removeOrganization($org);
                }
            } elseif (!$org->removal_warned_at && $lastActive->lte($warnBefore)) {
                $this->line(($dry ? 'Would warn: ' : 'Warning: ') . $org->name);
                if (!$dry) {
                    Notification::send($org->members, new UnusedAccountWarning($this->removeOn($lastActive)));
                    $org->forceFill(['removal_warned_at' => now()])->saveQuietly();
                }
            }
        }

        // People who signed up and never set up an organization.
        foreach (User::whereNull('organization_id')->get() as $user) {
            if (!$unused->userIsUntouched($user)) {
                continue;
            }
            $lastActive = $unused->userLastActive($user);

            if ($user->removal_warned_at && $lastActive->lte($removeBefore) && $user->removal_warned_at->lte(now()->subDays(6))) {
                $removed[] = "{$user->email} (no organization)";
                if (!$dry) {
                    $unused->removeUser($user);
                }
            } elseif (!$user->removal_warned_at && $lastActive->lte($warnBefore)) {
                $this->line(($dry ? 'Would warn: ' : 'Warning: ') . $user->email);
                if (!$dry) {
                    $user->notify(new UnusedAccountWarning($this->removeOn($lastActive)));
                    $user->forceFill(['removal_warned_at' => now()])->saveQuietly();
                }
            }
        }

        foreach ($removed as $line) {
            $this->line(($dry ? 'Would remove: ' : 'Removed: ') . $line);
        }
        if ($removed && !$dry) {
            Log::info('Removed unused accounts', $removed);
            $this->tellVentiq($removed);
        }

        return self::SUCCESS;
    }

    /** Two months after they were last active, and never sooner than a week from today. */
    private function removeOn($lastActive)
    {
        return collect([$lastActive->copy()->addDays(UnusedAccounts::REMOVE_AFTER_DAYS), now()->addDays(7)])->max()->startOfDay();
    }

    private function tellVentiq(array $removed): void
    {
        $to = config('constants.ventiq_alerts.email');
        if (!$to) {
            return;
        }
        try {
            \Illuminate\Support\Facades\Mail::raw(
                "These unused accounts were removed today (nothing created, no activity for two months, warned a week ago):\n\n- " . implode("\n- ", $removed),
                fn ($m) => $m->to($to)->subject(count($removed) . ' unused VENTIQ account(s) removed')
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
