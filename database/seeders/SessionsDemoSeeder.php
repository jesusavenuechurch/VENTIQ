<?php

namespace Database\Seeders;

use App\Jobs\EmbedMemoryChunks;
use App\Jobs\ExtractActionPoints;
use App\Jobs\GenerateSessionReport;
use App\Jobs\IndexSessionMemory;
use App\Jobs\IssueProgrammeCertificates;
use App\Models\ActionPoint;
use App\Models\CertificateDesign;
use App\Models\Client;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Participant;
use App\Models\Session;
use App\Models\SessionAgreement;
use App\Models\SessionInvoice;
use App\Models\SessionPackage;
use App\Models\SessionSeries;
use App\Models\User;
use App\Services\Memory\MemoryIndexer;
use App\Services\Sessions\CorporateBilling;
use App\Services\Sessions\ProgrammeProgress;
use App\Services\Sessions\SeriesScheduler;
use App\Services\Sessions\SessionMaker;
use App\Support\SessionType;
use App\Support\Signature;
use Database\Seeders\Demo\SessionsDemoStory as Story;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A demo of everything in Sessions, from both ends: what goes in (a
 * meeting to run today, a programme open for enrolment, check-in) and
 * what comes out (six months of meetings with notes, minutes, decisions
 * and action points for Ask Ventiq to answer from, a held programme with
 * certificates, participant lists, and a corporate rate's invoices).
 *
 *   php artisan db:seed --class=SessionsDemoSeeder
 *
 * Two organizations:
 * - Maluti Community Health Trust, an NGO on a corporate rate, with a
 *   team of four (Admin, Staff, a Viewer who signs certificates, a Door
 *   scanner).
 * - Thaba Bosiu Youth Network, on the Team plan with its sessions used
 *   up and one pay-as-you-go session left.
 *
 * Run again, it starts over: the demo organizations and users are
 * deleted and made again. Nothing is emailed or sent to Khoebo, and Ask
 * Ventiq's passages are made but not embedded: run
 * `php artisan sessions:index-memory` when the Mac mini is on.
 */
class SessionsDemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo-Pass-2026';

    private const ORG_EMAILS = ['office@maluti-health.example.org', 'hello@thababosiu-youth.example.org'];

    private const USER_EMAILS = [
        'demo.admin@example.com', 'demo.staff@example.com', 'demo.viewer@example.com',
        'demo.door@example.com', 'demo.team@example.com', 'demo.ventiq@example.com',
    ];

    /** Session ids in the order they were held, to price under the corporate rate at the end. */
    private array $held = [];

    private Organization $org;
    private array $team = [];
    private Carbon $monday;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Not in production: this seeder deletes and makes demo organizations.');

            return;
        }

        mt_srand(2026);
        // Nothing leaves: no email, no Khoebo, and the jobs the models start are done here instead.
        Mail::fake();
        Notification::fake();
        Bus::fake([IndexSessionMemory::class, IssueProgrammeCertificates::class, EmbedMemoryChunks::class, ExtractActionPoints::class, GenerateSessionReport::class]);
        config(['services.khoebo.url' => null, 'services.khoebo.token' => null]);

        if (!\Spatie\Permission\Models\Role::where('name', 'staff')->exists()) {
            $this->call(RolesAndPermissionsSeeder::class);
        }

        $this->clean();
        // This week's Monday; week 0 is 25 weeks before it.
        $this->monday = today()->startOfWeek(Carbon::MONDAY)->subWeeks(25);

        $this->trust();
        $this->weeklyMeetings();
        $this->boardMeetings();
        $this->workshop();
        $this->interviews();
        $this->chwTraining();
        $this->donorBriefing();
        $this->refresher();
        $this->partnersForum();
        $this->corporateBilling();
        $this->youthNetwork();
        $this->remember();

        $this->report();
    }

    // ── The organizations and people ─────────────────────────────────────

    private function clean(): void
    {
        $orgs = Organization::whereIn('email', self::ORG_EMAILS)->pluck('id');
        foreach ($orgs as $id) {
            Storage::disk('public')->deleteDirectory("signatures/programmes/{$id}");
            // Money records are kept from deletes by the app; a demo's go with it.
            SessionInvoice::where('organization_id', $id)->delete();
            Event::where('organization_id', $id)->pluck('id')->each(fn ($e) => \DB::table('events')->where('id', $e)->delete());
            \DB::table('organizations')->where('id', $id)->delete();
        }
        User::whereIn('email', self::USER_EMAILS)->get()->each(function (User $u) {
            $u->syncRoles([]);
            $u->delete();
        });
    }

    private function trust(): void
    {
        $this->org = Organization::create([
            'name'          => 'Maluti Community Health Trust',
            'email'         => self::ORG_EMAILS[0],
            'contact_email' => self::ORG_EMAILS[0],
            'phone'         => '+266 2231 0000',
            'description'   => 'Community health worker programmes in Mafeteng and Quthing.',
            'slug'          => 'maluti-community-health-trust',
            'is_active'     => true,
            'logo_path'     => $this->logo('logos/demo/maluti-community-health-trust.png', [31, 95, 74], 'MCHT'),
        ]);

        $this->team = [
            'palesa' => $this->user('demo.admin@example.com', 'Palesa Mokoena', 'org_admin', 'Programmes Manager'),
            'thabo'  => $this->user('demo.staff@example.com', 'Thabo Lebona', 'staff', 'Programme Coordinator'),
            'lineo'  => $this->user('demo.viewer@example.com', 'Dr Lineo Ramoeletsi', 'viewer', 'Executive Director'),
            'door'   => $this->user('demo.door@example.com', 'Tumelo Phafoli', 'scanner', null),
        ];

        $super = User::create(['name' => 'VENTIQ Billing (demo)', 'email' => 'demo.ventiq@example.com', 'password' => Hash::make(self::PASSWORD), 'email_verified_at' => now()]);
        $super->assignRole('super_admin');
        $this->team['super'] = $super;

        CertificateDesign::create([
            'organization_id' => $this->org->id,
            'layout'          => 'classic',
            'title'           => 'Certificate',
            'subtitle'        => 'of attendance',
            'wording'         => 'completed {days} of {programme}, hosted by {organization} at {venue} on {dates}.',
            'primary_color'   => '#1F5F4A',
            'accent_color'    => '#C8963E',
            'show_logo'       => true,
            'signatories'     => [$this->team['lineo']->id, $this->team['palesa']->id],
        ]);
    }

    /** A team member; those with a title sign certificates, with a drawn signature. */
    private function user(string $email, string $name, string $role, ?string $title): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make(self::PASSWORD), 'organization_id' => $this->org->id, 'email_verified_at' => now()]);
        $user->assignRole($role);
        if ($title) {
            $user->forceFill([
                'can_sign_certificates' => true,
                'signature_title'       => $title,
                'signature_path'        => Signature::store($this->scribble(), "signatures/users/{$user->id}"),
                'signature_updated_at'  => now(),
            ])->save();
        }

        return $user;
    }

    // ── The weekly management meeting: six months of it ──────────────────

    private function weeklyMeetings(): void
    {
        $agenda = fn (int $week) => collect(Story::ITEMS)
            ->reject(fn ($item, $key) => $key === 'me' && $week < Story::ME_FROM)
            ->map(fn ($item) => ['title' => $item[0], 'name' => $item[1]])->values()->all();

        $series = SessionSeries::create([
            'organization_id' => $this->org->id,
            'created_by'      => $this->team['thabo']->id,
            'title'           => 'Weekly management meeting',
            'type'            => 'meeting',
            'start_time'      => '09:00',
            'expected_duration' => 60,
            'people'          => SessionMaker::cleanPeople($agenda(25)),
            'meta'            => ['chair' => 'Dr Lineo Ramoeletsi', 'secretary' => 'Thabo Lebona', 'location' => 'Boardroom, Maseru office'],
            'frequency'       => 'weekly',
            'starts_on'       => $this->monday->toDateString(),
        ]);

        $meetings = [];
        $previous = null;
        foreach (Story::WEEKS as $week => $story) {
            $date = $this->week($week);
            $session = $this->at($date->copy()->setTime(8, 0), fn () => app(SessionMaker::class)->make($this->org, $this->team['thabo'], [
                'title' => $series->title, 'type' => 'meeting', 'date' => $date->toDateString(), 'start_time' => '09:00',
                'expected_duration' => 60, 'people' => $agenda($week),
                'chair' => 'Dr Lineo Ramoeletsi', 'secretary' => 'Thabo Lebona', 'location' => 'Boardroom, Maseru office',
                'series_id' => $series->id, 'parent_session_id' => $previous?->id,
            ]));

            $items = $session->segments()->orderBy('order')->get()->map(function ($segment) use ($story) {
                $key = collect(Story::ITEMS)->search(fn ($item) => $item[0] === $segment->title);
                [$notes, $discussion, $decisions] = $story[$key] ?? [...Story::ROUTINE[$key], []];

                return [$segment, $notes, ['summary' => $discussion, 'decisions' => $this->bullets($decisions), 'action_items' => 'None identified.', 'follow_ups' => 'None identified.']];
            })->all();

            $last = $week === array_key_last(Story::WEEKS);
            $this->hold($session, $date->copy()->setTime(9, 0), $items, 12, $last ? null : [
                'by' => $week % 3 === 0 ? 'Dr Lineo Ramoeletsi' : 'Thabo Lebona',
                'at' => $date->copy()->addDay()->setTime(15, 30),
            ]);

            $minutes = array_filter([
                'apologies' => $story['apologies'] ?? null,
                'aob'       => $story['aob'] ?? null,
            ]);
            if ($minutes) {
                $session->forceFill(['minutes' => $minutes])->save();
            }

            $meetings[$week] = $session;
            $previous = $session;
        }
        $series->forceFill(['scheduled_until' => $this->week(25)->toDateString()])->save();

        // The action points: agreed at a meeting, done at a later one (or still open).
        foreach (Story::WEEKS as $week => $story) {
            $session = $meetings[$week];
            foreach ($story['actions'] ?? [] as $point) {
                $done = $point['done'] ?? null;
                $this->at($session->date->copy()->setTime(10, 0), fn () => ActionPoint::create($this->owner($point[1]) + [
                    'organization_id'    => $this->org->id,
                    'session_id'         => $session->id,
                    'series_id'          => $series->id,
                    'task'               => $point[0],
                    'due_date'           => $session->date->copy()->addDays($point[2])->toDateString(),
                    'status'             => $done !== null ? 'done' : 'open',
                    'source'             => 'ai',
                    'done_at'            => $done !== null ? $meetings[$done]->date->copy()->setTime(9, 20) : null,
                    'done_in_session_id' => $done !== null ? $meetings[$done]->id : null,
                    'done_by'            => $done !== null ? $this->team['thabo']->id : null,
                ]));
            }
            // This week's, waiting for someone to confirm them on the report.
            foreach ($story['proposed'] ?? [] as $point) {
                ActionPoint::create($this->owner($point[1]) + [
                    'organization_id' => $this->org->id, 'session_id' => $session->id, 'series_id' => $series->id,
                    'task' => $point[0], 'due_date' => $session->date->copy()->addDays($point[2])->toDateString(),
                    'status' => 'proposed', 'source' => 'ai',
                ]);
            }
        }

        // The next four, on the desk.
        app(SeriesScheduler::class)->topUp($series->fresh(), $this->team['thabo']);
    }

    private function boardMeetings(): void
    {
        $weeks = [1, 6, 8, 15, 19, 24];
        foreach (Story::BOARD as $n => $board) {
            $date = $this->week($weeks[$n])->addDays(3);
            $decisions = array_pop($board);
            $leads = ['Dr Lineo Ramoeletsi', 'Nthabiseng Letsie', 'Palesa Mokoena', 'Dr Lineo Ramoeletsi'];
            $session = $this->at($date->copy()->setTime(13, 0), fn () => app(SessionMaker::class)->make($this->org, $this->team['palesa'], [
                'title' => 'Board of Trustees', 'type' => 'board', 'date' => $date->toDateString(), 'start_time' => '14:00', 'expected_duration' => 120,
                'people' => collect($board)->map(fn ($item, $i) => ['title' => $item[0], 'name' => $leads[$i]])->all(),
                'chair' => 'Mokete Sekhesa', 'secretary' => 'Thabo Lebona', 'location' => 'Boardroom, Maseru office',
            ]));
            $items = $session->segments()->orderBy('order')->get()->map(fn ($segment, $i) => [
                $segment,
                preg_split('/(?<=\.)\s+/', $board[$i][1]),
                ['summary' => $board[$i][1], 'decisions' => $this->bullets($i === 0 ? $decisions : []), 'action_items' => 'None identified.', 'follow_ups' => 'None identified.'],
            ])->all();
            $this->hold($session, $date->copy()->setTime(14, 0), $items, 25, ['by' => 'Palesa Mokoena', 'at' => $date->copy()->addDays(4)->setTime(11, 0)]);
        }
    }

    // ── Other sessions ───────────────────────────────────────────────────

    private function workshop(): void
    {
        $date = $this->week(5)->addDays(2);
        $session = $this->tracked('Data quality workshop', 'workshop', $date, '09:00', [
            ['Why data quality matters', 'Thabo Lebona', ['donor reports depend on registers', 'examples of double counting from Q1'],
                'Why accurate CHW registers matter for donor reporting and for planning, with examples of double counting from the first quarter.',
                ['Registers feed every donor report.', 'Double counting inflated home visits by about 12% in Q1.']],
            ['Common errors in CHW registers', 'Thabo Lebona', ['missing dates', 'children counted twice across visits', 'referrals not closed'],
                'The most common register errors: missing visit dates, children counted again on each visit, and referrals never marked as closed.',
                ['Write the date on every line.', 'Count a child once per month.', 'Close every referral.']],
            ['A monthly data review routine', 'Palesa Mokoena', ['supervisors check registers monthly', 'simple checklist', 'register template too long'],
                'Supervisors agreed a monthly review of each CHW\'s register with a short checklist, and asked for a simpler register template.',
                ['A monthly review with a five-point checklist.', 'A shorter register template.']],
        ], 14, 'key_points');
        $this->attend($session, array_merge(array_slice(Story::SUPERVISORS, 0, 7), array_slice(Story::TRAINEES, 0, 7)), ['self', 'self', 'walk_in', 'self']);
    }

    private function interviews(): void
    {
        $date = $this->week(6)->addDays(3);
        $candidates = [
            ['Mpho Sello', ['5 yrs MoH M&E unit', 'strong DHIS2, built district dashboards', 'good on data quality, gave examples', 'available in 2 weeks'],
                'Mpho Sello has five years in the Ministry of Health M&E unit and strong DHIS2 skills, and gave concrete examples of improving data quality. Available in two weeks.',
                ['Built district DHIS2 dashboards.', 'Clear on data quality routines.']],
            ['Karabo Tau', ['3 yrs with an NGO in Leribe', 'Excel strong, DHIS2 basic', 'salary expectation above range'],
                'Karabo Tau has three years with an NGO in Leribe, strong Excel skills and basic DHIS2. Salary expectation above the advertised range.',
                ['Strong with spreadsheets.', 'Would need DHIS2 training.']],
        ];
        $session = $this->at($date->copy()->setTime(9, 0), fn () => app(SessionMaker::class)->make($this->org, $this->team['palesa'], [
            'title' => 'Interviews: M&E Officer', 'type' => 'interview', 'date' => $date->toDateString(), 'start_time' => '10:00', 'location' => 'Boardroom, Maseru office',
            'people' => array_merge(
                collect($candidates)->map(fn ($c) => ['name' => 'Palesa Mokoena', 'title' => "Candidate: {$c[0]}", 'role' => 'interviewer'])->all(),
                [['name' => 'Dr Lineo Ramoeletsi', 'role' => 'panelist', 'presenting' => false], ['name' => 'Thabo Lebona', 'role' => 'panelist', 'presenting' => false]],
            ),
        ]));
        $items = $session->segments()->orderBy('order')->get()->filter->is_presenting->values()->map(fn ($segment, $i) => [
            $segment, $candidates[$i][1],
            ['summary' => $candidates[$i][2], 'key_points' => $this->bullets($candidates[$i][3]), 'follow_ups' => $i === 0 ? '• Check references.' : 'None identified.'],
        ])->all();
        $this->hold($session, $date->copy()->setTime(10, 0), $items, 40, ['by' => 'Palesa Mokoena', 'at' => $date->copy()->setTime(16, 0)]);
    }

    private function chwTraining(): void
    {
        $first = $this->week(17)->addDay();
        $programme = Event::create([
            'organization_id' => $this->org->id, 'name' => 'Community Health Worker Training, Mafeteng', 'is_programme' => true, 'is_public' => false,
            'venue' => 'Mafeteng Community Hall', 'city' => 'Mafeteng', 'event_date' => $first->copy()->setTime(8, 30),
            'certificates_enabled' => true, 'certificate_min_days' => 2, 'enrolment_open' => false,
            'description' => 'Three days on danger signs in children, ORS and zinc, referrals and the new registers.',
        ]);
        $modules = [
            [['Danger signs in children', 'Palesa Mokoena', ['fast breathing, chest indrawing', 'unable to drink, convulsions', 'refer the same day'],
                'Recognising danger signs in children under five, and when to refer the same day.', ['Fast breathing and chest indrawing mean pneumonia.', 'Any general danger sign: refer the same day.']],
             ['ORS and zinc', 'Thabo Lebona', ['mix ORS correctly, 1 litre', 'zinc for 10 days', 'practice in pairs'],
                'Preparing ORS and giving zinc for diarrhoea, practised in pairs.', ['One sachet to one litre of clean water.', 'Zinc for ten days, even when diarrhoea stops.']]],
            [['Referrals that close', 'Palesa Mokoena', ['referral slip', 'follow up within 3 days', 'record the outcome'],
                'Using the referral slip and following each referral up within three days.', ['Every referral gets a slip.', 'Follow up within three days and write down the outcome.']],
             ['Home visit practice', 'Thabo Lebona', ['role play in groups of 4', 'counselling the caregiver'],
                'Role play of a home visit, counselling the caregiver.', ['Ask, look, then counsel.']]],
            [['The new CHW register', 'Mpho Sello', ['walk through each column', 'count a child once per month', 'monthly review with supervisor'],
                'The simplified register, column by column, and the monthly review with supervisors.', ['Date every line.', 'Count a child once per month.']],
             ['Review and evaluation', 'Mpho Sello', ['post-test average 82%, pre-test 61%', 'participants want more practice on registers'],
                'Post-test and evaluation: the average score rose from 61% to 82%.', ['Average score up from 61% to 82%.']]],
        ];

        $days = collect([0, 1, 2])->map(fn ($d) => $this->at($first->copy()->addDays($d)->setTime(7, 0), fn () => app(SessionMaker::class)->make($this->org, $this->team['thabo'], [
            'title' => 'Community Health Worker Training, Mafeteng', 'type' => 'training', 'date' => $first->copy()->addDays($d)->toDateString(), 'start_time' => '08:30',
            'track_participants' => true, 'expected_participants' => 24, 'location' => 'Mafeteng Community Hall', 'programme_id' => $programme->id,
            'people' => collect($modules[$d])->map(fn ($m) => ['title' => $m[0], 'name' => $m[1], 'role' => 'facilitator'])->all(),
        ])));

        $progress = app(ProgrammeProgress::class);
        $people = collect(Story::TRAINEES)->map(fn ($t, $i) => $this->at($first->copy()->subDays(20 - $i % 10), fn () => $progress->enrol($programme, [
            'full_name' => $t[0], 'email' => $this->email($t[0]), 'phone' => $this->phone($t[0]), 'institution' => $t[1], 'position' => $t[2],
        ], $i % 3 === 0 ? 'self' : 'desk'))->client);

        // 18 every day, 3 two days, 3 one day.
        $came = fn (int $i) => match (true) { $i < 18 => [0, 1, 2], $i < 21 => [0, 2], default => [1] };
        foreach ($people as $i => $client) {
            $t = Story::TRAINEES[$i];
            foreach ($came($i) as $d) {
                $how = ['scanner', 'self', 'walk_in'][($i + $d) % 3];
                $participant = Participant::create([
                    'organization_id' => $this->org->id, 'event_id' => $programme->id, 'session_id' => $days[$d]->id, 'client_id' => $client->id,
                    'source' => $how, 'attended_at' => $days[$d]->date->copy()->setTime(8, 5 + ($i * 2) % 50),
                    'institution' => $t[1], 'position' => $t[2], 'district' => $t[3], 'signature_status' => 'pending',
                ]);
                if ($how === 'scanner') {
                    $this->at($participant->attended_at, fn () => $participant->storeSignature($this->scribble(), $this->team['door']->id, 'VENTIQ Scanner'));
                }
            }
        }

        foreach ($days as $d => $day) {
            $items = $day->segments()->orderBy('order')->get()->map(fn ($segment, $i) => [
                $segment, $modules[$d][$i][2],
                ['summary' => $modules[$d][$i][3], 'key_learning_points' => $this->bullets($modules[$d][$i][4]), 'follow_ups' => 'None identified.'],
            ])->all();
            $this->hold($day, $day->date->copy()->setTime(8, 30), $items, 150, ['by' => 'Palesa Mokoena', 'at' => $day->date->copy()->setTime(17, 0)]);
        }

        // The last day ended: certificates for everyone who came to two.
        $this->at($days[2]->date->copy()->setTime(18, 50), fn () => $progress->issueDue($programme->fresh()));
    }

    private function donorBriefing(): void
    {
        $date = $this->week(23)->addDays(2);
        $session = $this->tracked('Donor field visit: programme briefing', 'presentation', $date, '08:00', [
            ['Where the programme stands', 'Dr Lineo Ramoeletsi', ['3 districts, 96 CHWs', 'second vehicle since Q2', 'weekly deliveries to Quthing'],
                'An overview of the programme in three districts with 96 CHWs, and what the second vehicle changed: weekly deliveries to Quthing.',
                ['Quthing clinics now get weekly deliveries.', 'No ORS stockouts since the buffer was set up.']],
            ['Results so far', 'Mpho Sello', ['home visits up 18% on Q1', 'reporting rate 91% Mafeteng', 'post-test 61% to 82%'],
                'Home visits up 18% on the first quarter, the Mafeteng reporting rate up to 91%, and training scores up from 61% to 82%.',
                ['Home visits up 18%.', 'Reporting rate 91% in Mafeteng.']],
            ['Next year', 'Palesa Mokoena', ['refresher for Quthing supervisors', 'Qacha\'s Nek interest', '2027 budget'],
                'Plans for next year: a refresher for Quthing supervisors, interest in Qacha\'s Nek, and the 2027 budget.',
                ['A concept note on Qacha\'s Nek to follow.']],
        ], 9, 'key_points');
        $this->attend($session, [
            ['Sarah Whitfield', 'Global Child Health Fund', 'Programme Officer', 'other'],
            ['Daniel Okafor', 'Global Child Health Fund', 'Finance Officer', 'other'],
            ['Matšeliso Ntho', 'Ministry of Health', 'District Health Manager', 'quthing'],
            ['Mamosa Qhobela', 'Quthing Hospital', 'District Supervisor', 'quthing'],
            ['Tlali Nthejane', 'Mount Moorosi Health Centre', 'Clinic Supervisor', 'quthing'],
        ], ['walk_in', 'self']);
    }

    /** Next month's programme: open for enrolment, with some people on it already. */
    private function refresher(): void
    {
        $first = today()->addWeeks(3)->next(Carbon::TUESDAY);
        $programme = Event::create([
            'organization_id' => $this->org->id, 'name' => 'Supervisors Refresher Training, Quthing', 'is_programme' => true, 'is_public' => false,
            'venue' => 'Quthing Hospital training room', 'city' => 'Quthing', 'event_date' => $first->copy()->setTime(9, 0),
            'certificates_enabled' => true, 'enrolment_open' => true,
            'description' => 'Three days for clinic supervisors: data review, supportive supervision and stock management.',
        ]);
        foreach ([['Data review with DHIS2', 'Mpho Sello'], ['Supportive supervision', 'Palesa Mokoena'], ['Stock management', 'Teboho Mokhele']] as $d => [$module, $lead]) {
            app(SessionMaker::class)->make($this->org, $this->team['thabo'], [
                'title' => 'Supervisors Refresher Training, Quthing', 'type' => 'training', 'date' => $first->copy()->addDays($d)->toDateString(), 'start_time' => '09:00',
                'track_participants' => true, 'expected_participants' => 12, 'location' => 'Quthing Hospital training room', 'programme_id' => $programme->id,
                'people' => [['title' => $module, 'name' => $lead, 'role' => 'facilitator']],
            ]);
        }
        $progress = app(ProgrammeProgress::class);
        $progress->link($programme);
        foreach (Story::SUPERVISORS as $i => $s) {
            $progress->enrol($programme, ['full_name' => $s[0], 'email' => $this->email($s[0]), 'phone' => $this->phone($s[0]), 'institution' => $s[1], 'position' => $s[2]], $i < 4 ? 'self' : 'desk');
        }
    }

    /** Today, not started: to run the console, check people in and see a report come out. */
    private function partnersForum(): void
    {
        $session = app(SessionMaker::class)->make($this->org, $this->team['palesa'], [
            'title' => 'Quarterly partners\' forum', 'type' => 'presentation', 'date' => today()->toDateString(), 'start_time' => '14:00',
            'expected_duration' => 90, 'track_participants' => true, 'expected_participants' => 20, 'location' => 'Lancers Inn, Maseru',
            'people' => [
                ['name' => 'Dr Lineo Ramoeletsi', 'title' => 'Welcome and the year so far', 'role' => 'presenter'],
                ['name' => 'Mpho Sello', 'title' => 'Q3 results', 'role' => 'presenter'],
                ['name' => 'Palesa Mokoena', 'title' => 'Extending to Qacha\'s Nek', 'role' => 'presenter'],
                ['name' => 'Thabo Lebona', 'role' => 'judge', 'presenting' => false],
            ],
        ]);
        // Two people registered ahead; the rest check in at the door.
        foreach ([['Matšeliso Ntho', 'Ministry of Health', 'District Health Manager', 'quthing'], ['Sarah Whitfield', 'Global Child Health Fund', 'Programme Officer', 'other']] as $i => $p) {
            Participant::create([
                'organization_id' => $this->org->id, 'event_id' => $session->event_id, 'session_id' => $session->id, 'client_id' => $this->client($p[0])->id,
                'source' => 'pre_registered', 'institution' => $p[1], 'position' => $p[2], 'district' => $p[3], 'signature_status' => 'pending',
            ]);
        }
    }

    // ── Money: the Trust on a corporate rate, the Youth Network on a plan ──

    private function corporateBilling(): void
    {
        $billing = app(CorporateBilling::class);
        $agreement = SessionAgreement::create([
            'organization_id' => $this->org->id, 'rate' => 450, 'free_per_month' => 1, 'billing_email' => 'accounts@maluti-health.example.org',
            'notes' => 'Agreed with Dr Ramoeletsi: M450 a session, the first each month free.',
            'starts_on' => $this->monday->copy()->startOfMonth()->toDateString(), 'created_by' => $this->team['super']->id,
        ]);
        $agreement->forceFill(['created_at' => $this->monday->copy()->startOfMonth()])->save();

        // Priced as each was held, in order, so the first each month is the free one.
        Session::whereIn('id', $this->held)->orderBy('counted_at')->get()->each(fn (Session $s) => $billing->charge($s, $agreement));

        // On the 1st, last month's invoice; most paid, one part-paid, the last not yet.
        $months = [];
        for ($month = $this->monday->copy()->startOfMonth(); $month->lt(today()->startOfMonth()); $month->addMonth()) {
            $invoice = $this->at($month->copy()->addMonth()->setTime(6, 10), fn () => $billing->invoice($this->org, $month->copy()));
            $invoice && $months[] = $invoice;
        }
        foreach ($months as $i => $invoice) {
            $left = count($months) - $i;
            $paidOn = $invoice->created_at->copy()->addDays(9);
            if ($left === 1 || $paidOn->isFuture()) {
                continue;
            }
            $amount = $left === 2 ? round((float) $invoice->total / 2, 2) : (float) $invoice->total;
            $method = $i % 2 ? 'mpesa' : 'bank_transfer';
            $this->at($paidOn->copy()->setTime(11, 0), fn () => $billing->recordPayment($invoice, $amount, $paidOn->toDateString(), $method, 'MCHT-' . strtoupper(Str::random(6)), $this->team['super']));
        }
    }

    private function youthNetwork(): void
    {
        $org = Organization::create([
            'name' => 'Thaba Bosiu Youth Network', 'email' => self::ORG_EMAILS[1], 'contact_email' => self::ORG_EMAILS[1], 'phone' => '+266 2700 0000',
            'description' => 'Youth leadership clubs in Maseru district.', 'slug' => 'thaba-bosiu-youth-network', 'is_active' => true,
            'logo_path' => $this->logo('logos/demo/thaba-bosiu-youth-network.png', [176, 74, 36], 'TBYN'),
        ]);
        $admin = User::create(['name' => 'Kamohelo Ntsane', 'email' => 'demo.team@example.com', 'password' => Hash::make(self::PASSWORD), 'organization_id' => $org->id, 'email_verified_at' => now()]);
        $admin->assignRole('org_admin');

        $start = now()->subDays(12);
        $this->at($start, fn () => SessionPackage::create([
            'organization_id' => $org->id, 'tier' => 'team', 'status' => 'active', 'sessions_included' => 3, 'sessions_used' => 3,
            'whatsapp_included' => 100, 'whatsapp_used' => 41, 'sms_included' => 0, 'price_paid' => 500,
            'period_start' => $start, 'period_end' => $start->copy()->addMonth(), 'notes' => 'Team plan, paid by M-Pesa.',
        ]));
        $this->at(now()->subDays(2), fn () => SessionPackage::create([
            'organization_id' => $org->id, 'tier' => 'payg', 'status' => 'active', 'sessions_included' => 1, 'sessions_used' => 0,
            'whatsapp_included' => 0, 'sms_included' => 0, 'price_paid' => 150, 'period_start' => now()->subDays(2), 'notes' => 'One session, pay as you go.',
        ]));

        $topics = [
            ['Club leaders\' meeting', 'meeting', 9, [['Club reports', 'Kamohelo Ntsane', ['6 clubs active, 2 quiet', 'Ha Mantšebo club needs a new leader'], 'Six clubs were active and two quiet; the Ha Mantšebo club needed a new leader.', ['Find a new leader for the Ha Mantšebo club.']]]],
            ['Youth leadership workshop', 'workshop', 6, [['Running a club meeting', 'Kamohelo Ntsane', ['agenda, minutes, rotating chair'], 'How to run a club meeting: an agenda, minutes and a rotating chair.', ['Each club keeps minutes.']]]],
            ['Club leaders\' meeting', 'meeting', 2, [['Club reports', 'Kamohelo Ntsane', ['new leader for Ha Mantšebo: Rets\'elisitsoe', 'football tournament planned'], 'A new leader had been found for the Ha Mantšebo club, and a football tournament was planned.', ['Hold the tournament at the end of the month.']]]],
        ];
        foreach ($topics as [$title, $type, $daysAgo, $parts]) {
            $date = today()->subDays($daysAgo);
            $session = $this->at($date->copy()->setTime(9, 0), fn () => app(SessionMaker::class)->make($org, $admin, [
                'title' => $title, 'type' => $type, 'date' => $date->toDateString(), 'start_time' => '15:00', 'location' => 'Thaba Bosiu Community Centre',
                'people' => collect($parts)->map(fn ($p) => ['title' => $p[0], 'name' => $p[1], 'role' => $type === 'workshop' ? 'facilitator' : null])->all(),
                'chair' => $type === 'meeting' ? 'Kamohelo Ntsane' : null,
            ]));
            $sections = array_keys(SessionType::sections($type));
            $items = $session->segments()->orderBy('order')->get()->map(fn ($segment, $i) => [
                $segment, $parts[$i][2],
                ['summary' => $parts[$i][3], $sections[1] => $this->bullets($parts[$i][4])] + array_fill_keys(array_slice($sections, 2), 'None identified.'),
            ])->all();
            $this->hold($session, $date->copy()->setTime(15, 0), $items, 30, ['by' => 'Kamohelo Ntsane', 'at' => $date->copy()->setTime(18, 0)], false);
        }
        // Tomorrow's takes the last session; the one after that is stopped.
        foreach ([1, 8] as $days) {
            app(SessionMaker::class)->make($org, $admin, [
                'title' => 'Club leaders\' meeting', 'type' => 'meeting', 'date' => today()->addDays($days)->toDateString(), 'start_time' => '15:00',
                'location' => 'Thaba Bosiu Community Centre', 'chair' => 'Kamohelo Ntsane',
                'people' => [['title' => 'Club reports', 'name' => 'Kamohelo Ntsane'], ['title' => 'Tournament', 'name' => 'Kamohelo Ntsane']],
            ]);
        }
    }

    /** Ask Ventiq's memory of every held session. */
    private function remember(): void
    {
        $indexer = app(MemoryIndexer::class);
        Session::where('status', 'reported')->whereIn('organization_id', Organization::whereIn('email', self::ORG_EMAILS)->pluck('id'))
            ->orderBy('id')->get()->each(fn (Session $s) => $indexer->indexSession($s));
    }

    private function report(): void
    {
        $c = $this->command;
        if (!$c) {
            return;
        }
        $c->info('Sessions demo ready. Password for every account: ' . self::PASSWORD);
        $c->table(['Login', 'Who', 'Can'], [
            ['demo.admin@example.com', 'Palesa Mokoena, Admin', 'Everything; signs certificates'],
            ['demo.staff@example.com', 'Thabo Lebona, Staff', 'Run sessions, minutes, programmes'],
            ['demo.viewer@example.com', 'Dr Lineo Ramoeletsi, Viewer', 'Read reports; signs certificates'],
            ['demo.door@example.com', 'Tumelo Phafoli, Door', 'Check people in'],
            ['demo.team@example.com', 'Kamohelo Ntsane, Youth Network', 'Team plan used up, 1 pay-as-you-go left'],
            ['demo.ventiq@example.com', 'VENTIQ super admin', 'Corporate rates and invoices'],
        ]);
        $c->line('Ask Ventiq, for example: "Why did we buy a second vehicle?", "What is still open for Teboho?", "What did we decide about fuel?"');
        $c->line('Embeddings: run `php artisan sessions:index-memory` with the Mac mini on (answers work on keywords without it).');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * A session held: started, its items noted and written up, reported,
     * and (when given) reviewed. Corporate ones are priced at the end.
     *
     * @param array<int, array{0: \App\Models\SessionSegment, 1: string[], 2: array}> $items
     */
    private function hold(Session $session, Carbon $start, array $items, int $minutesEach, ?array $review, bool $corporate = true): void
    {
        $at = $start->copy();
        foreach ($items as [$segment, $notes, $summary]) {
            $began = $at->copy();
            $log = collect($notes)->values()->map(fn ($text, $i) => ['time' => $began->copy()->addMinutes(2 + $i * 3)->format('H:i:s'), 'text' => $text])->all();
            $at->addMinutes($minutesEach);
            $segment->forceFill([
                'status' => 'completed', 'started_at' => $began, 'ended_at' => $at->copy(), 'raw_log' => $log, 'ai_summary' => $summary,
            ])->save();
        }
        // Those not presenting (a judge, the panel) were there throughout.
        $session->segments()->where('is_presenting', false)->update(['status' => 'completed', 'started_at' => $start, 'ended_at' => $at]);

        $session->forceFill([
            'status'      => 'reported',
            'counted_at'  => $start,
            'start_time'  => $start->format('H:i:s'),
            'reviewed_at' => $review['at'] ?? null,
            'meta'        => array_merge($session->meta ?? [], ['action_points' => 'done'], $review ? ['reviewed_by' => $review['by']] : []),
            'created_at'  => $start->copy()->subDays(2),
            'updated_at'  => $at,
        ])->save();
        $session->forceFill(['session_report' => $this->writeUp($session->fresh())])->save();

        if ($corporate) {
            $this->held[] = $session->id;
        }
    }

    /** The report as Ventiq Assist writes it, from the items' write-ups. */
    private function writeUp(Session $session): string
    {
        $session->load(['segments', 'organization', 'creator']);
        $job = new GenerateSessionReport('demo', $session->created_by ?? 0, $session->id);
        $metrics = [
            'presenterCount'    => $session->segments->where('is_presenting', true)->count(),
            'participantCount'  => $session->event_id ? Participant::where('session_id', $session->id)->count() : null,
            'durationFormatted' => (function () use ($session) {
                $seconds = $session->segments->where('is_presenting', true)->sum(fn ($s) => $s->started_at && $s->ended_at ? $s->started_at->diffInSeconds($s->ended_at) : 0);

                return intdiv($seconds, 3600) ? intdiv($seconds, 3600) . 'h ' . intdiv($seconds % 3600, 60) . 'm' : intdiv($seconds, 60) . 'm';
            })(),
        ];
        $build = new \ReflectionMethod(GenerateSessionReport::class, 'buildReport');

        return $build->invoke($job, $session, ['themes' => null, 'recommendations' => null], $metrics, SessionType::sections($session->type));
    }

    /** A session with check-in, its items given as [title, lead, notes, summary, points]. */
    private function tracked(string $title, string $type, Carbon $date, string $time, array $items, int $expected, string $pointsKey): Session
    {
        $session = $this->at($date->copy()->subDays(5)->setTime(10, 0), fn () => app(SessionMaker::class)->make($this->org, $this->team['thabo'], [
            'title' => $title, 'type' => $type, 'date' => $date->toDateString(), 'start_time' => $time, 'track_participants' => true,
            'expected_participants' => $expected, 'location' => $type === 'workshop' ? 'Mafeteng Community Hall' : 'Boardroom, Maseru office',
            'people' => collect($items)->map(fn ($i) => ['title' => $i[0], 'name' => $i[1], 'role' => $type === 'workshop' ? 'facilitator' : 'presenter'])->all(),
        ]));
        $segments = $session->segments()->orderBy('order')->get();
        $this->hold($session, $date->copy()->setTime(...array_map('intval', explode(':', $time))), $segments->map(fn ($segment, $i) => [
            $segment, $items[$i][2], ['summary' => $items[$i][3], $pointsKey => $this->bullets($items[$i][4]), 'follow_ups' => 'None identified.']
                + ($type === 'presentation' ? ['questions' => 'None identified.'] : []),
        ])->all(), 40, ['by' => 'Thabo Lebona', 'at' => $date->copy()->setTime(17, 0)]);

        return $session->fresh();
    }

    /** People checked in to a held session: [name, institution, position, district]. */
    private function attend(Session $session, array $people, array $sources): void
    {
        foreach ($people as $i => $p) {
            $participant = Participant::create([
                'organization_id' => $this->org->id, 'event_id' => $session->event_id, 'session_id' => $session->id, 'client_id' => $this->client($p[0])->id,
                'source' => $sources[$i % count($sources)], 'attended_at' => $session->date->copy()->setTimeFromTimeString($session->start_time)->subMinutes(15 - $i),
                'institution' => $p[1], 'position' => $p[2], 'district' => $p[3], 'signature_status' => 'pending',
            ]);
            if ($i % 4 === 3) {
                $participant->storeSignature($this->scribble(), $this->team['door']->id, 'VENTIQ Scanner');
                $participant->update(['source' => 'scanner']);
            }
        }
    }

    private function client(string $name): Client
    {
        return Client::firstOrCreate(
            ['organization_id' => $this->org->id, 'email' => $this->email($name)],
            ['full_name' => $name, 'phone' => $this->phone($name), 'status' => 'active'],
        );
    }

    /** A team member's key to their user; anyone else by name. */
    private function owner(string $who): array
    {
        return isset($this->team[$who]) ? ['owner_user_id' => $this->team[$who]->id] : ['owner_name' => $who];
    }

    private function week(int $n): Carbon
    {
        return $this->monday->copy()->addWeeks($n);
    }

    private function bullets(array $lines): string
    {
        return $lines ? collect($lines)->map(fn ($l) => "• {$l}")->implode("\n") : 'None identified.';
    }

    /** Run as if it were then, so what's made carries that date. */
    private function at(Carbon $when, \Closure $do): mixed
    {
        Carbon::setTestNow($when);
        try {
            return $do();
        } finally {
            Carbon::setTestNow();
        }
    }

    private function email(string $name): string
    {
        return Str::slug(Str::ascii($name), '.') . '@example.com';
    }

    /** Made up, and the same for the same person. */
    private function phone(string $name): string
    {
        return '+266 5' . str_pad((string) (crc32($name) % 10000000), 7, '0', STR_PAD_LEFT);
    }

    /** A drawn-looking signature, as the signature pad sends it. */
    private function scribble(): string
    {
        $img = imagecreatetruecolor(360, 120);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        $ink = imagecolorallocate($img, 22, 40, 90);
        imagesetthickness($img, 3);
        $loops = mt_rand(3, 5);
        $phase = mt_rand(0, 100) / 10;
        [$px, $py] = [20, 70];
        for ($x = 20; $x <= 330; $x += 4) {
            $t = ($x - 20) / 310;
            $y = 64 + sin($t * $loops * M_PI * 2 + $phase) * (26 - 14 * $t) + cos($t * 17) * 6;
            imageline($img, $px, $py, $x, (int) $y, $ink);
            [$px, $py] = [$x, (int) $y];
        }
        imageline($img, 40, 98, 300, 92, $ink);
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode(ob_get_clean());
    }

    /** A simple logo: a disc in the brand colour with mountains and initials. */
    private function logo(string $path, array $rgb, string $initials): string
    {
        $img = imagecreatetruecolor(240, 240);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        $brand = imagecolorallocate($img, ...$rgb);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefilledellipse($img, 120, 120, 232, 232, $brand);
        imagefilledpolygon($img, [44, 150, 100, 72, 140, 122, 162, 96, 200, 150], $white);
        imagefilledpolygon($img, [92, 84, 100, 72, 108, 84], $brand);
        $w = imagefontwidth(5) * strlen($initials);
        imagestring($img, 5, (int) (120 - $w / 2), 166, $initials, $white);
        ob_start();
        imagepng($img);
        imagedestroy($img);
        Storage::disk('public')->put($path, ob_get_clean());

        return $path;
    }
}
