<?php

use App\Mail\SignCertificatesMail;
use App\Models\{Certificate, CertificateDesign, Client, Event, Organization, User};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Mail, Storage};
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    $this->travelTo(Carbon::parse('2026-10-21 08:00'));
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create(['name' => 'Ministry of Health']);
    $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Palesa Mokoena']);
    $this->admin->assignRole('org_admin');
    $this->director = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Dr Thabo Lebona', 'email' => 'thabo@example.com']);
    $this->director->assignRole('viewer');
    $this->actingAs($this->admin);

    $this->programme = Event::create(['organization_id' => $this->org->id, 'name' => 'Nurses training', 'venue' => 'Lesotho Sun', 'event_date' => '2026-10-19', 'is_public' => false, 'is_programme' => true, 'certificates_enabled' => true]);
    $this->client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Nthabiseng Letsie']);

    // A small real PNG, as the signature pad sends it.
    $im = imagecreatetruecolor(40, 20);
    ob_start();
    imagepng($im);
    $this->png = 'data:image/png;base64,' . base64_encode(ob_get_clean());
    $this->design = fn (array $extra = []) => $extra + [
        'layout' => 'classic', 'title' => 'Certificate', 'subtitle' => 'of completion',
        'wording' => 'completed {programme} at {venue} over {days}.', 'primary_color' => '#14532d', 'accent_color' => '#ca8a04',
        'show_logo' => false, 'signatories' => [],
    ];
});

it('fills in the wording, in bold, and drops what has nothing to fill it', function () {
    expect(CertificateDesign::renderWording('completed {programme} at {venue}, on {dates}.', ['programme' => 'Nurses <training>', 'venue' => '', 'dates' => '19 to 21 October 2026']))
        ->toBe('completed <b>Nurses &lt;training&gt;</b>, on <b>19 to 21 October 2026</b>.');
});

it('lets an Admin allow someone to sign, who then draws their signature once', function () {
    $this->put(route('organizer.team.signs', $this->director), ['signs' => true])->assertRedirect();
    expect($this->director->fresh()->can_sign_certificates)->toBeTrue();
    Mail::assertQueued(SignCertificatesMail::class, fn ($m) => $m->hasTo('thabo@example.com'));

    // A Viewer in Sessions still gets to their own signature.
    $this->actingAs($this->director->fresh())->get(route('sessions.signature'))->assertInertia(fn (Assert $page) => $page->component('Sessions/Signature')->where('allowed', true)->where('signature', null));
    $this->put(route('sessions.signature.update'), ['signature' => 'data:image/png;base64,bm90IGEgcG5n', 'title' => 'Director'])->assertSessionHasErrors('signature');
    $this->put(route('sessions.signature.update'), ['signature' => $this->png, 'title' => 'Programme Director'])->assertSessionHasNoErrors();

    $director = $this->director->fresh();
    expect($director->signature_title)->toBe('Programme Director')
        ->and(Storage::disk('local')->exists($director->signature_path))->toBeTrue()
        ->and($director->signatureDataUri())->toStartWith('data:image/png;base64,')
        ->and($director->toArray())->not->toHaveKey('signature_path');

    $other = User::factory()->create(['organization_id' => $this->org->id]);
    $other->assignRole('staff');
    $this->actingAs($other)->put(route('sessions.signature.update'), ['signature' => $this->png])->assertForbidden();
});

it('saves the design, and a programme can have its own', function () {
    $this->director->forceFill(['can_sign_certificates' => true])->save();
    $outsider = User::factory()->create(['organization_id' => Organization::factory()->create()->id, 'can_sign_certificates' => true]);

    $this->put(route('sessions.certificates.design.update'), ($this->design)(['signatories' => [$outsider->id]]))->assertSessionHasErrors('signatories.0');
    $this->put(route('sessions.certificates.design.update'), ($this->design)(['signatories' => [$this->director->id]]))->assertSessionHasNoErrors();
    $this->put(route('sessions.certificates.design.update'), ($this->design)(['layout' => 'minimal', 'title' => 'Award', 'programme' => $this->programme->id]))->assertSessionHasNoErrors();

    expect(CertificateDesign::for($this->org->id)->layout)->toBe('classic')
        ->and(CertificateDesign::for($this->org->id, $this->programme->id)->title)->toBe('Award')
        ->and(CertificateDesign::for($this->org->id, 999999)->layout)->toBe('classic');

    $this->get(route('sessions.certificates.design', ['programme' => $this->programme->id]))->assertInertia(fn (Assert $page) => $page
        ->component('Programmes/CertificateDesign')->where('design.title', 'Award')->where('programme.own', true)->where('signers.0.name', 'Dr Thabo Lebona'));

    $this->delete(route('sessions.certificates.design.reset', ['programme' => $this->programme->id]))->assertRedirect();
    expect(CertificateDesign::for($this->org->id, $this->programme->id)->title)->toBe('Certificate');

    // Viewers don't design.
    $this->actingAs($this->director)->put(route('sessions.certificates.design.update'), ($this->design)())->assertForbidden();
});

it('previews an unsaved design with the real view, and makes a sample PDF', function () {
    $this->director->forceFill(['can_sign_certificates' => true, 'signature_title' => 'Director'])->save();

    $html = $this->postJson(route('sessions.certificates.design.preview'), ($this->design)(['signatories' => [$this->director->id], 'programme' => $this->programme->id]))->assertOk()->json('html');
    expect($html)->toContain('Nthabiseng Letsie')->toContain('completed <b>Nurses training</b> at <b>Lesotho Sun</b>')->toContain('Dr Thabo Lebona')->toContain('Director')->toContain('class="classic"');

    $response = $this->get(route('sessions.certificates.design.sample'))->assertOk();
    expect($response->headers->get('content-type'))->toContain('pdf');
});

it('keeps the design and signature a certificate was issued with', function () {
    $this->director->forceFill(['can_sign_certificates' => true, 'signature_title' => 'Director'])->save();
    $this->actingAs($this->director->fresh())->put(route('sessions.signature.update'), ['signature' => $this->png]);
    $firstSignature = $this->director->fresh()->signature_path;
    $this->actingAs($this->admin)->put(route('sessions.certificates.design.update'), ($this->design)(['signatories' => [$this->director->id]]));

    $certificate = Certificate::create(['organization_id' => $this->org->id, 'event_id' => $this->programme->id, 'client_id' => $this->client->id, 'issued_at' => now()]);
    expect($certificate->design)->toMatchArray(['layout' => 'classic', 'subtitle' => 'of completion'])
        ->and($certificate->design['signatories'][0])->toBe(['name' => 'Dr Thabo Lebona', 'title' => 'Director', 'signature' => $firstSignature]);

    // Later: a new design, a redrawn signature, and the director no longer signs.
    $this->travel(1)->minutes();
    $this->actingAs($this->director->fresh())->put(route('sessions.signature.update'), ['signature' => $this->png]);
    $this->actingAs($this->admin)->put(route('sessions.certificates.design.update'), ($this->design)(['layout' => 'banner']));
    $this->put(route('organizer.team.signs', $this->director), ['signs' => false]);

    expect($certificate->fresh()->design['layout'])->toBe('classic')
        ->and(Storage::disk('local')->exists($firstSignature))->toBeTrue()
        ->and($this->director->fresh()->signature_path)->not->toBe($firstSignature);

    $view = CertificateDesign::view($certificate->fresh()->design, $certificate->fresh()->facts());
    expect($view['signatories'][0]['image'])->toStartWith('data:image/png;base64,')
        ->and($view['wording'])->toContain('<b>Lesotho Sun</b>')
        ->and(substr($certificate->fresh()->pdf()->output(), 0, 4))->toBe('%PDF');

    // A new certificate: the new design, and no signature from someone who no longer signs.
    $next = Certificate::create(['organization_id' => $this->org->id, 'event_id' => $this->programme->id, 'client_id' => Client::create(['organization_id' => $this->org->id, 'full_name' => 'Mpho Sello'])->id, 'issued_at' => now()]);
    expect($next->design['layout'])->toBe('banner')->and($next->design['signatories'])->toBe([]);
});
