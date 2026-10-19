<?php

use App\Models\{AppRelease, ContactInquiry, Organization, SessionPackage, Settlement, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->super = User::factory()->create(['organization_id' => null]);
    $this->super->assignRole('super_admin');
    $this->org = Organization::factory()->create(['name' => 'Maseru Youth Network']);
});

it('lists payout batches and opens one', function () {
    $batch = Settlement::create(['organization_id' => $this->org->id, 'trigger_type' => 'manual', 'gross_paid' => 200, 'gateway_fees' => 17.3, 'amount_received' => 200, 'amount_owed_to_org' => 182.7, 'ventiq_revenue' => 17.3, 'status' => 'pending']);

    $this->actingAs($this->super)->get(route('ventiq.payouts.index', ['batch' => $batch->id]))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Ventiq/Payouts')->where('rows.data.0.owed', 182.7)->where('batch.id', $batch->id)->where('batch.organization', 'Maseru Youth Network'));
});

it('puts an organization on a plan and adds pay-as-you-go sessions', function () {
    $this->actingAs($this->super)->post(route('ventiq.session-plans.plan'), [
        'organization_id' => $this->org->id, 'tier' => 'team', 'sessions_included' => 3, 'whatsapp_included' => 50, 'sms_included' => 50, 'price_paid' => 500,
    ])->assertSessionHas('status', 'Maseru Youth Network is on the Team plan.');
    expect(SessionPackage::where('organization_id', $this->org->id)->where('tier', 'team')->where('status', 'active')->exists())->toBeTrue();

    $this->post(route('ventiq.session-plans.payg'), ['organization_id' => $this->org->id, 'quantity' => 3])
        ->assertSessionHas('status', fn ($s) => str_contains($s, '3 pay-as-you-go sessions'));
    expect((float) SessionPackage::where('organization_id', $this->org->id)->where('tier', 'payg')->value('price_paid'))->toBe(400.0);

    $this->get(route('ventiq.session-plans.index', ['filter' => ['tier' => 'team']]))->assertInertia(fn ($page) => $page->has('rows.data', 1));
});

it('keeps the inbox: status, notes and a WhatsApp reply link', function () {
    $message = ContactInquiry::create(['name' => 'Palesa', 'phone' => '+26658001234', 'subject' => 'Pricing', 'message' => 'How much for 300 people?', 'status' => 'new']);

    $this->actingAs($this->super)->get(route('ventiq.inbox.index'))
        ->assertInertia(fn ($page) => $page->where('unread', 1)->where('rows.data.0.whatsapp', fn ($url) => str_starts_with($url, 'https://wa.me/26658001234')));

    $this->put(route('ventiq.inbox.update', $message), ['status' => 'replied', 'notes' => 'Quoted M1,500.']);
    expect($message->fresh()->status)->toBe('replied')->and($message->fresh()->notes)->toBe('Quoted M1,500.')->and($message->fresh()->handled_by)->toBe($this->super->id);
});

it('uploads a scanner build and makes it the current one', function () {
    Storage::fake(AppRelease::DISK);
    $old = AppRelease::create(['version' => '1.0.0', 'play_store_url' => 'https://play.google.com/store/apps/details?id=ls.ventiq', 'is_current' => true]);

    $this->actingAs($this->super)->post(route('ventiq.app-releases.store'), [
        'version' => '1.1.0', 'apk' => UploadedFile::fake()->create('scanner.apk', 2048, 'application/vnd.android.package-archive'), 'is_current' => true,
    ])->assertSessionHas('status');
    $new = AppRelease::where('version', '1.1.0')->sole();
    expect($new->is_current)->toBeTrue()->and($old->fresh()->is_current)->toBeFalse()->and($new->hasApk())->toBeTrue();

    $this->post(route('ventiq.app-releases.store'), ['version' => '1.2.0'])->assertSessionHasErrors('apk');

    $this->put(route('ventiq.app-releases.update', $old), ['is_current' => true, 'play_store_url' => $old->play_store_url]);
    expect($old->fresh()->is_current)->toBeTrue()->and($new->fresh()->is_current)->toBeFalse();
});

it('is for super admins only', function () {
    $admin = User::factory()->create(['organization_id' => $this->org->id]);
    $admin->assignRole('org_admin');
    foreach (['ventiq.payouts.index', 'ventiq.session-plans.index', 'ventiq.inbox.index', 'ventiq.app-releases.index'] as $name) {
        $this->actingAs($admin)->get(route($name))->assertForbidden();
    }
});
