<?php

namespace App\Jobs;

use App\Models\ActionPoint;
use App\Models\Participant;
use App\Models\Session;
use App\Models\User;
use App\Services\AI\AIService;
use App\Services\AI\Prompts\ActionPointsPrompt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * When a session's report is written: the AI proposes its action points
 * from the notes, matched to people on the team or attendees where it can.
 * They wait as "proposed" until someone confirms them on the report. The
 * model runs on VENTIQ's Mac mini; when it can't be reached, this tries
 * again later instead of giving up.
 */
class ExtractActionPoints implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 6;

    // One call to the AI with the whole meeting's notes.
    public int $timeout = 300;

    public function __construct(public readonly int $sessionId) {}

    /** @return int[] */
    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600];
    }

    public function handle(AIService $ai): void
    {
        $session = Session::with('segments')->find($this->sessionId);
        if (!$session) {
            return;
        }

        // An unreachable model throws: tried again later, and failed()
        // marks it after the last try.
        $this->extract($ai, $session);
        $this->mark($session, 'done');
    }

    public function failed(?\Throwable $e): void
    {
        if ($session = Session::find($this->sessionId)) {
            $this->mark($session, 'failed');
        }
    }

    private function mark(Session $session, string $state): void
    {
        $session->update(['meta' => array_merge($session->fresh()->meta ?? [], ['action_points' => $state])]);
    }

    private function extract(AIService $ai, Session $session): void
    {
        foreach ($this->propose($ai, $session) as $point) {
            ActionPoint::create($point);
        }
    }

    /**
     * The action points the AI finds in the session's notes, as they'd be
     * saved (proposed, owners matched), leaving out ones already there.
     * Throws when the AI can't be reached. Also used by sessions:assist-check.
     *
     * @return array<int, array<string, mixed>>
     */
    public function propose(AIService $ai, Session $session): array
    {
        $notes = $session->segments->map(function ($s) {
            $lines = $s->notesText();

            // A meeting's agenda item: the item, and who led it if anyone.
            $heading = $s->title && $s->presenter_name === $s->title ? $s->title : trim($s->presenter_name . ($s->title ? " ({$s->title})" : ''));

            return $lines === '' ? null : "## {$heading}\n" . $lines;
        })->filter()->implode("\n\n");
        if (trim($notes) === '') {
            return [];
        }

        $owners = $this->owners($session);
        $open = $session->series_id
            ? ActionPoint::where('series_id', $session->series_id)->where('status', 'open')->pluck('task')->map(fn ($t) => "- {$t}")->implode("\n")
            : '';

        $result = $ai->generate((new ActionPointsPrompt)->with([
            'title'  => $session->resolved_title,
            'date'   => $session->date?->format('l j F Y'),
            'people' => collect($owners)->pluck('name')->unique()->map(fn ($n) => "- {$n}")->implode("\n") ?: '- (none listed)',
            'notes'  => Str::limit($notes, 12000),
            'open'   => $open,
        ]));

        if (!$result->success) {
            throw new RuntimeException('Action points: the AI could not be reached: ' . $result->error);
        }

        $known = $session->actionPoints()->pluck('task')->map(fn ($t) => Str::lower(trim($t)))->all();
        $proposed = [];

        foreach (static::parse($result->content) as $point) {
            if (in_array(Str::lower($point['task']), $known, true)) {
                continue;
            }
            $owner = static::matchOwner($point['owner'], $owners);
            $proposed[] = [
                'organization_id' => $session->organization_id,
                'session_id'      => $session->id,
                'series_id'       => $session->series_id,
                'task'            => $point['task'],
                'owner_name'      => isset($owner['user_id']) || isset($owner['client_id']) ? null : ($owner['name'] ?? ($point['owner'] ?: null)),
                'owner_user_id'   => $owner['user_id'] ?? null,
                'owner_client_id' => $owner['client_id'] ?? null,
                'due_date'        => static::dueDate($point['due'], $session),
                'status'          => 'proposed',
                'source'          => 'ai',
            ];
            $known[] = Str::lower($point['task']);
        }

        return $proposed;
    }

    /** People who can own a point: the team, then the session's people and attendees. */
    private function owners(Session $session): array
    {
        $team = User::where('organization_id', $session->organization_id)->get(['id', 'name'])
            ->map(fn ($u) => ['name' => $u->name, 'user_id' => $u->id]);
        $attendees = Participant::where('session_id', $session->id)->with('client:id,full_name')->get()
            ->filter(fn ($p) => $p->client)
            ->map(fn ($p) => ['name' => $p->client->full_name, 'client_id' => $p->client->id]);
        // Who led each part; an agenda item no one led has no person.
        $leading = $session->segments->filter(fn ($s) => !$s->title || $s->lead())->map(fn ($s) => ['name' => $s->presenter_name]);
        $officers = collect([$session->meta['chair'] ?? null, $session->meta['secretary'] ?? null])->filter()->map(fn ($n) => ['name' => $n]);

        return $team->concat($attendees)->concat($leading)->concat($officers)->values()->all();
    }

    /** The model's answer as [{task, owner, due}], forgiving about code fences and stray text. */
    public static function parse(string $content): array
    {
        $start = strpos($content, '[');
        $end = strrpos($content, ']');
        if ($start === false || $end === false || $end < $start) {
            return [];
        }
        $items = json_decode(substr($content, $start, $end - $start + 1), true);
        if (!is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn ($i) => is_array($i) && trim((string) ($i['task'] ?? '')) !== '')
            ->map(fn ($i) => [
                'task'  => Str::limit(trim((string) $i['task']), 500, ''),
                'owner' => trim((string) ($i['owner'] ?? '')),
                'due'   => trim((string) ($i['due'] ?? '')),
            ])
            ->take(30)
            ->values()
            ->all();
    }

    /** The owner named by the model, if they're someone we know: full name, else first name. */
    public static function matchOwner(string $name, array $owners): ?array
    {
        if ($name === '') {
            return null;
        }
        $wanted = Str::lower($name);
        $known = array_filter($owners, fn ($o) => isset($o['user_id']) || isset($o['client_id']));

        foreach ($known as $o) {
            if (Str::lower($o['name']) === $wanted) {
                return $o;
            }
        }
        $first = Str::before($wanted, ' ');
        $byFirst = array_values(array_filter($known, fn ($o) => Str::before(Str::lower($o['name']), ' ') === $first));
        if ($byFirst) {
            // Only when the first name points to one person.
            return count($byFirst) === 1 ? $byFirst[0] : null;
        }

        // Someone named on the session (an item's lead, the chair) but not
        // on the team or checked in: their full name, from the first.
        $named = collect($owners)->reject(fn ($o) => isset($o['user_id']) || isset($o['client_id']))
            ->filter(fn ($o) => Str::lower($o['name']) === $wanted || Str::before(Str::lower($o['name']), ' ') === $first)
            ->unique('name')->values();

        return $named->count() === 1 ? ['name' => $named[0]['name']] : null;
    }

    /** A real date, not before the session (the model sometimes guesses the past). */
    private static function dueDate(string $due, Session $session): ?string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
            return null;
        }
        try {
            $date = Carbon::parse($due);
        } catch (\Throwable) {
            return null;
        }

        return $session->date && $date->lt($session->date) ? null : $date->toDateString();
    }
}
