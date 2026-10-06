<?php

use App\Models\{Client, Event, EventTier, Organization, OrganizationPaymentMethod, Ticket, TicketPayment};
use Illuminate\Support\Facades\{Bus, Mail};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    $this->org = Organization::factory()->create();
    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid', 'is_public' => true,
    ]);
    $this->tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 200, 'is_active' => true]);
    $this->form = route('registration.form', [$this->org->slug, $this->event->slug]);
    $this->register = fn (array $data = []) => $this->from($this->form)->post("/register/{$this->org->slug}/{$this->event->slug}", array_merge([
        'tier_id' => $this->tier->id, 'full_name' => 'Lerato Mokoena', 'phone' => '59494756', 'terms' => '1',
    ], $data));
});

it('shows why a registration didn\'t go through', function () {
    $this->tier->update(['quantity_available' => 1]);
    ($this->register)(['phone' => '50000001']);

    ($this->register)(['phone' => '50000002'])->assertRedirect($this->form)->assertSessionHas('error');
    $this->get($this->form . '?tier=' . $this->tier->id)->assertSee('sold out');
});

it('keeps the registration when a message fails to send after it is saved', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

    $r = ($this->register)(['email' => 'lerato@example.com']);

    $ticket = Ticket::sole();
    $r->assertRedirect(route('ticket.pay', $ticket->qr_code));
    expect(collect($ticket->fresh()->delivery_log)->pluck('method'))->toContain('registration');
});

it('shows the number again without a second +266 after an error', function () {
    ($this->register)(['terms' => null])->assertSessionHasErrors('terms');

    $this->get($this->form . '?tier=' . $this->tier->id)->assertSee('value="59494756"', false);
});

it('keeps each person\'s name when two register with one phone, and never wipes an email', function () {
    ($this->register)(['email' => 'lerato@example.com']);
    $second = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'VIP', 'price' => 500, 'is_active' => true]);
    ($this->register)(['tier_id' => $second->id, 'full_name' => 'Thabo Mokoena']);

    [$lerato, $thabo] = Ticket::orderBy('id')->get();
    expect($lerato->holder_name)->toBe('Lerato Mokoena')
        ->and($thabo->holder_name)->toBe('Thabo Mokoena')
        ->and(Client::sole()->email)->toBe('lerato@example.com');

    $this->get(route('ticket.pay', $thabo->qr_code))->assertOk();
});

it('checks workshop details on the server', function () {
    $this->event->update(['event_type' => 'workshop']);

    ($this->register)()->assertSessionHasErrors(['position', 'institution', 'district']);
    expect(Ticket::count())->toBe(0);
});

it('finds a ticket by phone and entry code, and shows the link to keep', function () {
    ($this->register)();
    $ticket = Ticket::sole();

    $this->post(route('installment.find'), ['ticket_number' => strtolower($ticket->voucher_code), 'phone' => '5949 4756'])
        ->assertRedirect(route('ticket.pay', $ticket->qr_code));
    $this->post(route('installment.find'), ['ticket_number' => $ticket->voucher_code, 'phone' => '50000000'])->assertSessionHasErrors('ticket_number');

    $this->get(route('ticket.pay', $ticket->qr_code))->assertSee('Your ticket link: save it')
        ->assertSee(route('ticket.download', $ticket->qr_code))->assertSee($ticket->voucher_code);
    $this->get(route('ticket.find'))->assertOk()->assertSee('entry code');
    $this->get(route('terms'))->assertOk()->assertSee('Ticket terms');
});

it('shows the balance and the account paid to on the confirmation page', function () {
    ($this->register)();
    $ticket = Ticket::sole();
    $ticket->update(['amount_paid' => 60, 'payment_status' => 'partial']);
    $account = OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'ecocash', 'account_name' => 'Events', 'account_number' => '62000000', 'is_active' => true]);
    $ticket->payments()->first()->update(['organization_payment_method_id' => $account->id, 'submitted_at' => now()]);

    $this->get(route('ticket.registered', $ticket->qr_code))->assertSee('Balance left')->assertSee('M140.00')->assertSee('62000000');
});
