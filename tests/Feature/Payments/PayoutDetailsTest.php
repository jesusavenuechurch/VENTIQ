<?php

use App\Models\{Organization, User};
use App\Notifications\Organizer\PayoutDetailsChanged;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create(['phone' => '+26659494756']);
    $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
    $this->admin->assignRole('org_admin');
    $this->otherAdmin = User::factory()->create(['organization_id' => $this->org->id]);
    $this->otherAdmin->assignRole('org_admin');
});

it('puts organization, payouts and team under one Settings tab', function () {
    $this->actingAs($this->admin);
    foreach (['organizer.organization.edit', 'organizer.payout.edit', 'organizer.team.index'] as $route) {
        $this->get(route($route))->assertOk()->assertSee('Settings')
            ->assertSee(route('organizer.organization.edit'))->assertSee(route('organizer.payout.edit'))->assertSee(route('organizer.team.index'));
    }
    $this->get(route('organizer.settings'))->assertRedirect(route('organizer.organization.edit'));
});

it('lets an admin set the payout account, and tells every admin', function () {
    $this->actingAs($this->admin)->get(route('organizer.payout.edit'))->assertSee('No payout account yet');

    $this->put(route('organizer.payout.update'), [
        'payout_method' => 'bank_transfer', 'payout_bank_name' => 'Standard Lesotho Bank',
        'payout_account_name' => 'MYN Events', 'payout_account_number' => '9080 1234 5678',
    ])->assertRedirect(route('organizer.payout.edit'));

    $org = $this->org->fresh();
    expect($org->payoutSummary())->toBe('Bank transfer · Standard Lesotho Bank · MYN Events · 9080 1234 5678')
        ->and($org->payout_updated_by)->toBe($this->admin->id)
        ->and($org->payoutRecentlyChanged())->toBeTrue();
    Notification::assertSentTo([$this->admin, $this->otherAdmin], PayoutDetailsChanged::class);

    // EcoCash needs no bank, and drops the old one.
    $this->put(route('organizer.payout.update'), ['payout_method' => 'ecocash', 'payout_account_name' => 'Lerato M', 'payout_account_number' => '59494756']);
    expect($this->org->fresh()->payout_bank_name)->toBeNull();
});

it('checks the details, and keeps them from the rest of the team', function () {
    $this->actingAs($this->admin)->put(route('organizer.payout.update'), ['payout_method' => 'bank_transfer', 'payout_account_name' => 'X', 'payout_account_number' => 'abc'])
        ->assertSessionHasErrors(['payout_bank_name', 'payout_account_number']);

    $this->org->update(['payout_method' => 'ecocash', 'payout_account_name' => 'Lerato', 'payout_account_number' => '59494756']);
    $staff = User::factory()->create(['organization_id' => $this->org->id]);
    $staff->assignRole('staff');

    $this->actingAs($staff)->get(route('organizer.payout.edit'))->assertOk()->assertSee('••••4756')->assertDontSee('59494756')->assertSee('Only an admin');
    $this->put(route('organizer.payout.update'), ['payout_method' => 'ecocash', 'payout_account_name' => 'Thief', 'payout_account_number' => '50000000'])->assertForbidden();
    expect($this->org->fresh()->payout_account_number)->toBe('59494756');
    Notification::assertNothingSent();
});

it('shows VENTIQ where to pay, and warns about a change just made', function () {
    $super = User::factory()->create(['organization_id' => null]);
    $super->assignRole('super_admin');
    $event = \App\Models\Event::create(['organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(5), 'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid']);
    $tier = \App\Models\EventTier::create(['event_id' => $event->id, 'tier_name' => 'General', 'price' => 100, 'is_active' => true]);
    $client = \App\Models\Client::create(['organization_id' => $this->org->id, 'full_name' => 'Guest', 'phone' => '+26650000001']);
    \Illuminate\Support\Facades\Bus::fake();
    \Illuminate\Support\Facades\Mail::fake();
    $ticket = \App\Models\Ticket::create(['event_id' => $event->id, 'client_id' => $client->id, 'event_tier_id' => $tier->id, 'status' => 'pending', 'payment_status' => 'pending', 'amount' => 100]);
    \App\Models\TicketPayment::create(['ticket_id' => $ticket->id, 'amount' => 100, 'status' => 'pending', 'payment_type' => 'full']);
    $session = \App\Models\PaymentSession::create(['payable_type' => 'ticket', 'payable_id' => $ticket->id, 'gateway' => 'paylesotho', 'client_reference' => 'R' . Str::random(8), 'payment_method' => 'ecocash', 'amount' => 100, 'status' => 'completed', 'organization_id' => $this->org->id]);
    app(\App\Services\Payments\TicketActivationService::class)->activate($ticket, \App\Services\Payments\TicketActivationService::SOURCE_VENTIQ_ONLINE, 'ecocash', 'TX', null, $session);

    $this->actingAs($super)->get(route('ventiq.money.index'))->assertSee('No payout account on file');

    $this->org->update(['payout_method' => 'mpesa', 'payout_account_name' => 'Lerato', 'payout_account_number' => '58000000', 'payout_updated_at' => now()]);
    $this->get(route('ventiq.money.index'))->assertSee('M-Pesa · Lerato · 58000000')->assertSee('confirm with the organizer by phone');

    $this->org->update(['payout_updated_at' => now()->subWeek()]);
    $this->get(route('ventiq.money.index'))->assertDontSee('confirm with the organizer by phone');
});
