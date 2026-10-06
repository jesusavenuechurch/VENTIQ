<?php

use App\Jobs\ChargeMobileMoney;
use App\Models\{Client, Event, EventTier, Organization, OrganizationPaymentMethod, PaymentSession, Ticket, TicketPayment, User};
use App\Notifications\Payments\AttendeeTicketNotice;
use Illuminate\Support\Facades\{Bus, Mail, Notification};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake([ChargeMobileMoney::class]);
    Mail::fake();
    Notification::fake();
    config(['gateways.paylesotho.enabled' => true, 'gateways.paylesotho.ecocash.enabled' => true]);

    $this->org = Organization::factory()->create();
    OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'online', 'is_active' => true]);
    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid', 'is_public' => true, 'allow_installments' => true,
    ]);
    $this->tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 200, 'is_active' => true]);
    $this->makeTicket = function (array $attrs = []) {
        $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Lerato Mokoena', 'email' => 'lerato@example.com', 'phone' => '+2665' . random_int(1000000, 9999999)]);

        return tap(Ticket::create(array_merge([
            'event_id' => $this->event->id, 'client_id' => $client->id, 'event_tier_id' => $this->tier->id,
            'status' => 'pending', 'payment_status' => 'pending', 'amount' => 200, 'amount_paid' => 0, 'payment_due_at' => now()->addDay(),
        ], $attrs)), fn ($t) => TicketPayment::create(['ticket_id' => $t->id, 'amount' => 200, 'status' => 'pending', 'payment_type' => 'full']));
    };
    $this->ticket = ($this->makeTicket)();
});

it('sends a new registration to its private link', function () {
    $this->post("/register/{$this->org->slug}/{$this->event->slug}", [
        'tier_id' => $this->tier->id, 'full_name' => 'Thabo', 'phone' => '+26650001111', 'terms' => '1',
    ])->assertRedirect(route('ticket.pay', Ticket::latest('id')->first()->qr_code));

    $this->get(route('ticket.pay', Ticket::latest('id')->first()->qr_code))->assertOk()->assertSee('Pay Online');
});

it('shows nothing at an old numbered link, and only emails the link to the ticket\'s owner', function () {
    $old = "/register/{$this->org->slug}/{$this->event->slug}/payment/{$this->ticket->id}";

    $this->get($old)->assertOk()->assertSee('new private link')->assertSee('l•••')
        ->assertDontSee('Lerato')->assertDontSee($this->ticket->qr_code)->assertDontSee('lerato@example.com');
    $this->get("/register/{$this->org->slug}/{$this->event->slug}/confirmation/{$this->ticket->id}")->assertOk()->assertDontSee('Lerato');
    $this->get("/installment/{$this->ticket->id}")->assertOk()->assertDontSee('Lerato');

    $this->post(route('ticket.link.send', $this->ticket->id))->assertSessionHas('status');
    $this->post(route('ticket.link.send', $this->ticket->id));
    Notification::assertSentOnDemandTimes(AttendeeTicketNotice::class, 1);
    Notification::assertSentOnDemand(AttendeeTicketNotice::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'lerato@example.com'
        && str_contains($n->actionUrl, $this->ticket->qr_code));
});

it('starts and follows a push only through the ticket\'s private link', function () {
    $other = ($this->makeTicket)();

    $this->postJson('/ticket/QR-not-a-ticket/pay/online', ['method' => 'ecocash', 'mobile_number' => '+26659494756'])->assertNotFound();
    $this->postJson(route('ticket.pay.online', $this->ticket->qr_code), ['method' => 'ecocash', 'mobile_number' => '+26659494756'])->assertOk();
    $session = PaymentSession::sole();

    $this->getJson(route('paylesotho.status', $session))->assertNotFound();
    $this->getJson(route('ticket.pay.status', [$other->qr_code, $session]))->assertNotFound();
    $this->getJson(route('ticket.pay.status', [$this->ticket->qr_code, $session]))->assertOk()->assertJson(['status' => 'pending']);
});

it('serves QR images and passes only for tickets that scan', function () {
    $this->get(route('ticket.qr', $this->ticket->qr_code))->assertNotFound();
    $this->get(route('ticket.avatar.download', $this->ticket->qr_code))->assertNotFound();
    expect($this->ticket->qr_code_url)->toBeNull();
});

it('finds a ticket for its balance by number and phone, and takes the balance only', function () {
    $this->ticket->update(['amount_paid' => 60, 'payment_status' => 'partial']);

    $this->post(route('installment.find'), ['ticket_number' => $this->ticket->ticket_number, 'phone' => '50000000'])->assertSessionHasErrors('ticket_number');
    $this->post(route('installment.find'), ['ticket_number' => $this->ticket->ticket_number, 'phone' => substr($this->ticket->client->phone, 4)])
        ->assertRedirect(route('ticket.pay', $this->ticket->qr_code));

    $account = OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'ecocash', 'account_number' => '62000000', 'is_active' => true]);
    $this->get(route('ticket.pay', $this->ticket->qr_code))->assertSee('M140.00');
    $this->post(route('ticket.pay.manual', $this->ticket->qr_code), ['payment_method_id' => $account->id, 'payment_type' => 'full', 'payment_reference' => 'EC1']);

    expect((float) $this->ticket->payments()->latest('id')->first()->amount)->toBe(140.0);
});

it('lets door devices look up entry codes for their own organization only', function () {
    $code = $this->ticket->generateVoucherCode();
    $this->ticket->update(['status' => 'active', 'payment_status' => 'completed']);

    $stranger = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $this->actingAs($stranger, 'sanctum')->postJson('/api/scanner/voucher/lookup', ['voucher_code' => $code])->assertNotFound();
    $this->actingAs($stranger, 'sanctum')->postJson('/api/scanner/voucher/checkin', ['voucher_code' => $code])->assertNotFound();

    $own = User::factory()->create(['organization_id' => $this->org->id]);
    $this->actingAs($own, 'sanctum')->postJson('/api/scanner/voucher/lookup', ['voucher_code' => $code])->assertOk()->assertJsonPath('ticket.client_name', 'Lerato Mokoena');
    $this->actingAs($own, 'sanctum')->postJson('/api/scanner/voucher/lookup', ['voucher_code' => $code, 'event_id' => $this->event->id + 99])->assertNotFound();
});

it('limits how often one phone number can register', function () {
    foreach (range(1, 7) as $i) {
        $r = $this->post("/register/{$this->org->slug}/{$this->event->slug}", [
            'tier_id' => EventTier::create(['event_id' => $this->event->id, 'tier_name' => "T{$i}", 'price' => 10, 'is_active' => true])->id,
            'full_name' => 'Thabo', 'phone' => '+26650002222', 'terms' => '1',
        ]);
    }
    $r->assertSessionHas('error', fn ($e) => str_contains($e, 'Too many registrations'));
    expect(Ticket::whereHas('client', fn ($q) => $q->where('phone', '+26650002222'))->count())->toBe(6);
});

it('follows up once on a failed payment the attendee walked away from', function () {
    $failed = PaymentSession::create([
        'payable_type' => 'ticket', 'payable_id' => $this->ticket->id, 'gateway' => 'paylesotho', 'client_reference' => 'R' . Str::random(8),
        'payment_method' => 'ecocash', 'amount' => 200, 'status' => 'failed', 'organization_id' => $this->org->id,
    ]);

    $this->artisan('tickets:payment-follow-ups');
    Notification::assertNothingSent();   // not yet: they may still be on the page

    $failed->forceFill(['created_at' => now()->subMinutes(11)])->save();
    $this->artisan('tickets:payment-follow-ups');
    $this->artisan('tickets:payment-follow-ups');
    Notification::assertSentOnDemandTimes(AttendeeTicketNotice::class, 1);
    Notification::assertSentOnDemand(AttendeeTicketNotice::class, fn ($n) => str_contains($n->subject, "didn't go through") && str_contains($n->actionUrl, "/ticket/{$this->ticket->qr_code}/pay"));

    // Someone who paid another way meanwhile isn't told.
    $paid = ($this->makeTicket)();
    PaymentSession::create([
        'payable_type' => 'ticket', 'payable_id' => $paid->id, 'gateway' => 'paylesotho', 'client_reference' => 'R' . Str::random(8),
        'payment_method' => 'ecocash', 'amount' => 200, 'status' => 'failed', 'organization_id' => $this->org->id,
    ])->forceFill(['created_at' => now()->subMinutes(11)])->save();
    $paid->payments()->first()->update(['submitted_at' => now()]);
    $this->artisan('tickets:payment-follow-ups');
    Notification::assertSentOnDemandTimes(AttendeeTicketNotice::class, 1);
});

it('shows a paid ticket\'s QR code at its private address', function () {
    $this->ticket->update(['status' => 'active', 'payment_status' => 'completed']);

    $r = $this->get(route('ticket.qr', $this->ticket->qr_code))->assertOk();
    expect($r->headers->get('Content-Type'))->toMatch('/image\/(png|svg)/');
});

it('points WhatsApp buttons where the email points: pay page, or the event once the place is gone', function () {
    \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['messages' => [['id' => 'x']]])]);
    config(['constants.whatsapp_templates.payment_failed.approved' => true, 'constants.whatsapp_templates.payment_expired.approved' => true,
        'constants.whatsapp_templates.payment_expired.button' => true,
        'services.whatsapp.phone_number_id' => '1', 'services.whatsapp.access_token' => 't']);
    $notifier = app(\App\Services\Notifications\AttendeeNotifier::class);

    $notifier->paymentFailed($this->ticket);
    $notifier->paymentWindowExpired($this->ticket);

    $buttons = collect(\Illuminate\Support\Facades\Http::recorded())->map(fn ($pair) => collect($pair[0]['template']['components'] ?? [])
        ->firstWhere('type', 'button')['parameters'][0]['text'] ?? null);
    expect($buttons->all())->toBe(["ticket/{$this->ticket->qr_code}/pay", "e/{$this->org->slug}/{$this->event->slug}"]);
});
