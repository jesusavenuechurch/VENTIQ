<?php

namespace Database\Seeders\Demo;

use App\Models\Client;
use App\Models\Event;
use App\Models\MemoryChunk;
use App\Models\Organization;
use App\Models\Participant;
use App\Models\Session;
use App\Models\User;
use App\Services\Sessions\CorporateBilling;
use App\Services\Sessions\SessionMaker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A session happening now, for a demo: the Quthing district health
 * committee, started 25 minutes ago. Its first item is done, the second
 * is being noted, and people have checked in. Made fresh each time, so it
 * is always "now" (`php artisan sessions:demo-live`).
 */
class LiveDemoSession
{
    public const TITLE = 'Quthing district health committee';

    private const ITEMS = [
        ['Welcome and apologies', 'Dr Lineo Ramoeletsi', [
            'welcome by the Chair, introductions round the table',
            'apologies: DHMT pharmacist, Mphaki clinic nurse',
            'agenda adopted without changes',
        ]],
        ['Rainy season preparedness', 'Teboho Mokhele', [
            'road to Mount Moorosi washed out again on Saturday',
            'a month of ORS and zinc pre-positioned at Tosing and Seforong',
            'Villa Maria still waiting, the truck could not cross the river',
            'council asked to grade the Mphaki road',
        ]],
        ['Stock report', 'Mamosa Qhobela', []],
        ['CHW recruitment for the Qacha road', 'Palesa Mokoena', []],
    ];

    /** Members and guests: [name, institution, position, how they checked in]. */
    private const PEOPLE = [
        ['Mamosa Qhobela', 'Quthing Hospital', 'District Supervisor', 'scanner'],
        ['Tlali Nthejane', 'Mount Moorosi Health Centre', 'Clinic Supervisor', 'self'],
        ['Rorisang Matela', 'Villa Maria Clinic', 'Clinic Supervisor', 'self'],
        ['Mamotšelisi Pitso', 'Tosing Clinic', 'Clinic Supervisor', 'scanner'],
        ['Thabiso Lesoli', 'Seforong Clinic', 'Clinic Supervisor', 'self'],
        ['Matšeliso Ntho', 'Ministry of Health', 'District Health Manager', 'walk_in'],
        ['Chief Lerotholi Mohale', 'Quthing community council', 'Councillor', 'walk_in'],
        ['Nkhabiseng Lethola', 'Mphaki Clinic', 'Clinic Supervisor', 'self'],
        ['Khotso Rantšo', 'Quthing Hospital', 'Data Clerk', 'scanner'],
        ['Puseletso Makhetha', 'Lesotho Red Cross, Quthing', 'Volunteer Coordinator', 'self'],
        ['Rev. Molapo Thaba', 'Quthing Methodist Church', 'Community representative', 'walk_in'],
    ];

    /** Remove the organization's live demo session, if there is one, and make it again. */
    public static function refresh(Organization $organization, ?User $by = null): Session
    {
        Session::withTrashed()->where('organization_id', $organization->id)->where('meta->demo_live', true)->get()
            ->each(function (Session $old) {
                MemoryChunk::where('session_id', $old->id)->delete();
                $event = $old->event_id;
                $old->forceDelete();
                $event && Event::whereKey($event)->where('kind', Event::KIND_SESSION)->get()->each(fn ($e) => \DB::table('events')->where('id', $e->id)->delete());
            });

        return self::make($organization, $by);
    }

    public static function make(Organization $organization, ?User $by = null): Session
    {
        $by ??= User::where('organization_id', $organization->id)->role('staff')->first() ?? User::where('organization_id', $organization->id)->first();
        $started = now()->subMinutes(25)->startOfMinute();

        $session = app(SessionMaker::class)->make($organization, $by, [
            'title' => self::TITLE, 'type' => 'committee', 'date' => $started->toDateString(), 'start_time' => $started->format('H:i'),
            'expected_duration' => 90, 'track_participants' => true, 'expected_participants' => 14,
            'location' => 'Quthing Hospital boardroom', 'chair' => 'Dr Lineo Ramoeletsi', 'secretary' => 'Thabo Lebona',
            'people' => collect(self::ITEMS)->map(fn ($item) => ['title' => $item[0], 'name' => $item[1]])->all(),
        ]);
        $session->forceFill([
            'status' => 'active', 'counted_at' => $started, 'created_at' => $started->copy()->subDays(6),
            'meta' => array_merge($session->meta ?? [], ['demo_live' => true]),
        ])->save();

        $billing = app(CorporateBilling::class);
        if ($agreement = $billing->agreement($organization)) {
            $billing->charge($session, $agreement);
        }

        // The first item done in 12 minutes, the second being noted since.
        $segments = $session->segments()->orderBy('order')->get();
        foreach ($segments as $i => $segment) {
            $began = $started->copy()->addMinutes($i === 0 ? 0 : 12);
            $notes = self::ITEMS[$i][2];
            $segment->forceFill([
                'status'     => match ($i) { 0 => 'completed', 1 => 'active', default => 'upcoming' },
                'started_at' => $i < 2 ? $began : null,
                'ended_at'   => $i === 0 ? $began->copy()->addMinutes(12) : null,
                'raw_log'    => $i < 2 ? collect($notes)->map(fn ($text, $n) => ['time' => $began->copy()->addMinutes(1 + $n * 3)->format('H:i:s'), 'text' => $text])->all() : null,
            ])->save();
        }
        // Ventiq Assist's sticky notes on the item being noted.
        foreach ([['theme', 'Access to Quthing clinics in the rains'], ['decision', 'Pre-position a month of supplies at every clinic beyond the river'], ['action', 'Ask the council to grade the Mphaki road']] as $n => [$category, $content]) {
            $segments[1]->insights()->create(['category' => $category, 'content' => $content, 'is_ai_generated' => true, 'display_order' => $n]);
        }

        foreach (self::PEOPLE as $n => [$name, $institution, $position, $how]) {
            $client = Client::firstOrCreate(
                ['organization_id' => $organization->id, 'email' => Str::slug(Str::ascii($name), '.') . '@example.com'],
                ['full_name' => $name, 'phone' => '+266 5' . str_pad((string) (crc32($name) % 10000000), 7, '0', STR_PAD_LEFT), 'status' => 'active'],
            );
            Participant::create([
                'organization_id' => $organization->id, 'event_id' => $session->event_id, 'session_id' => $session->id, 'client_id' => $client->id,
                'source' => $how, 'attended_at' => $started->copy()->subMinutes(10)->addMinutes($n * 3), 'institution' => $institution,
                'position' => $position, 'district' => 'quthing', 'signature_status' => 'pending',
            ]);
        }

        return $session->fresh();
    }
}
