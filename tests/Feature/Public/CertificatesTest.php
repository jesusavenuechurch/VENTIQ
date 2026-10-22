<?php

use App\Models\{Certificate, Client, Event, Organization};
use Illuminate\Support\Str;

it('finds a certificate by its number and shows who it was awarded to', function () {
    $org = Organization::factory()->create(['name' => 'Maseru Youth Network']);
    $programme = Event::create(['organization_id' => $org->id, 'name' => 'Leadership Programme', 'slug' => 'lead-' . Str::random(4), 'status' => 'published', 'event_date' => now()->subWeek()]);
    $client = Client::create(['organization_id' => $org->id, 'full_name' => 'Lerato Mokoena', 'phone' => '+26659494756']);
    $certificate = Certificate::create(['organization_id' => $org->id, 'event_id' => $programme->id, 'client_id' => $client->id, 'issued_at' => now()]);

    $this->get(route('certificates.lookup'))->assertOk()->assertInertia(fn ($page) => $page->component('Certificates/Lookup')->where('error', null));
    $this->get(route('certificates.lookup', ['number' => 'VQ-0000-NOPE']))->assertInertia(fn ($page) => $page->where('error', fn ($e) => str_contains($e, 'No certificate found')));
    $this->get(route('certificates.lookup', ['number' => $certificate->certificate_number]))->assertRedirect(route('certificates.verify', $certificate->token));

    $this->get(route('certificates.verify', $certificate->token))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Certificates/Verify')->where('certificate.holder', 'Lerato Mokoena')
            ->where('certificate.programme', 'Leadership Programme')->where('certificate.download', route('certificates.download.public', $certificate->token)));
    $this->get(route('certificates.verify', 'not-a-token'))->assertNotFound();
});
