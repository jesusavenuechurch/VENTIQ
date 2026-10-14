<?php
use App\Models\{Event, Organization, OrganizationPaymentMethod, User};
use Illuminate\Support\Str;

// Org users are sent to the organizer area for these screens; super
// admins still use them in Filament.
it('renders the payment account and event payment pages for super admins', function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => null, 'email_verified_at' => now()]);
    $user->assignRole('super_admin');
    $acct = OrganizationPaymentMethod::create(['organization_id' => $org->id, 'payment_method' => 'ecocash', 'account_name' => 'Events Account', 'account_number' => '625', 'is_active' => true, 'is_default' => true]);
    $event = Event::create(['organization_id' => $org->id, 'name' => 'E', 'slug' => 'e-' . Str::random(4), 'event_date' => now()->addMonth(), 'status' => 'draft', 'payment_mode' => 'paid', 'enabled_payment_method_ids' => [$acct->id]]);

    foreach ([
        '/admin/organization/organization-payment-methods',
        "/admin/organization/organization-payment-methods/{$acct->id}/edit",
        '/admin/events/events/create',
        "/admin/events/events/{$event->id}/edit",
    ] as $url) {
        $r = $this->actingAs($user)->get($url);
        expect($r->status())->toBe(200);
    }
    $this->actingAs($user)->get("/admin/events/events/{$event->id}/edit")->assertSee('Your attendees will be offered')->assertSee('EcoCash — Events Account');
});
