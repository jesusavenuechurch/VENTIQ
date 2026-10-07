<?php

use App\Filament\Resources\EventResource\Pages\EventTiers;
use App\Models\{Event, EventTier, Organization, User};
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('lets a super admin change a ticket type\'s number from the event\'s tiers list', function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $super = User::factory()->create(['organization_id' => null]);
    $super->assignRole('super_admin');
    $event = Event::create(['organization_id' => Organization::factory()->create()->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid']);
    $tier = EventTier::create(['event_id' => $event->id, 'tier_name' => 'VIP', 'price' => 500, 'quantity_available' => 2, 'is_active' => true]);

    $this->actingAs($super);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->get(EventResource_url($event))->assertOk()->assertSee('Change number');

    Livewire::test(EventTiers::class, ['record' => $event->getKey()])
        ->callTableAction('change_number', $tier, data: ['quantity_available' => 50])
        ->assertHasNoTableActionErrors();

    expect($tier->fresh()->quantity_available)->toBe(50);
});

function EventResource_url(Event $event): string
{
    return \App\Filament\Resources\EventResource::getUrl('tiers', ['record' => $event]);
}
