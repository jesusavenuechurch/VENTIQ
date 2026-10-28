<?php

namespace App\Console\Commands;

use App\Mail\ActionPointsReminderMail;
use App\Models\ActionPoint;
use App\Services\SessionQuotaService;
use App\Services\WhatsAppCloudService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Reminds people of their open action points: two days before the due
 * date, on the day, and once a week while it's overdue. One message per
 * person, with everything due for them at once, on WhatsApp or by email:
 * team members choose on their account page; attendees get WhatsApp when
 * we have their number. Whatever WhatsApp can't deliver goes by email.
 */
class SendActionPointReminders extends Command
{
    protected $signature = 'sessions:action-reminders';

    protected $description = 'Remind people of their action points that are due soon or overdue, on WhatsApp or by email';

    public function handle(SessionQuotaService $quota, WhatsAppCloudService $whatsapp): int
    {
        $today = today();
        $due = ActionPoint::where('status', 'open')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today->copy()->addDays(2))
            ->with(['session', 'ownerUser', 'ownerClient', 'session.organization'])
            ->get()
            ->filter(fn (ActionPoint $p) => ($p->ownerEmail() || $p->ownerWhatsApp()) && static::isDue($p));

        $sent = 0;
        $owner = fn (ActionPoint $p) => ($p->owner_user_id ? "user:{$p->owner_user_id}" : "client:{$p->owner_client_id}") . '|' . $p->organization_id;
        foreach ($due->groupBy($owner) as $points) {
            $first = $points->first();
            $organization = $first->session?->organization;
            $orgName = $organization?->name ?? 'VENTIQ';
            $items = $points->map(fn (ActionPoint $p) => [
                'task'    => $p->task,
                'when'    => $p->due_date->isToday() ? 'due today' : ($p->isOverdue() ? 'due ' . $p->due_date->format('j M') : 'due ' . $p->due_date->format('D j M')),
                'overdue' => $p->isOverdue(),
                'from'    => trim(($p->session?->resolved_title ?? 'a session') . ($p->session?->date ? ', ' . $p->session->date->format('j M') : '')),
            ])->values()->all();

            $delivered = false;
            if (($phone = $first->ownerWhatsApp()) && $organization && $quota->hasWhatsappQuota($organization)) {
                $delivered = $whatsapp->sendActionReminder($phone, $first->ownerLabel() ?? 'there', $orgName, static::oneLine($items));
                if ($delivered) {
                    $quota->consumeWhatsapp($organization);
                }
            }
            if (!$delivered && $first->ownerEmail()) {
                // Team members can open the list; attendees just get the email.
                $link = $first->owner_user_id ? route('sessions.actions.index', ['show' => 'mine']) : null;
                Mail::to($first->ownerEmail())->send(new ActionPointsReminderMail($first->ownerLabel() ?? 'there', $orgName, $items, $link));
                $delivered = true;
            }
            if ($delivered) {
                ActionPoint::whereIn('id', $points->pluck('id'))->update(['reminded_at' => now()]);
                $sent++;
            }
        }

        $this->info("{$sent} reminders sent.");

        return self::SUCCESS;
    }

    /**
     * The points as one line for the WhatsApp template (it takes no line
     * breaks): "Send the quotes (due today); Book the hall (overdue, due 12 Oct)".
     */
    public static function oneLine(array $items): string
    {
        $parts = array_map(fn ($i) => trim(preg_replace('/\s+/', ' ', $i['task'])) . ' (' . ($i['overdue'] ? 'overdue, ' : '') . $i['when'] . ')', $items);
        $line = '';
        foreach ($parts as $n => $part) {
            $next = $line === '' ? $part : "{$line}; {$part}";
            if (mb_strlen($next) > 700) {
                $rest = count($parts) - $n - ($line === '' ? 1 : 0);
                $line = $line === '' ? mb_substr($part, 0, 690) . '…' : $line;

                return $rest > 0 ? "{$line}; and {$rest} more" : $line;
            }
            $line = $next;
        }

        return $line;
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
