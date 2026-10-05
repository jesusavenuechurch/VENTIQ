<?php

use App\Models\{Client, Event, EventTier, Organization, OrganizationPaymentMethod, Ticket, TicketPayment};
use App\Services\Payments\PaymentAccountService;
use Illuminate\Support\Facades\{Bus, Mail};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();
    Mail::fake();

    $this->org = Organization::factory()->create();
    $this->event = Event::create([
        'organization_id' => $this->org->id,
        'name'            => 'Maseru Youth Summit',
        'slug'            => 'summit-' . Str::random(6),
        'event_date'      => now()->addMonth(),
        'status'          => 'published',
        'is_public'       => true,
    ]);
    $this->tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'Standard', 'price' => 250, 'is_active' => true]);

    $account = fn (array $attrs) => OrganizationPaymentMethod::create(array_merge([
        'organization_id' => $this->org->id, 'payment_method' => 'ecocash', 'is_active' => true,
    ], $attrs));

    $this->eventsAccount = $account(['account_name' => 'Events Account', 'account_number' => '62500000', 'instructions' => 'Pay the Events line', 'is_default' => true]);
    $this->mainAccount   = $account(['account_name' => 'Main Account', 'account_number' => '58000000', 'instructions' => 'Pay the Main line']);
    $this->fnb           = $account(['payment_method' => 'bank_transfer', 'account_name' => 'Conference Account', 'account_number' => '6200123']);
    $this->online        = OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'online', 'account_number' => 'VENTIQ', 'is_active' => true]);

    $this->accounts = app(PaymentAccountService::class);
});

function accountTicket(object $t): Ticket
{
    $client = Client::create(['organization_id' => $t->org->id, 'full_name' => 'Palesa', 'phone' => '+2665' . random_int(1000000, 9999999)]);
    $ticket = Ticket::create([
        'event_id' => $t->event->id, 'client_id' => $client->id, 'event_tier_id' => $t->tier->id,
        'status' => 'pending', 'payment_status' => 'pending', 'amount' => 250,
    ]);
    TicketPayment::create(['ticket_id' => $ticket->id, 'amount' => 250, 'status' => 'pending', 'payment_type' => 'full']);

    return $ticket;
}

function manualPaymentUrl(object $t, Ticket $ticket): string
{
    return "/ticket/{$ticket->qr_code}/pay/manual";
}

it('keeps one default per method', function () {
    $this->mainAccount->update(['is_default' => true]);

    expect($this->eventsAccount->fresh()->is_default)->toBeFalse()
        ->and($this->mainAccount->fresh()->is_default)->toBeTrue()
        ->and($this->fnb->fresh()->is_default)->toBeFalse();
});

it('starts new events with the default accounts, or all accounts when none is default', function () {
    expect($this->accounts->defaultAccountIds($this->org))->toBe([$this->eventsAccount->id]);

    $this->eventsAccount->update(['is_default' => false]);

    expect($this->accounts->defaultAccountIds($this->org))
        ->toEqualCanonicalizing([$this->eventsAccount->id, $this->mainAccount->id, $this->fnb->id]);
});

it('locks the number of an account that has received payments', function () {
    $ticket = accountTicket($this);
    $ticket->payments()->update(['organization_payment_method_id' => $this->eventsAccount->id]);

    $this->eventsAccount->update(['account_name' => 'Events (renamed)']);
    expect($this->eventsAccount->fresh()->account_name)->toBe('Events (renamed)');

    expect(fn () => $this->eventsAccount->fresh()->update(['account_number' => '62599999']))->toThrow(Exception::class);
    expect(fn () => $this->eventsAccount->fresh()->delete())->toThrow(Exception::class);

    $this->eventsAccount->fresh()->update(['is_active' => false]);
    expect($this->eventsAccount->fresh()->is_active)->toBeFalse()
        ->and($this->eventsAccount->fresh()->account_number)->toBe('62500000');
});

it('offers every active account on events that never chose', function () {
    config(['gateways.paylesotho.mpesa.enabled' => true]);   // both merchants live

    expect($this->event->enabled_payment_method_ids)->toBeNull()
        ->and($this->accounts->directAccountsForEvent($this->event)->pluck('id')->all())
        ->toEqualCanonicalizing([$this->eventsAccount->id, $this->mainAccount->id, $this->fnb->id])
        ->and($this->accounts->onlineMethodsForEvent($this->event))->toBe(['ecocash', 'mpesa']);
});

it('offers only the accounts and online option the event chose', function () {
    $this->event->update(['enabled_payment_method_ids' => [$this->fnb->id]]);

    expect($this->accounts->directAccountsForEvent($this->event->fresh())->pluck('id')->all())->toBe([$this->fnb->id])
        ->and($this->accounts->onlineMethodsForEvent($this->event->fresh()))->toBe([]);

    $this->event->update(['enabled_payment_method_ids' => [$this->online->id, $this->eventsAccount->id]]);
    config(['gateways.paylesotho.mpesa.enabled' => false]);

    expect($this->accounts->onlineMethodsForEvent($this->event->fresh()))->toBe(['ecocash']);
});

it('shows attendees only the event\'s accounts, labelled by account name', function () {
    $this->event->update(['enabled_payment_method_ids' => [$this->online->id, $this->eventsAccount->id, $this->fnb->id]]);
    $ticket = accountTicket($this);

    $this->get("/ticket/{$ticket->qr_code}/pay")
        ->assertOk()
        ->assertSee('EcoCash — Events Account')
        ->assertSee('Conference Account')
        ->assertDontSee('Main Account')
        ->assertSee('pay directly to the organizer')
        ->assertSee('Payment processed securely through VENTIQ');
});

it('accepts a payment to an older active account but not to an archived one', function () {
    $this->event->update(['enabled_payment_method_ids' => [$this->fnb->id]]);
    $ticket = accountTicket($this);

    $this->post(manualPaymentUrl($this, $ticket), ['payment_method_id' => $this->mainAccount->id, 'payment_reference' => 'OLD-1'])
        ->assertSessionHasNoErrors();
    expect($ticket->payments()->pending()->first()->organization_payment_method_id)->toBe($this->mainAccount->id);

    $this->mainAccount->update(['is_active' => false]);
    $this->post(manualPaymentUrl($this, $ticket), ['payment_method_id' => $this->mainAccount->id, 'payment_reference' => 'OLD-2'])
        ->assertNotFound();
});

it('shows the instructions of the account actually paid into', function () {
    $ticket = accountTicket($this);

    $this->post(manualPaymentUrl($this, $ticket), ['payment_method_id' => $this->mainAccount->id, 'payment_reference' => 'REF9']);

    $this->get("/ticket/{$ticket->qr_code}/registered")
        ->assertOk()
        ->assertSee('Pay the Main line')
        ->assertDontSee('Pay the Events line');
});

it('refuses online payment when the event does not offer it', function () {
    $this->event->update(['enabled_payment_method_ids' => [$this->fnb->id]]);
    $ticket = accountTicket($this);

    $this->postJson("/ticket/{$ticket->qr_code}/pay/online", [
        'method' => 'ecocash', 'mobile_number' => '+26650001234',
    ])->assertUnprocessable()->assertJsonPath('message', 'Online payment is not available for this event.');
});

it('counts the attendees who have not paid yet', function () {
    $waiting = accountTicket($this);
    $submitted = accountTicket($this);
    $submitted->payments()->update(['submitted_at' => now(), 'payment_method' => 'ecocash']);

    expect($this->accounts->unpaidTicketCount($this->event))->toBe(1);
});
