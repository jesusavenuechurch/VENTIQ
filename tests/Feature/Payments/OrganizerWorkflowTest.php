<?php

use App\Models\{Client, Event, EventTier, Organization, OrganizationPaymentMethod, Ticket, TicketPayment, User};
use App\Notifications\Payments\{AttendeeTicketNotice, PaymentSubmittedNotification};
use App\Notifications\TicketRegistrationNotification;
use App\Services\Notifications\OrganizerNotifier;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\{Bus, Hash, Mail, Notification, URL};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    Notification::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'password' => Hash::make('secret123')]);
    $this->admin->assignRole('org_admin');

    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Maseru Youth Summit', 'slug' => 'summit-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'is_public' => true,
    ]);
    $this->tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'Standard', 'price' => 250, 'is_active' => true]);
    $this->account = OrganizationPaymentMethod::create([
        'organization_id' => $this->org->id, 'payment_method' => 'ecocash',
        'account_name' => 'Events Account', 'account_number' => '62500000', 'is_active' => true,
    ]);
});

function workflowTicket(object $t, array $ticket = [], ?array $payment = []): Ticket
{
    $client = Client::create(['organization_id' => $t->org->id, 'full_name' => 'Lerato Mokoena', 'email' => 'lerato@example.com', 'phone' => '+2665' . random_int(1000000, 9999999)]);
    $model = Ticket::create(array_merge([
        'event_id' => $t->event->id, 'client_id' => $client->id, 'event_tier_id' => $t->tier->id,
        'status' => 'pending', 'payment_status' => 'pending', 'amount' => 250,
    ], $ticket));

    if ($payment !== null) {
        TicketPayment::create(array_merge([
            'ticket_id' => $model->id, 'amount' => 250, 'status' => 'pending', 'payment_type' => 'full',
        ], $payment));
    }

    return $model;
}

function submittedPayment(object $t, array $payment = [], array $ticket = []): TicketPayment
{
    $ticket = workflowTicket($t, $ticket, array_merge([
        'payment_method' => 'ecocash', 'organization_payment_method_id' => $t->account->id,
        'payment_reference' => 'ABC123', 'submitted_at' => now(), 'source' => 'organizer_direct',
    ], $payment));

    return $ticket->payments()->first();
}

describe('getting into the organizer area', function () {
    it('sends org users to the organizer area after login', function () {
        $this->postJson('/login', ['email' => $this->admin->email, 'password' => 'secret123', 'intent' => 'host'])
            ->assertOk()
            ->assertJsonPath('redirect', route('organizer.home'));
    });

    it('moves org users off the Filament dashboard', function () {
        $this->actingAs($this->admin)->get('/admin')->assertRedirect(route('organizer.home'));
        $this->get(route('filament.admin.events.resources.events.create'))->assertOk();
    });

    it('keeps super admins on the Filament dashboard', function () {
        $super = User::factory()->create(['organization_id' => null]);
        $super->assignRole('super_admin');

        $this->actingAs($super)->get('/admin')->assertOk();
    });

    it('points the home page Host link at the organizer area', function () {
        $this->actingAs($this->admin)->get('/')->assertSee(route('organizer.home'), false);
    });

    it('sends new organizations to email verification, then the organizer area', function () {
        $this->postJson('/org/register', [
            'org_name' => 'New Org ' . Str::random(4), 'org_phone' => '+26650001111',
            'user_name' => 'Thabo', 'user_email' => 'thabo' . Str::random(4) . '@example.com',
            'user_password' => 'Secret-Pass-123', 'user_password_confirmation' => 'Secret-Pass-123',
        ])->assertOk()->assertJsonPath('redirect', route('verification.notice'));

        $this->get(route('verification.notice'))->assertOk()->assertSee('Check your email');
        $this->get(route('organizer.home'))->assertRedirect(route('verification.notice'));
    });

    it('shows the org its events and how many payments wait for it', function () {
        submittedPayment($this);

        $this->actingAs($this->admin)->get(route('organizer.home'))
            ->assertOk()
            ->assertSee('Maseru Youth Summit')
            ->assertSee('1 payment waiting for you to confirm');
    });
});

describe('super admin access', function () {
    beforeEach(function () {
        $this->super = User::factory()->create(['organization_id' => null]);
        $this->super->assignRole('super_admin');
    });

    it('sends a super admin without an organization picked back to Filament', function () {
        $this->actingAs($this->super)->get(route('organizer.home'))
            ->assertRedirect(route('filament.admin.organization.resources.organizations.index'));
    });

    it('lets a super admin open and leave an organization\'s organizer area', function () {
        $this->actingAs($this->super)->get(route('organizer.act-as.start', $this->org))->assertRedirect(route('organizer.home'));

        $this->get(route('organizer.home'))->assertOk()->assertSee('Super admin view of')->assertSee($this->org->name);

        $payment = submittedPayment($this);
        $this->post(route('organizer.payments.decide', $payment), ['decision' => 'activate'])->assertRedirect();
        expect($payment->ticket->fresh()->status)->toBe('active');

        $this->post(route('organizer.act-as.stop'))->assertRedirect(route('filament.admin.organization.resources.organizations.index'));
        $this->get(route('organizer.home'))->assertRedirect(route('filament.admin.organization.resources.organizations.index'));
    });

    it('refuses act-as to anyone but a super admin', function () {
        $this->actingAs($this->admin)->get(route('organizer.act-as.start', Organization::factory()->create()))->assertForbidden();
    });
});

describe('confirming payments', function () {
    it('lists only this organization\'s submitted payments', function () {
        submittedPayment($this, ['payment_reference' => 'MINE-1']);
        workflowTicket($this); // registered, nothing submitted

        $other = Organization::factory()->create();
        $otherEvent = Event::create(['organization_id' => $other->id, 'name' => 'Other', 'slug' => 'o-' . Str::random(5), 'event_date' => now()->addWeek()]);
        $otherTier = EventTier::create(['event_id' => $otherEvent->id, 'tier_name' => 'S', 'price' => 10]);
        $otherClient = Client::create(['organization_id' => $other->id, 'full_name' => 'X', 'phone' => '+26651112222']);
        $otherTicket = Ticket::create(['event_id' => $otherEvent->id, 'client_id' => $otherClient->id, 'event_tier_id' => $otherTier->id, 'status' => 'pending', 'payment_status' => 'pending', 'amount' => 10]);
        TicketPayment::create(['ticket_id' => $otherTicket->id, 'amount' => 10, 'status' => 'pending', 'payment_reference' => 'THEIRS-1', 'submitted_at' => now(), 'payment_type' => 'full']);

        $this->actingAs($this->admin)->get(route('organizer.payments.index'))
            ->assertOk()
            ->assertSee('MINE-1')
            ->assertSee('EcoCash — Events Account')
            ->assertDontSee('THEIRS-1');
    });

    it('activates a ticket when the organizer says the money arrived', function () {
        $payment = submittedPayment($this);

        $this->actingAs($this->admin)->post(route('organizer.payments.decide', $payment), ['decision' => 'activate'])
            ->assertRedirect()->assertSessionHas('status');

        expect($payment->ticket->fresh()->status)->toBe('active')
            ->and($payment->fresh()->status)->toBe('approved')
            ->and($payment->fresh()->approved_by)->toBe($this->admin->id);
    });

    it('records a deposit without activating, then activates with a balance due', function () {
        $first = submittedPayment($this, ['amount' => 100, 'payment_type' => 'deposit']);
        $ticket = $first->ticket;

        $this->actingAs($this->admin)->post(route('organizer.payments.decide', $first), ['decision' => 'deposit']);
        $ticket->refresh();
        expect($ticket->status)->toBe('pending')
            ->and($ticket->payment_status)->toBe('partial')
            ->and((float) $ticket->amount_paid)->toBe(100.0);

        $second = TicketPayment::create(['ticket_id' => $ticket->id, 'amount' => 50, 'status' => 'pending', 'submitted_at' => now(), 'payment_type' => 'installment']);
        $this->post(route('organizer.payments.decide', $second), ['decision' => 'activate']);
        $ticket->refresh();
        expect($ticket->status)->toBe('active')
            ->and($ticket->payment_status)->toBe('partial')
            ->and((float) $ticket->amount_paid)->toBe(150.0)
            ->and($ticket->isValid())->toBeTrue()
            ->and($this->tier->fresh()->quantity_sold)->toBe(1);
    });

    it('rejects a payment, tells the attendee and restarts the payment window', function () {
        $this->event->update(['payment_window_hours' => 24]);
        $payment = submittedPayment($this);

        $this->actingAs($this->admin)->post(route('organizer.payments.decide', $payment), ['decision' => 'reject', 'reason' => 'No such reference']);

        expect($payment->fresh()->status)->toBe('rejected')
            ->and($payment->fresh()->notes)->toBe('No such reference')
            ->and($payment->ticket->fresh()->status)->toBe('pending')
            ->and($payment->ticket->fresh()->payment_due_at)->not->toBeNull();

        Notification::assertSentTo(new AnonymousNotifiable, AttendeeTicketNotice::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'lerato@example.com' && str_contains(implode(' ', $n->lines), 'No such reference'));
    });

    it('refuses decisions on other organizations\' payments and from team members without permission', function () {
        $payment = submittedPayment($this);

        $viewer = User::factory()->create(['organization_id' => $this->org->id]);
        $viewer->assignRole('viewer');
        $this->actingAs($viewer)->post(route('organizer.payments.decide', $payment), ['decision' => 'activate'])->assertForbidden();

        $outsider = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $outsider->assignRole('org_admin');
        $this->actingAs($outsider)->post(route('organizer.payments.decide', $payment), ['decision' => 'activate'])->assertNotFound();

        expect($payment->ticket->fresh()->status)->toBe('pending');
    });
});

describe('the review link', function () {
    it('opens without login from a signed link and activates the ticket', function () {
        $payment = submittedPayment($this);
        $url = app(OrganizerNotifier::class)->reviewUrl($payment);

        $page = $this->get($url)->assertOk()->assertSee('Has this payment been received?')->assertSee('ABC123');

        preg_match('/action="([^"]+payment-review[^"]+)"/', $page->getContent(), $m);
        $this->post(html_entity_decode($m[1]), ['decision' => 'activate'])->assertOk()->assertSee('Ticket activated');

        expect($payment->ticket->fresh()->status)->toBe('active')
            ->and($payment->fresh()->approved_by)->toBeNull();

        $this->post(html_entity_decode($m[1]), ['decision' => 'reject'])->assertSee('already been dealt with');
        expect($payment->fresh()->status)->toBe('approved');
    });

    it('refuses an unsigned or tampered link', function () {
        $payment = submittedPayment($this);

        $this->get("/payment-review/{$payment->id}")->assertForbidden();
        $this->post("/payment-review/{$payment->id}", ['decision' => 'activate'])->assertForbidden();
        $this->get(URL::temporarySignedRoute('payment-review.show', now()->subMinute(), ['payment' => $payment->id]))->assertForbidden();
    });
});

describe('notifications', function () {
    it('tells the organizer when a payment is submitted, not when someone registers', function () {
        $this->post("/register/{$this->org->slug}/{$this->event->slug}", [
            'tier_id' => $this->tier->id, 'full_name' => 'Palesa', 'phone' => '+26650009999', 'terms' => '1',
        ]);
        Notification::assertNothingSent();

        $ticket = Ticket::latest('id')->first();
        $this->post("/register/{$this->org->slug}/{$this->event->slug}/payment/{$ticket->id}/manual", [
            'payment_method_id' => $this->account->id, 'payment_reference' => 'REF77',
        ]);

        Notification::assertSentTo($this->admin, PaymentSubmittedNotification::class,
            fn ($n) => str_contains($n->reviewUrl, '/payment-review/') && str_contains($n->reviewUrl, 'signature='));
        Notification::assertNotSentTo($this->admin, TicketRegistrationNotification::class);
    });
});

describe('tickets before they are active', function () {
    it('shows an inactive ticket instead of refusing it', function () {
        $ticket = workflowTicket($this, ['payment_due_at' => now()->addDay()]);

        $this->get(route('ticket.download', $ticket->qr_code))
            ->assertOk()->assertSee('Inactive — awaiting payment')->assertSee('Pay by')->assertDontSee('Scan at Entrance');
    });

    it('says when an expired ticket has lost its place', function () {
        $ticket = workflowTicket($this, ['status' => 'expired']);

        $this->get(route('ticket.download', $ticket->qr_code))->assertOk()->assertSee('Payment window ended');
    });

    it('holds a place for unpaid tickets until they expire', function () {
        $this->tier->update(['quantity_available' => 1]);
        $held = workflowTicket($this);
        $register = fn () => $this->post("/register/{$this->org->slug}/{$this->event->slug}", [
            'tier_id' => $this->tier->id, 'full_name' => 'Late Comer', 'phone' => '+26650007777', 'terms' => '1',
        ]);

        $register()->assertSessionHas('error', 'Sorry, Standard is sold out.');
        expect(Ticket::count())->toBe(1);

        $held->update(['status' => 'expired']);
        $register();
        expect(Ticket::where('status', 'pending')->count())->toBe(1);
    });
});

describe('payment windows', function () {
    it('expires unpaid tickets past their deadline and tells the attendee', function () {
        $due = workflowTicket($this, ['payment_due_at' => now()->subMinute()]);
        $waitingOnOrganizer = submittedPayment($this, [], ['payment_due_at' => now()->subMinute()])->ticket;
        $notYet = workflowTicket($this, ['payment_due_at' => now()->addDay()]);

        $this->artisan('tickets:expire-unpaid')->assertSuccessful();

        expect($due->fresh()->status)->toBe('expired')
            ->and($waitingOnOrganizer->fresh()->status)->toBe('pending')
            ->and($notYet->fresh()->status)->toBe('pending');
        Notification::assertSentTo(new AnonymousNotifiable, AttendeeTicketNotice::class,
            fn ($n) => str_contains($n->subject, 'Payment window ended'));
    });

    it('sends one reminder once half the window has passed', function () {
        $ticket = workflowTicket($this, ['payment_due_at' => now()->addHours(10)]);
        $ticket->forceFill(['created_at' => now()->subHours(12)])->save();
        $fresh = workflowTicket($this, ['payment_due_at' => now()->addHours(47)]);

        $this->artisan('tickets:expire-unpaid');
        $this->artisan('tickets:expire-unpaid');

        expect($ticket->fresh()->payment_reminder_sent_at)->not->toBeNull()
            ->and($fresh->fresh()->payment_reminder_sent_at)->toBeNull();
        Notification::assertSentTimes(AttendeeTicketNotice::class, 1);
    });

    it('lets the organizer reinstate an expired ticket or cancel an unpaid one', function () {
        $this->event->update(['payment_window_hours' => 24]);
        $expired = workflowTicket($this, ['status' => 'expired']);
        $unpaid = workflowTicket($this);

        $this->actingAs($this->admin)->get(route('organizer.events.attendees', [$this->event, 'filter' => 'expired']))
            ->assertOk()->assertSee('Reinstate');

        $this->post(route('organizer.tickets.reinstate', $expired));
        expect($expired->fresh()->status)->toBe('pending')
            ->and($expired->fresh()->payment_due_at)->not->toBeNull();

        $this->post(route('organizer.tickets.cancel', $unpaid));
        expect($unpaid->fresh()->status)->toBe('cancelled');
    });
});
