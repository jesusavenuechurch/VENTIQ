<?php

use App\Models\{Client, Event, EventTier, Organization, Ticket, User};
use App\Notifications\Organizer\TierSoldOut;
use Illuminate\Support\Facades\{Bus, Mail, Notification};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    Notification::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
    $this->admin->assignRole('org_admin');
    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid', 'is_public' => true,
    ]);
    $this->vip = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'VIP', 'price' => 500, 'quantity_available' => 2, 'is_active' => true]);
    $this->hold = fn () => Ticket::create([
        'event_id' => $this->event->id, 'event_tier_id' => $this->vip->id, 'status' => 'pending', 'payment_status' => 'pending', 'amount' => 500,
        'client_id' => Client::create(['organization_id' => $this->org->id, 'full_name' => 'Guest', 'phone' => '+2665' . random_int(1000000, 9999999)])->id,
    ]);
});

it('tells the organizer once when a ticket type sells out, and again after they allow more', function () {
    ($this->hold)();
    Notification::assertNothingSent();

    ($this->hold)();   // 2 of 2
    Notification::assertSentToTimes($this->admin, TierSoldOut::class, 1);
    Notification::assertSentTo($this->admin, TierSoldOut::class, fn ($n) => str_contains($n->toMail($this->admin)->subject, 'VIP is sold out')
        && str_contains($n->editUrl, "/events/{$this->event->id}/edit"));

    $this->vip->update(['quantity_available' => 3]);   // reopens
    expect($this->vip->fresh()->sold_out_notified_at)->toBeNull();
    ($this->hold)();   // 3 of 3
    Notification::assertSentToTimes($this->admin, TierSoldOut::class, 2);
});

it('won\'t lower a ticket type below the places already taken', function () {
    ($this->hold)();
    ($this->hold)();

    $this->actingAs($this->admin)->put(route('organizer.events.update', $this->event), [
        'name' => 'Summit', 'category' => 'education', 'city' => 'Maseru', 'event_date' => now()->addMonth()->format('Y-m-d'), 'event_time' => '18:00',
        'status' => 'published', 'online' => '0', 'account_ids' => [],
        'tiers' => [['id' => $this->vip->id, 'tier_name' => 'VIP', 'price' => '500', 'quantity_available' => '1', 'quantity_per_purchase' => '1', 'is_active' => '1']],
    ])->assertSessionHasErrors(['tiers.0.quantity_available' => "2 VIP tickets are already taken, so the number can't be lower than 2."]);
});
