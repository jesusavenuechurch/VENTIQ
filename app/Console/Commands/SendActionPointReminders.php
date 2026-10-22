<?php

namespace App\Console\Commands;

use App\Mail\ActionPointsReminderMail;
use App\Models\ActionPoint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Reminds people of their open action points: two days before the due
 * date, on the day, and once a week while it's overdue. One email per
 * person, with everything due for them at once.
 */
class SendActionPointReminders extends Command
{
    protected $signature = 'sessions:action-reminders';

    protected $description = 'Email people their action points that are due soon or overdue';

    public function handle(): int
    {
        $today = today();
        $due = ActionPoint::where('status', 'open')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today->copy()->addDays(2))
            ->with(['session', 'ownerUser', 'ownerClient', 'session.organization'])
            ->get()
            ->filter(fn (ActionPoint $p) => $p->ownerEmail() && static::isDue($p));

        $sent = 0;
        foreach ($due->groupBy(fn ($p) => strtolower($p->ownerEmail()) . '|' . $p->organization_id) as $points) {
            $first = $points->first();
            $organization = $first->session?->organization?->name ?? 'VENTIQ';
            // Team members can open the list; attendees just get the email.
            $link = $first->owner_user_id ? route('sessions.actions.index', ['show' => 'mine']) : null;

            Mail::to($first->ownerEmail())->send(new ActionPointsReminderMail(
                $first->ownerLabel() ?? 'there',
                $organization,
                $points->map(fn (ActionPoint $p) => [
                    'task'    => $p->task,
                    'when'    => $p->due_date->isToday() ? 'due today' : ($p->isOverdue() ? 'due ' . $p->due_date->format('j M') : 'due ' . $p->due_date->format('D j M')),
                    'overdue' => $p->isOverdue(),
                    'from'    => trim(($p->session?->resolved_title ?? 'a session') . ($p->session?->date ? ', ' . $p->session->date->format('j M') : '')),
                ])->values()->all(),
                $link,
            ));
            ActionPoint::whereIn('id', $points->pluck('id'))->update(['reminded_at' => now()]);
            $sent++;
        }

        $this->info("{$sent} reminders sent.");

        return self::SUCCESS;
    }

    /** Two days before, on the day, and weekly once overdue; never twice in a day. */
    public static function isDue(ActionPoint $point): bool
    {
        if ($point->reminded_at?->isToday()) {
            return false;
        }
        $days = (int) today()->diffInDays($point->due_date, false);

        if ($days === 2 || $days === 0) {
            return true;
        }

        return $days < 0 && (!$point->reminded_at || $point->reminded_at->lte(now()->subDays(7)));
    }
}
