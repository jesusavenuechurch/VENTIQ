<?php

namespace App\Services\Sessions;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one place a session is made, with its people and, when it tracks
 * who comes, the hidden event that holds its attendees (or its
 * programme's). Used when scheduling one session and by a series for each
 * of its meetings. Nothing is counted against the plan here: a session is
 * counted when it's held (SessionController::start()).
 */
class SessionMaker
{
    /**
     * @param array{title: string, type: string, custom_type_label?: ?string, date: string, start_time?: ?string,
     *   expected_duration?: ?int, track_participants?: bool, expected_participants?: ?int,
     *   people?: array<array{name: string, title?: ?string, role?: ?string, presenting?: bool}>, programme_id?: ?int,
     *   series_id?: ?int, parent_session_id?: ?int, chair?: ?string, secretary?: ?string, location?: ?string} $data
     */
    public function make(Organization $organization, ?User $by, array $data): Session
    {
        return DB::transaction(function () use ($organization, $by, $data) {
            $tracking = (bool) ($data['track_participants'] ?? false);

            $session = Session::create([
                'organization_id'   => $organization->id,
                'created_by'        => $by?->id,
                'series_id'         => $data['series_id'] ?? null,
                'parent_session_id' => $data['parent_session_id'] ?? null,
                'type'              => $data['type'],
                'title'             => $data['title'],
                'date'              => $data['date'],
                'start_time'        => $data['start_time'] ?? null,
                'location'          => $data['location'] ?? null,
                'status'            => 'draft',
                'meta'              => [
                    'expected_duration_minutes' => $data['expected_duration'] ?? null,
                    'expected_participants'     => $tracking ? ($data['expected_participants'] ?? null) : null,
                    'custom_type_label'         => $data['type'] === 'custom' ? ($data['custom_type_label'] ?? null) : null,
                ] + self::officers($data),
            ]);

            // Into a programme: its attendees are the programme's. Only one
            // of this organization's own programmes, whatever was posted.
            $programme = !empty($data['programme_id'])
                ? Event::where('id', $data['programme_id'])->where('organization_id', $organization->id)->where('is_programme', true)->first()
                : null;

            if ($programme) {
                $session->update(['event_id' => $programme->id]);
            } elseif ($tracking) {
                $event = Event::create([
                    'organization_id' => $organization->id,
                    'kind'            => Event::KIND_SESSION,
                    'name'            => $data['title'],
                    'event_date'      => trim("{$data['date']} " . ($data['start_time'] ?? '')),
                    'is_public'       => false,
                ]);
                $session->update(['event_id' => $event->id]);
            }

            if ($tracking) {
                $session->update(['public_token' => Str::random(32), 'session_code' => Session::generateSessionCode()]);
            }

            $this->setPeople($session, $data['people'] ?? []);

            return $session;
        });
    }

    /**
     * The session's people (or a meeting's agenda items), in order,
     * replacing any it had. Only for a session not yet held.
     */
    public function setPeople(Session $session, array $people): void
    {
        $session->segments()->delete();

        collect(self::cleanPeople($people))
            ->each(fn ($p, $i) => $session->segments()->create([
                'presenter_name' => $p['name'],
                'title'          => $p['title'],
                'role'           => $p['role'],
                'is_presenting'  => $p['presenting'],
                'order'          => $i,
                'status'         => 'upcoming',
            ]));
    }

    /**
     * People as stored on a series (and posted by the form), cleaned. A
     * meeting's agenda item is {title: the item, name: who leads it}; with
     * no one leading it, its name is the item.
     */
    public static function cleanPeople(array $people): array
    {
        return collect($people)
            ->map(fn ($p) => [
                'title'      => trim($p['title'] ?? '') ?: null,
                'name'       => trim($p['name'] ?? '') ?: trim($p['title'] ?? ''),
                'role'       => $p['role'] ?? null,
                'presenting' => filter_var($p['presenting'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ])
            ->filter(fn ($p) => $p['name'] !== '')
            ->values()
            ->all();
    }

    /** A meeting's chair and secretary, kept on the session's meta. */
    public static function officers(array $data): array
    {
        return [
            'chair'     => trim($data['chair'] ?? '') ?: null,
            'secretary' => trim($data['secretary'] ?? '') ?: null,
        ];
    }
}
