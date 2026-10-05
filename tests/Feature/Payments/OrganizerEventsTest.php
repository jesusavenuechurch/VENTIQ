<?php

use App\Models\{Client, Event, EventTier, Organization, OrganizationPaymentMethod, Ticket, TicketPayment, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Bus, Mail, Notification, Storage};
use Illuminate\Support\Str;
use App\Services\Payments\PaymentAccountService;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    Notification::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
    $this->admin->assignRole('org_admin');

    $this->events  = OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'ecocash', 'account_name' => 'Events Account', 'account_number' => '62500000', 'is_active' => true, 'is_default' => true]);
    $this->main    = OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'ecocash', 'account_name' => 'Main Account', 'account_number' => '58000000', 'is_active' => true]);
});

function eventForm(array $overrides = []): array
{
    return array_replace_recursive([
        'name'         => 'Maseru Youth Summit',
        'tagline'      => 'Two days of ideas',
        'description'  => 'A summit.',
        'category'     => 'education',
        'city'         => 'Maseru',
        'is_public'    => '1',
        'event_date'   => now()->addMonth()->format('Y-m-d'),
        'event_time'   => '18:00',
        'venue'        => 'Convention Centre',
        'payment_mode' => 'free',
        'status'       => 'draft',
    ], $overrides);
}

function paidForm(object $t, array $overrides = []): array
{
    return eventForm(array_replace_recursive([
        'payment_mode' => 'paid',
        'online'       => '1',
        'account_ids'  => [$t->events->id],
        'tiers'        => [
            ['tier_name' => 'General', 'price' => '250', 'quantity_per_purchase' => '1', 'is_active' => '1'],
            ['tier_name' => 'Table of 3', 'price' => '750', 'quantity_available' => '10', 'quantity_per_purchase' => '3', 'is_active' => '1'],
        ],
    ], $overrides));
}

describe('creating events', function () {
    it('opens the form with the default accounts ticked', function () {
        $this->actingAs($this->admin)->get(route('organizer.events.create'))
            ->assertOk()
            ->assertSee('Create an event')
            ->assertSee('EcoCash — Events Account')
            ->assertSee('Write it with Ventiq Assist')
            ->assertSee('value="' . $this->events->id . '" x-model.number="selected" checked', false)
            ->assertDontSee('value="' . $this->main->id . '" x-model.number="selected" checked', false);
    });

    it('creates a free event with a Free ticket', function () {
        $this->actingAs($this->admin)->post(route('organizer.events.store'), eventForm())
            ->assertRedirect(route('organizer.home'))->assertSessionHas('status');

        $event = Event::where('name', 'Maseru Youth Summit')->firstOrFail();
        expect($event->organization_id)->toBe($this->org->id)
            ->and($event->status)->toBe('draft')
            ->and($event->payment_mode)->toBe('free')
            ->and($event->slug)->not->toBeEmpty()
            ->and($event->enabled_payment_method_ids)->toBeNull()
            ->and($event->tiers()->pluck('tier_name')->all())->toBe(['Free']);
    });

    it('creates a paid event with ticket types, a group ticket and payment options', function () {
        Storage::fake('public');

        $this->actingAs($this->admin)->post(route('organizer.events.store'), paidForm($this, [
            'status' => 'published',
            'payment_window_hours' => '48',
            'allow_installments' => '1',
            'minimum_deposit_percentage' => '40',
            'banner' => UploadedFile::fake()->image('poster.jpg'),
        ]))->assertRedirect(route('organizer.home'));

        $event = Event::where('name', 'Maseru Youth Summit')->firstOrFail();
        $online = OrganizationPaymentMethod::where('organization_id', $this->org->id)->where('payment_method', 'online')->first();

        expect($event->status)->toBe('published')
            ->and($event->enabled_payment_method_ids)->toBe([$online->id, $this->events->id])
            ->and($event->payment_window_hours)->toBe(48)
            ->and($event->allow_installments)->toBeTrue()
            ->and((float) $event->minimum_deposit_percentage)->toBe(40.0);
        Storage::disk('public')->assertExists($event->banner_image);

        $table = $event->tiers()->where('tier_name', 'Table of 3')->first();
        expect($table->quantity_per_purchase)->toBe(3)
            ->and((bool) $table->is_group_ticket)->toBeTrue()
            ->and($table->quantity_available)->toBe(10)
            ->and($event->tiers()->count())->toBe(2);
    });

    it('needs a way to pay and a ticket type for paid events, and a future date', function () {
        $form = paidForm($this, ['online' => '0', 'event_date' => now()->subDay()->format('Y-m-d')]);
        $form['account_ids'] = [];
        $this->actingAs($this->admin)->post(route('organizer.events.store'), $form)
            ->assertSessionHasErrors(['account_ids', 'event_date']);

        $form = paidForm($this);
        $form['tiers'] = [];
        $this->post(route('organizer.events.store'), $form)->assertSessionHasErrors('tiers');

        expect(Event::count())->toBe(0);
    });

    it('keeps team members without permission out', function () {
        $viewer = User::factory()->create(['organization_id' => $this->org->id]);
        $viewer->assignRole('viewer');

        $this->actingAs($viewer)->get(route('organizer.events.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('organizer.events.store'), eventForm())->assertForbidden();
    });
});

describe('editing events', function () {
    beforeEach(function () {
        $this->actingAs($this->admin)->post(route('organizer.events.store'), paidForm($this));
        $this->event = Event::where('name', 'Maseru Youth Summit')->firstOrFail();
        $this->general = $this->event->tiers()->where('tier_name', 'General')->first();
        $this->table = $this->event->tiers()->where('tier_name', 'Table of 3')->first();
    });

    it('shows the event with its ticket types and payment options', function () {
        $page = $this->get(route('organizer.events.edit', $this->event))
            ->assertOk()->assertSee('Edit event')->assertSee('Table of 3');
        expect($page->getContent())->toMatch('/value="ecocash" x-model="methods"\s+checked/');
    });

    it('updates details and ticket types, removing an unsold type', function () {
        $form = paidForm($this, ['name' => 'Maseru Youth Summit 2026', 'account_ids' => [$this->main->id]]);
        $form['tiers'] = [['id' => $this->general->id, 'tier_name' => 'General Admission', 'price' => '300', 'quantity_per_purchase' => '1', 'is_active' => '1']];

        $this->put(route('organizer.events.update', $this->event), $form)
            ->assertRedirect(route('organizer.events.edit', $this->event));

        $originalSlug = $this->event->slug;
        $this->event->refresh();
        expect($this->event->name)->toBe('Maseru Youth Summit 2026')
            ->and($this->event->slug)->toBe($originalSlug)
            ->and($this->general->fresh()->tier_name)->toBe('General Admission')
            ->and((float) $this->general->fresh()->price)->toBe(300.0)
            ->and(EventTier::find($this->table->id))->toBeNull()
            ->and($this->event->enabled_payment_method_ids)->toContain($this->main->id)
            ->and($this->event->enabled_payment_method_ids)->not->toContain($this->events->id);
    });

    it('switches off a removed ticket type that has sales, and locks free/paid', function () {
        $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'L', 'phone' => '+26650001111']);
        Ticket::create(['event_id' => $this->event->id, 'client_id' => $client->id, 'event_tier_id' => $this->table->id, 'status' => 'pending', 'payment_status' => 'pending', 'amount' => 750, 'admissions' => 3]);

        $form = paidForm($this, ['payment_mode' => 'free']);
        $form['tiers'] = [['id' => $this->general->id, 'tier_name' => 'General', 'price' => '250', 'quantity_per_purchase' => '1', 'is_active' => '1']];

        $this->put(route('organizer.events.update', $this->event), $form)
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Table of 3') && str_contains($s, 'switched off'));

        expect((bool) $this->table->fresh()->is_active)->toBeFalse()
            ->and($this->event->fresh()->payment_mode)->toBe('paid');

        $this->get(route('organizer.events.edit', $this->event))->assertSee('can no longer switch between free and paid');
    });

    it('hides other organizations\' events', function () {
        $outsider = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $outsider->assignRole('org_admin');

        $this->actingAs($outsider)->get(route('organizer.events.edit', $this->event))->assertNotFound();
        $this->actingAs($outsider)->put(route('organizer.events.update', $this->event), paidForm($this))->assertNotFound();
    });
});

describe('payment accounts', function () {
    it('lists, adds and edits accounts', function () {
        $this->actingAs($this->admin)->get(route('organizer.accounts.index'))
            ->assertOk()->assertSee('EcoCash — Events Account')->assertSee('Main Account');

        $this->post(route('organizer.accounts.store'), [
            'payment_method' => 'bank_transfer', 'account_name' => 'Conference Account', 'account_number' => '6200123',
        ])->assertSessionHas('status', 'Account added.');

        $this->post(route('organizer.accounts.store'), ['payment_method' => 'mpesa', 'account_name' => 'No number'])
            ->assertSessionHasErrors('account_number');

        $this->put(route('organizer.accounts.update', $this->main), ['account_name' => 'Main', 'account_number' => '58999999']);
        expect($this->main->fresh()->account_name)->toBe('Main')
            ->and($this->main->fresh()->account_number)->toBe('58999999');
    });

    it('keeps the number of an account that has been paid into, and won\'t delete it', function () {
        $event = Event::create(['organization_id' => $this->org->id, 'name' => 'E', 'slug' => 'e-' . Str::random(5), 'event_date' => now()->addWeek()]);
        $tier = EventTier::create(['event_id' => $event->id, 'tier_name' => 'S', 'price' => 10]);
        $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'L', 'phone' => '+26650002222']);
        $ticket = Ticket::create(['event_id' => $event->id, 'client_id' => $client->id, 'event_tier_id' => $tier->id, 'amount' => 10]);
        TicketPayment::create(['ticket_id' => $ticket->id, 'amount' => 10, 'status' => 'approved', 'organization_payment_method_id' => $this->events->id, 'payment_type' => 'full']);

        $this->actingAs($this->admin)->put(route('organizer.accounts.update', $this->events), ['account_name' => 'Events', 'account_number' => '11111111'])
            ->assertSessionHasNoErrors();
        expect($this->events->fresh()->account_number)->toBe('62500000')
            ->and($this->events->fresh()->account_name)->toBe('Events');

        $this->delete(route('organizer.accounts.destroy', $this->events))->assertSessionHas('status', fn ($s) => str_contains($s, 'only be switched off'));
        expect(OrganizationPaymentMethod::find($this->events->id))->not->toBeNull();

        $this->delete(route('organizer.accounts.destroy', $this->main));
        expect(OrganizationPaymentMethod::find($this->main->id))->toBeNull();
    });

    it('moves the default and drops it when an account is switched off', function () {
        $this->actingAs($this->admin)->post(route('organizer.accounts.default', $this->main));
        expect($this->main->fresh()->is_default)->toBeTrue()
            ->and($this->events->fresh()->is_default)->toBeFalse();

        $this->post(route('organizer.accounts.toggle', $this->main));
        expect($this->main->fresh()->is_active)->toBeFalse()
            ->and($this->main->fresh()->is_default)->toBeFalse();
    });

    it('hides other organizations\' accounts and VENTIQ\'s online row', function () {
        $online = OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'online', 'is_active' => true]);
        $other = OrganizationPaymentMethod::create(['organization_id' => Organization::factory()->create()->id, 'payment_method' => 'cash', 'is_active' => true]);

        $this->actingAs($this->admin)->post(route('organizer.accounts.toggle', $other))->assertNotFound();
        $this->post(route('organizer.accounts.toggle', $online))->assertNotFound();
    });
});

describe('leaving Filament behind', function () {
    it('sends org users from the Filament event and account screens to the organizer area', function () {
        $event = Event::create(['organization_id' => $this->org->id, 'name' => 'E', 'slug' => 'e-' . Str::random(5), 'event_date' => now()->addWeek()]);

        $this->actingAs($this->admin);
        $this->get(route('filament.admin.events.resources.events.create'))->assertRedirect(route('organizer.events.create'));
        $this->get(route('filament.admin.events.resources.events.edit', $event))->assertRedirect(route('organizer.events.edit', $event));
        $this->get(route('filament.admin.events.resources.events.index'))->assertRedirect(route('organizer.home'));
        $this->get(route('filament.admin.organization.resources.organization-payment-methods.index'))->assertRedirect(route('organizer.accounts.index'));
    });

    it('leaves super admins in Filament', function () {
        $super = User::factory()->create(['organization_id' => null]);
        $super->assignRole('super_admin');

        $this->actingAs($super)->get(route('filament.admin.events.resources.events.create'))->assertOk();
    });

    it('links the organizer pages to each other, with Home instead of Sessions', function () {
        $this->actingAs($this->admin)->get(route('organizer.home'))
            ->assertSee(route('organizer.events.create'))
            ->assertSee(route('organizer.accounts.index'))
            ->assertSee(route('home'))
            ->assertDontSee(route('filament.admin.events.resources.events.create'));
    });
});

describe('online payment methods', function () {
    beforeEach(function () {
        config(['gateways.paylesotho.enabled' => true, 'gateways.paylesotho.ecocash.enabled' => true, 'gateways.paylesotho.mpesa.enabled' => false]);
    });

    it('shows every method, with the ones not live yet as coming soon', function () {
        $this->actingAs($this->admin)->get(route('organizer.events.create'))
            ->assertOk()->assertSee('EcoCash')->assertSee('M-Pesa')->assertSee('Card')->assertSee('Coming soon');
    });

    it('stores the chosen methods, and attendees are offered only those that are live', function () {
        $this->actingAs($this->admin)->post(route('organizer.events.store'), paidForm($this, ['online' => null, 'online_methods' => ['ecocash']]))
            ->assertSessionHasNoErrors();
        $event = Event::latest('id')->first();

        expect($event->online_methods)->toBe(['ecocash'])
            ->and(app(PaymentAccountService::class)->onlineMethodsForEvent($event))->toBe(['ecocash']);

        // M-Pesa switched on later: this event still offers only what it chose.
        config(['gateways.paylesotho.mpesa.enabled' => true]);
        expect(app(PaymentAccountService::class)->onlineMethodsForEvent($event))->toBe(['ecocash']);
    });

    it('refuses a method that is not live yet', function () {
        $this->actingAs($this->admin)->post(route('organizer.events.store'), paidForm($this, ['online' => null, 'online_methods' => ['mpesa']]))
            ->assertSessionHasErrors('online_methods.0');
    });

    it('treats an event with no online methods as direct payment only', function () {
        $this->actingAs($this->admin)->post(route('organizer.events.store'), paidForm($this, ['online' => null, 'online_methods' => '']))
            ->assertSessionHasNoErrors();
        $event = Event::latest('id')->first();

        expect(app(PaymentAccountService::class)->onlineMethodsForEvent($event))->toBe([]);
    });
});
