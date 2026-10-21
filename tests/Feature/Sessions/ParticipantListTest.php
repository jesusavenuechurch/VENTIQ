<?php

use App\Models\{Client, Event, Organization, Participant, Session, User};
use App\Services\Sessions\ParticipantList;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-21 08:00'));
    Storage::fake('public');
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create(['name' => 'Ministry of Health']);
    $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Palesa Mokoena']);
    $this->admin->assignRole('org_admin');
    $this->actingAs($this->admin);

    $this->programme = Event::create(['organization_id' => $this->org->id, 'name' => 'Nurses training', 'venue' => 'Lesotho Sun', 'event_date' => '2026-10-19', 'is_public' => false, 'is_programme' => true]);
    $this->day = fn (string $date) => Session::create(['organization_id' => $this->org->id, 'event_id' => $this->programme->id, 'type' => 'training', 'title' => 'Nurses training', 'date' => $date, 'status' => 'reported', 'session_code' => 'ABC' . substr($date, -2)]);
    $this->checkIn = function (Session $day, Client $client, string $time, string $source, array $extra = []) {
        return Participant::create($extra + [
            'organization_id' => $this->org->id, 'event_id' => $this->programme->id, 'session_id' => $day->id, 'client_id' => $client->id,
            'attended_at' => Carbon::parse("{$day->date->toDateString()} {$time}"), 'source' => $source, 'institution' => 'Queen II Hospital', 'position' => 'Nurse',
        ]);
    };
    $this->mpho = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Mpho Sello', 'phone' => '+26650000001', 'email' => 'mpho@example.com']);
    $this->lineo = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Lineo Ramoeletsi', 'phone' => '+26650000002']);
});

it('lists one row per person with a column per day, and how each check-in was recorded', function () {
    [$one, $two, $three] = [($this->day)('2026-10-19'), ($this->day)('2026-10-20'), ($this->day)('2026-10-21')];
    Storage::disk('public')->put('signatures/programmes/1/participant_1.png', 'png-bytes');
    ($this->checkIn)($one, $this->lineo, '08:40', 'scanner', ['signature_path' => 'signatures/programmes/1/participant_1.png', 'signature_status' => 'signed', 'signed_at' => now()]);
    ($this->checkIn)($one, $this->mpho, '08:05', 'self');
    ($this->checkIn)($two, $this->mpho, '08:10', 'walk_in', ['institution' => 'Maluti Hospital']);
    ($this->checkIn)($three, $this->mpho, '08:20', 'self');

    $list = app(ParticipantList::class)->forProgramme($this->programme, $this->admin);

    expect($list)->toMatchArray(['organization' => 'Ministry of Health', 'title' => 'Nurses training', 'venue' => 'Lesotho Sun', 'dates' => '19 Oct to 21 Oct 2026 (3 days)', 'by' => 'Palesa Mokoena'])
        ->and(collect($list['days'])->pluck('label')->all())->toBe(['Day 1', 'Day 2', 'Day 3'])
        ->and(collect($list['days'])->pluck('count')->all())->toBe([2, 1, 1])
        ->and(collect($list['rows'])->pluck('name')->all())->toBe(['Mpho Sello', 'Lineo Ramoeletsi'])   // first to arrive first
        ->and($list['rows'][0]['days'][$one->id])->toBe(['time' => '08:05', 'how' => 'Self'])
        ->and($list['rows'][0]['days'][$two->id])->toBe(['time' => '08:10', 'how' => 'Desk'])
        ->and($list['rows'][0]['organization'])->toBe('Queen II Hospital')
        ->and($list['rows'][0]['attended'])->toBe(3)
        ->and($list['rows'][1]['days'][$two->id])->toBeNull()
        ->and($list['rows'][1]['signature'])->toBe('data:image/png;base64,' . base64_encode('png-bytes'))
        ->and($list['how'])->toBe(['Desk' => 1, 'Scanner' => 1, 'Self' => 2]);

    $html = view('sessions.participant-list-pdf', ['list' => $list, 'blank' => 3])->render();
    expect($html)->toContain('Participant list: Nurses training')->toContain('Day 3')->toContain('Lineo Ramoeletsi')
        ->toContain('2 Self')->toContain('Produced 21 October 2026, 08:00 by Palesa Mokoena')
        ->and(substr_count($html, '<tr>'))->toBe(1 + 1 + 2 + 3 + 1);   // letterhead, header, people, blank rows, sign-off

    $response = $this->get(route('programmes.participants.pdf', $this->programme))->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('participant-list-nurses-training.pdf');
});

it("prints one session's list, and only for the organization's check-in roles", function () {
    $one = ($this->day)('2026-10-19');
    $one->update(['location' => 'Hall B']);
    ($this->checkIn)($one, $this->mpho, '08:05', 'self');
    Participant::create(['organization_id' => $this->org->id, 'event_id' => $this->programme->id, 'session_id' => $one->id, 'client_id' => $this->lineo->id, 'source' => 'self']);   // never arrived

    $list = app(ParticipantList::class)->forSession($one);
    expect($list['rows'])->toHaveCount(1)
        ->and($list['days'][0]['label'])->toBe('In')
        ->and($list['venue'])->toBe('Hall B')
        ->and($list['dates'])->toBe('Monday, 19 October 2026');

    $response = $this->get(route('sessions.checkin.pdf', $one) . '?blank=0')->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('participant-list-nurses-training-2026-10-19.pdf');

    $viewer = User::factory()->create(['organization_id' => $this->org->id]);
    $viewer->assignRole('viewer');
    $this->actingAs($viewer)->get(route('programmes.participants.pdf', $this->programme))->assertRedirect(route('sessions.index'));
    $other = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $other->assignRole('org_admin');
    $this->actingAs($other)->get(route('programmes.participants.pdf', $this->programme))->assertForbidden();
});

it('marks a self check-in as self', function () {
    $one = ($this->day)('2026-10-21');
    $one->update(['public_token' => 'tok123']);
    $this->post(route('public.session-checkin.submit', 'tok123'), ['full_name' => 'Nthabiseng Letsie', 'email' => 'ntha@example.com']);
    expect(Participant::where('session_id', $one->id)->value('source'))->toBe('self');
});

it("puts the organization's logo on its participant lists, minutes and reports", function () {
    $im = imagecreatetruecolor(20, 10);
    ob_start();
    imagepng($im);
    Storage::disk('public')->put('logos/moh.png', ob_get_clean());
    $this->org->update(['logo_path' => 'logos/moh.png']);
    $logo = \App\Support\Logo::of($this->org->fresh());
    expect($logo)->toStartWith('data:image/png;base64,')
        ->and(\App\Support\Logo::dataUri('logos/missing.png'))->toBeNull();

    $day = ($this->day)('2026-10-19');
    ($this->checkIn)($day, $this->mpho, '08:05', 'self');
    $list = view('sessions.participant-list-pdf', ['list' => app(ParticipantList::class)->forSession($day), 'blank' => 0, 'logo' => $logo])->render();
    $report = view('sessions.report-pdf', ['session' => $day->fresh(), 'logo' => $logo])->render();
    expect($list)->toContain($logo)->toContain('Ministry of Health')
        ->and($report)->toContain($logo);

    // Without a logo: just the name.
    expect(view('sessions.report-pdf', ['session' => $day->fresh(), 'logo' => null])->render())->not->toContain('<img')->toContain('Ministry of Health');

    $this->get(route('sessions.checkin.pdf', $day))->assertOk();
    $this->get(route('sessions.report.pdf', $day))->assertOk();
});
