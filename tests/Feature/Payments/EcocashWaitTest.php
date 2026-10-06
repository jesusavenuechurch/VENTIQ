<?php

use App\Jobs\ChargeMobileMoney;
use App\Models\{Client, Event, EventTier, Organization, OrganizationPaymentMethod, PaymentSession, SettlementItem, Ticket, TicketPayment, User};
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Bus, Http, Mail, Storage};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake([ChargeMobileMoney::class]);
    Mail::fake();
    Storage::fake('local');
    config([
        'gateways.paylesotho.enabled' => true, 'gateways.paylesotho.ecocash.enabled' => true,
        'gateways.paylesotho.ecocash.waits_for_pin' => true,
        'gateways.paylesotho.ecocash.merchant_id' => '62243375', 'gateways.paylesotho.ecocash.merchant_code' => '62243375',
        'gateways.paylesotho.ecocash.merchant_name' => 'SDAandIT',
    ]);

    $this->org = Organization::factory()->create();
    OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'online', 'is_active' => true]);
    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid', 'is_public' => true,
    ]);
    $this->tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 200, 'is_active' => true]);
    $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Lerato', 'phone' => '+26659494756']);
    $this->ticket = Ticket::create([
        'event_id' => $this->event->id, 'client_id' => $client->id, 'event_tier_id' => $this->tier->id,
        'status' => 'pending', 'payment_status' => 'pending', 'amount' => 200, 'amount_paid' => 0,
    ]);
    TicketPayment::create(['ticket_id' => $this->ticket->id, 'amount' => 200, 'status' => 'pending', 'payment_type' => 'full']);

    $this->pay = fn () => $this->postJson(route('ticket.pay.online', $this->ticket->qr_code), [
        'method' => 'ecocash', 'mobile_number' => '+26662552155',
    ]);
    // Run the background wait the way it runs after the response.
    $this->answer = function (PaymentSession $session) {
        (new ChargeMobileMoney($session->id, '+26662552155'))->handle(app(\App\Services\Payments\PaymentGatewayFactory::class), app(\App\Services\Payments\PaymentCompletion::class));

        return $session->fresh();
    };
    $this->paymentPage = route('ticket.pay', $this->ticket->qr_code);
});

it('sends the push in the background and activates the ticket on a 200', function () {
    Http::fake(['*/api/v2/econet/payment' => Http::response(['status_code' => '200', 'amount' => '200', 'transaction_reference' => 'PL-1', 'reference' => 'PL-1', 'message' => 'Transaction processed successfully'])]);

    ($this->pay)()->assertOk()->assertJson(['status' => 'pending', 'attempts_left' => 2, 'wait_seconds' => 90]);
    Bus::assertDispatchedAfterResponse(ChargeMobileMoney::class);

    $session = ($this->answer)(PaymentSession::sole());

    Http::assertSent(fn ($r) => $r['mobileNumber'] === '62552155' && $r['amount'] === '200' && $r['merchantid'] === '62243375' && $r['client_reference'] === $session->client_reference);
    expect($session->status)->toBe('completed')
        ->and($session->transaction_id)->toBe('PL-1')
        ->and($this->ticket->fresh()->status)->toBe('active')
        ->and(SettlementItem::where('payment_session_id', $session->id)->exists())->toBeTrue();
    $this->getJson(route('ticket.pay.status', [$this->ticket->qr_code, $session]))->assertJson(['status' => 'completed']);
});

it('tells the attendee why on a 415, and counts the try', function () {
    Http::fake(['*' => Http::response(['status_code' => '415', 'message' => 'This error is caused by Insufficient Balance or wrong ecocash number provided or missing field input'], 415)]);

    ($this->pay)();
    $session = ($this->answer)(PaymentSession::sole());

    expect($session->status)->toBe('failed')->and($this->ticket->fresh()->status)->toBe('pending');
    $this->getJson(route('ticket.pay.status', [$this->ticket->qr_code, $session]))
        ->assertJson(['status' => 'failed', 'attempts_left' => 2])
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'balance may be too low'));
});

it('leaves a push with no answer for a person to check', function () {
    Http::fake(fn () => throw new ConnectionException('Operation timed out'));

    ($this->pay)();
    $session = ($this->answer)(PaymentSession::sole());

    expect($session->status)->toBe('pending')->and($session->callback_payload['no_answer'])->toBeTrue();
});

it('never pays more than once for an amount that differs', function () {
    Http::fake(['*' => Http::response(['status_code' => '200', 'amount' => '1', 'reference' => 'PL-2'])]);
    ($this->pay)();

    expect(($this->answer)(PaymentSession::sole())->status)->toBe('pending')
        ->and($this->ticket->fresh()->status)->toBe('pending');
});

it('follows the push on the phone, then allows 3 tries and no more', function () {
    ($this->pay)()->assertOk();
    ($this->pay)()->assertOk();
    expect(PaymentSession::count())->toBe(1);   // still on the phone: no second push

    $this->travel(91)->seconds();
    ($this->pay)()->assertJson(['attempts_left' => 1]);
    $this->travel(91)->seconds();
    ($this->pay)()->assertJson(['attempts_left' => 0]);
    $this->travel(91)->seconds();
    ($this->pay)()->assertStatus(429)->assertJson(['attempts_left' => 0]);

    expect(PaymentSession::count())->toBe(3);
    $this->get($this->paymentPage)->assertOk()->assertSee("Let's try another way", false)->assertSee('62243375');
});

it('takes a payment to VENTIQ\'s merchant by hand and lets VENTIQ confirm it', function () {
    $this->post(route('ticket.pay.merchant', $this->ticket->qr_code), [
        'merchant_phone' => '62552155', 'proof' => UploadedFile::fake()->image('ecocash.png'),
    ])->assertRedirect();

    $session = PaymentSession::sole();
    expect($session->status)->toBe('pending')->and((float) $session->amount)->toBe(200.0)
        ->and($session->purchase_meta)->toBe(['by_hand' => true]);
    Storage::disk('local')->assertExists($session->callback_payload['by_hand']['proof_path']);
    $this->get($this->paymentPage)->assertSee("We're checking your payment", false);

    // It doesn't use up a try.
    expect(\App\Http\Controllers\PayLesothoController::attemptsLeft($this->ticket))->toBe(3);

    $super = User::factory()->create(['organization_id' => null]);
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $super->assignRole('super_admin');
    $this->actingAs($super)->get(route('ventiq.money.index'))->assertSee('Paid the merchant by hand from 62552155')->assertSee('screenshot');
    $this->get(route('ventiq.money.online.proof', $session))->assertOk();

    $this->withoutExceptionHandling()->post(route('ventiq.money.online.decide', $session), ['decision' => 'received']);
    expect($this->ticket->fresh()->status)->toBe('active');
});

it('tells VENTIQ by email and WhatsApp when someone pays the merchant by hand', function () {
    \Illuminate\Support\Facades\Notification::fake();
    Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
    config(['constants.ventiq_alerts' => ['email' => 'support@ventiq.co.ls', 'whatsapp' => '+26662552155'], 'services.whatsapp.access_token' => 't', 'services.whatsapp.phone_number_id' => '1']);

    $this->post(route('ticket.pay.merchant', $this->ticket->qr_code), ['merchant_phone' => '59494756', 'merchant_reference' => 'MP1.2.3'])->assertRedirect();

    \Illuminate\Support\Facades\Notification::assertSentOnDemand(\App\Notifications\Ventiq\MerchantPaymentClaimed::class,
        fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'support@ventiq.co.ls'
            && str_contains($n->toMail($notifiable)->subject, 'M200.00'));
    Http::assertSent(fn ($r) => str_contains($r->body(), '26662552155') && str_contains($r->body(), 'payment_submitted') && str_contains($r->body(), 'MP1.2.3'));
});

it('opens the pay page on paying another way from the message link', function () {
    $this->get(route('ticket.pay.another', $this->ticket->qr_code))->assertOk()
        ->assertSee("Let's try another way", false)->assertSee('62243375')->assertSee('Or try the payment prompt again');
});

it('needs a reference or a screenshot to pay the merchant by hand', function () {
    $this->post(route('ticket.pay.merchant', $this->ticket->qr_code), [
        'merchant_phone' => '62552155',
    ])->assertSessionHasErrors('merchant_reference');
});

it('lists money paid for a ticket that was already paid, to refund', function () {
    Http::fake(['*' => Http::response(['status_code' => '200', 'amount' => '200', 'reference' => 'PL-' . Str::random(4)])]);
    ($this->pay)();
    ($this->answer)(PaymentSession::sole());

    // A second push, sent before the first answer came, is paid too.
    $second = PaymentSession::create([
        'payable_type' => 'ticket', 'payable_id' => $this->ticket->id, 'gateway' => 'paylesotho', 'client_reference' => 'ECOCASH' . $this->ticket->id . 'T1234567890',
        'payment_method' => 'ecocash', 'amount' => 200, 'status' => 'pending', 'organization_id' => $this->org->id,
    ]);
    $second = ($this->answer)($second);

    expect($second->status)->toBe('completed')->and($second->callback_payload['paid_twice'])->toBeTrue()
        ->and(SettlementItem::count())->toBe(1);

    $super = User::factory()->create(['organization_id' => null]);
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $super->assignRole('super_admin');
    $this->actingAs($super)->get(route('ventiq.money.index'))->assertSee('Paid twice: refund these');
    $this->post(route('ventiq.money.online.refunded', $second), ['reference' => 'RF1'])->assertSessionHas('status');
    $this->withoutExceptionHandling()->get(route('ventiq.money.index'))->assertDontSee('Paid twice: refund these');
});

it('accepts a screenshot instead of a reference when paying the organizer', function () {
    $account = OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'ecocash', 'account_number' => '62000000', 'is_active' => true]);

    $this->post(route('ticket.pay.manual', $this->ticket->qr_code), [
        'payment_method_id' => $account->id, 'payment_type' => 'full', 'proof' => UploadedFile::fake()->image('paid.jpg'),
    ])->assertRedirect();

    $payment = $this->ticket->payments()->latest('id')->first();
    expect($payment->proof_path)->not->toBeNull();

    $admin = User::factory()->create(['organization_id' => $this->org->id]);
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $admin->assignRole('org_admin');
    $this->actingAs($admin)->get(route('organizer.payments.index'))->assertSee('View screenshot');
    $this->get(route('organizer.payments.proof', $payment))->assertOk();
});

it('picks up the unpaid ticket when the same number registers again for the same type', function () {
    $register = fn () => $this->post("/register/{$this->org->slug}/{$this->event->slug}", [
        'tier_id' => $this->tier->id, 'full_name' => 'Lerato M', 'phone' => '+26659494756', 'terms' => '1',
    ]);

    $register()->assertRedirect($this->paymentPage);
    expect(Ticket::count())->toBe(1)->and($this->ticket->fresh()->holder_name)->toBe('Lerato M')
        ->and($this->ticket->client->fresh()->full_name)->toBe('Lerato');   // the contact isn't renamed

    // Once it's paid, a new registration is a new ticket.
    $this->ticket->update(['status' => 'active', 'payment_status' => 'completed']);
    $register();
    expect(Ticket::count())->toBe(2);
});
