<?php

namespace App\Http\Controllers;

use App\Models\ActionPoint;
use App\Models\Participant;
use App\Models\Session;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Action points: every one across the organization, and confirming, changing and closing them. */
class ActionPointsController extends Controller
{
    public function index(Request $request)
    {
        $organizationId = Auth::user()->organization_id;
        $show = in_array($request->query('show'), ['open', 'overdue', 'mine', 'proposed', 'done'], true) ? $request->query('show') : 'open';

        $points = ActionPoint::where('organization_id', $organizationId)
            ->with(['session', 'ownerUser', 'ownerClient'])
            ->when($show === 'open', fn ($q) => $q->where('status', 'open'))
            ->when($show === 'overdue', fn ($q) => $q->where('status', 'open')->whereDate('due_date', '<', today()))
            ->when($show === 'mine', fn ($q) => $q->where('status', 'open')->where('owner_user_id', Auth::id()))
            ->when($show === 'proposed', fn ($q) => $q->where('status', 'proposed'))
            ->when($show === 'done', fn ($q) => $q->where('status', 'done'))
            ->orderByRaw($show === 'done' ? 'done_at desc' : 'due_date is null, due_date asc')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $count = fn ($q) => $q->where('organization_id', $organizationId)->count();

        return Inertia::render('Sessions/Actions', [
            'show'   => $show,
            'points' => $points->through(fn (ActionPoint $p) => $p->toCard()),
            'counts' => [
                'open'     => $count(ActionPoint::where('status', 'open')),
                'overdue'  => $count(ActionPoint::where('status', 'open')->whereDate('due_date', '<', today())),
                'mine'     => $count(ActionPoint::where('status', 'open')->where('owner_user_id', Auth::id())),
                'proposed' => $count(ActionPoint::where('status', 'proposed')),
            ],
            'owners' => static::owners(null),
            'meta'   => ['title' => 'Action points | VENTIQ Sessions'],
        ]);
    }

    /** One added by hand, to a session. */
    public function store(Request $request, Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 404);
        $data = $this->validated($request);

        ActionPoint::create($this->owner($data['owner'] ?? '', $data['owner_name'] ?? null) + [
            'organization_id' => $session->organization_id,
            'session_id'      => $session->id,
            'series_id'       => $session->series_id,
            'task'            => $data['task'],
            'due_date'        => $data['due_date'] ?? null,
            'status'          => 'open',
            'source'          => 'manual',
        ]);

        return back()->with('status', 'Action point added.');
    }

    /** The AI's proposals for a session, all taken as they are. */
    public function confirmAll(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 404);
        $count = $session->actionPoints()->where('status', 'proposed')->update(['status' => 'open']);

        return back()->with('status', $count === 1 ? '1 action point confirmed.' : "{$count} action points confirmed.");
    }

    /** Change it; confirming a proposed one too. */
    public function update(Request $request, ActionPoint $actionPoint)
    {
        $this->authorizePoint($actionPoint);
        $data = $this->validated($request);

        $actionPoint->update($this->owner($data['owner'] ?? '', $data['owner_name'] ?? null) + [
            'task'     => $data['task'],
            'due_date' => $data['due_date'] ?? null,
            'status'   => $actionPoint->status === 'proposed' ? 'open' : $actionPoint->status,
        ]);

        return back()->with('status', 'Saved.');
    }

    /** Done, in a meeting (session_id) or any time. */
    public function done(Request $request, ActionPoint $actionPoint)
    {
        $this->authorizePoint($actionPoint);
        $in = $request->integer('session_id') ?: null;
        if ($in) {
            abort_unless(Session::whereKey($in)->where('organization_id', $actionPoint->organization_id)->exists(), 404);
        }

        $actionPoint->update(['status' => 'done', 'done_at' => now(), 'done_in_session_id' => $in, 'done_by' => Auth::id()]);

        return $request->expectsJson() ? response()->json(['status' => 'ok']) : back()->with('status', 'Marked done.');
    }

    public function reopen(Request $request, ActionPoint $actionPoint)
    {
        $this->authorizePoint($actionPoint);
        $actionPoint->update(['status' => 'open', 'done_at' => null, 'done_in_session_id' => null, 'done_by' => null]);

        return $request->expectsJson() ? response()->json(['status' => 'ok']) : back()->with('status', 'Open again.');
    }

    /** A proposal that's wrong goes; a confirmed one is kept as dropped. */
    public function destroy(ActionPoint $actionPoint)
    {
        $this->authorizePoint($actionPoint);
        $actionPoint->status === 'proposed' ? $actionPoint->delete() : $actionPoint->update(['status' => 'dropped']);

        return back()->with('status', 'Action point removed.');
    }

    /**
     * Who can own a point: the team, and a session's attendees with an
     * email (so reminders reach them). Keyed "user:5" / "client:9".
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function owners(?Session $session): array
    {
        $team = User::where('organization_id', Auth::user()->organization_id)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($u) => ['value' => "user:{$u->id}", 'label' => $u->name]);
        $attendees = $session
            ? Participant::where('session_id', $session->id)->with('client:id,full_name,email')->get()
                ->filter(fn ($p) => $p->client?->email)
                ->map(fn ($p) => ['value' => "client:{$p->client->id}", 'label' => "{$p->client->full_name} (attended)"])
            : collect();

        return $team->concat($attendees)->values()->all();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'task'       => 'required|string|max:500',
            'owner'      => ['nullable', 'string', 'regex:/^(user|client):\d+$/'],
            'owner_name' => 'nullable|string|max:255',
            'due_date'   => 'nullable|date',
        ], ['task.required' => 'Say what is to be done.']);
    }

    /** "user:5" or "client:9" to its ids, only within this organization; else a typed name. */
    private function owner(string $key, ?string $name): array
    {
        $none = ['owner_user_id' => null, 'owner_client_id' => null, 'owner_name' => $name ? trim($name) : null];
        if (!preg_match('/^(user|client):(\d+)$/', $key, $m)) {
            return $none;
        }
        $organizationId = Auth::user()->organization_id;
        if ($m[1] === 'user' && User::whereKey($m[2])->where('organization_id', $organizationId)->exists()) {
            return ['owner_user_id' => (int) $m[2], 'owner_client_id' => null, 'owner_name' => null];
        }
        if ($m[1] === 'client' && \App\Models\Client::whereKey($m[2])->where('organization_id', $organizationId)->exists()) {
            return ['owner_user_id' => null, 'owner_client_id' => (int) $m[2], 'owner_name' => null];
        }

        return $none;
    }

    private function authorizePoint(ActionPoint $point): void
    {
        abort_unless($point->organization_id === Auth::user()->organization_id, 404);
    }
}
