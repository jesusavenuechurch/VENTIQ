<?php

use App\Models\{Client, Event, EventTier, MobileMoneyMessage, Organization, OrganizationPaymentMethod, PaymentSession, SettlementItem, Ticket, User};
use App\Services\MobileMoney\{EcoCashSms, MobileMoneyInbox};
use Illuminate\Support\Facades\{Bus, Mail};
use Illuminate\Support\Str;

// The wording EcoCash (199) sends VENTIQ's merchant SIM; names and numbers made up.
function ecocashIn(float $amount, string $phone, string $reference, string $txn, float $balance): string
{
    return "You have received M{$amount} from LERATO MOKOENA-{$phone} for {$reference}. Approval Code: {$txn}. New wallet balance: M{$balance}.";
}

function forward(string $text, string $from = '199')
{
    return test()->postJson('/api/mobile-money/sms', ['from' => $from, 'text' => $text, 'receivedStamp' => now()->getTimestampMs()], ['X-Ventiq-Token' => 'sms-secret']);
}

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    config([
        'services.mobile_money.token' => 'sms-secret',
        'services.mobile_money.senders' => ['ecocash' => ['199']],
        'gateways.paylesotho.ecocash.merchant_code' => '62000000',
        'gateways.paylesotho.ecocash.merchant_name' => 'VENTIQ',
    ]);
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->org = Organization::factory()->create();
    $this->event = Event::create(['organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6), 'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid']);
    $this->tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 150, 'is_active' => true]);
    $this->client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Lerato', 'phone' => '+26659494756']);
    $this->ticket = Ticket::create(['event_id' => $this->event->id, 'client_id' => $this->client->id, 'event_tier_id' => $this->tier->id, 'status' => 'pending', 'payment_status' => 'pending', 'amount' => 150]);
    $this->code = Str::after($this->ticket->fresh()->voucher_code, 'VQ-');
});

it('reads EcoCash payments in and out', function () {
    expect(EcoCashSms::parse('You have received M4000.0 from NAME SURNAME-63000000 for Merchant Payment. Approval Code: MP261008.1608.A38818. New wallet balance: M4003.0.'))
        ->toMatchArray(['kind' => 'in', 'amount' => 4000.0, 'payer_name' => 'NAME SURNAME', 'payer_phone' => '63000000', 'reference' => 'Merchant Payment', 'transaction_id' => 'MP261008.1608.A38818', 'balance_after' => 4003.0]);

    expect(EcoCashSms::parse("You have received M1.0 from A\nB-62000001 for 0. Approval Code:\nMP261001.1610.A94752. New wallet balance: M1.0."))
        ->toMatchArray(['kind' => 'in', 'amount' => 1.0, 'payer_phone' => '62000001', 'reference' => '0', 'transaction_id' => 'MP261001.1610.A94752']);

    expect(EcoCashSms::parse('Stock Reimbursement transaction of M4000 is successful to IND01 with Txn ID: OW261009.1107.A49445'))
        ->toMatchArray(['kind' => 'out', 'amount' => 4000.0, 'transaction_id' => 'OW261009.1107.A49445']);

    expect(EcoCashSms::parse('Dial *199# for great offers')['kind'])->toBe('other');
    expect(MobileMoneyInbox::entryCode('vq-x82l'))->toBe('VQ-X82L')
        ->and(MobileMoneyInbox::entryCode('0'))->toBeNull()
        ->and(MobileMoneyInbox::entryCode('Merchant Payment'))->toBeNull();
});

it('only takes messages with the token, and keeps only the mobile money senders', function () {
    $this->postJson('/api/mobile-money/sms', ['from' => '199', 'text' => 'x'], ['X-Ventiq-Token' => 'wrong'])->assertStatus(401);

    forward('Your OTP is 1234', from: 'Bank')->assertStatus(202);
    expect(MobileMoneyMessage::count())->toBe(0);
});

it('activates the ticket whose entry code was typed as the reference', function () {
    forward(ecocashIn(150, '63000000', $this->code, 'MP261014.1000.A00001', 150))->assertOk()->assertJson(['status' => 'matched']);

    $ticket = $this->ticket->fresh();
    expect($ticket->status)->toBe('active')
        ->and($ticket->payment_status)->toBe('completed')
        ->and(SettlementItem::where('ticket_id', $ticket->id)->value('amount_received'))->toEqual('150.00');

    // The forwarder sending it again changes nothing.
    forward(ecocashIn(150, '63000000', $this->code, 'MP261014.1000.A00001', 150))->assertOk();
    expect(MobileMoneyMessage::count())->toBe(1)
        ->and(SettlementItem::where('ticket_id', $ticket->id)->count())->toBe(1);
});

it('matches by the paying number and amount when there is no reference', function () {
    forward(ecocashIn(150, '59494756', '0', 'MP261014.1000.A00002', 150))->assertJson(['status' => 'matched']);

    expect($this->ticket->fresh()->status)->toBe('active');
});

it('confirms a PIN prompt EcoCash never answered', function () {
    $push = PaymentSession::create([
        'payable_type' => 'ticket', 'payable_id' => $this->ticket->id, 'gateway' => 'paylesotho', 'client_reference' => 'ECOCASH1T' . random_int(1e9, 9e9),
        'payment_method' => 'ecocash', 'amount' => 150, 'status' => 'pending', 'organization_id' => $this->org->id,
        'callback_payload' => ['push_to' => '+26659494756', 'no_answer' => true],
    ]);

    forward(ecocashIn(150, '59494756', '0', 'MP261014.1000.A00003', 150))->assertJson(['status' => 'online']);

    expect($push->fresh()->status)->toBe('completed')
        ->and($push->fresh()->transaction_id)->toBe('MP261014.1000.A00003')
        ->and($this->ticket->fresh()->status)->toBe('active');
});

it('only notes the SMS for a payment already completed online', function () {
    $push = PaymentSession::create([
        'payable_type' => 'ticket', 'payable_id' => $this->ticket->id, 'gateway' => 'paylesotho', 'client_reference' => 'ECOCASH1T' . random_int(1e9, 9e9),
        'payment_method' => 'ecocash', 'amount' => 150, 'status' => 'completed', 'transaction_id' => 'PL-9', 'organization_id' => $this->org->id,
        'callback_payload' => ['push_to' => '59494756'],
    ]);
    app(\App\Services\Payments\PaymentCompletion::class)->completed($push);

    forward(ecocashIn(150, '59494756', '0', 'MP261014.1000.A00004', 150))->assertJson(['status' => 'online']);

    expect(SettlementItem::where('ticket_id', $this->ticket->id)->count())->toBe(1)
        ->and($push->fresh()->callback_payload['paid_twice'] ?? false)->toBeFalse();
});

it('holds a payment whose balance does not follow, until a person says it is real', function () {
    forward(ecocashIn(10, '63000009', '0', 'MP261014.0900.A00010', 10))->assertJson(['status' => 'unmatched']);

    // 10 + 150 is 160, not 500: not trusted on its own.
    forward(ecocashIn(150, '63000000', $this->code, 'MP261014.1000.A00011', 500))->assertJson(['status' => 'held']);
    expect($this->ticket->fresh()->status)->toBe('pending');

    $super = User::factory()->create(['organization_id' => null]);
    $super->assignRole('super_admin');
    $held = MobileMoneyMessage::where('transaction_id', 'MP261014.1000.A00011')->first();

    $this->actingAs($super)->get(route('ventiq.money.index'))->assertSee('EcoCash payments to check')->assertSee("doesn't follow");
    $this->post(route('ventiq.money.sms.decide', $held), ['decision' => 'accept']);

    expect($held->fresh()->status)->toBe('matched')
        ->and($this->ticket->fresh()->status)->toBe('active');
});

it('keeps the balance chain across money paid out', function () {
    forward(ecocashIn(100, '63000009', '0', 'MP261014.0900.A00020', 100));
    forward('Stock Reimbursement transaction of M100 is successful to IND01 with Txn ID: OW261014.0930.A00021');
    forward(ecocashIn(150, '63000000', $this->code, 'MP261014.1000.A00022', 150))->assertJson(['status' => 'matched']);
});

it('holds a payment whose amount is not what the ticket owes', function () {
    forward(ecocashIn(100, '63000000', $this->code, 'MP261014.1000.A00030', 100))->assertJson(['status' => 'held']);

    expect($this->ticket->fresh()->status)->toBe('pending')
        ->and(MobileMoneyMessage::first()->ticket_id)->toBe($this->ticket->id);
});

it('matches a payment from another phone once the attendee says where they paid from', function () {
    forward(ecocashIn(150, '63111111', '0', 'MP261014.1000.A00040', 150))->assertJson(['status' => 'unmatched']);

    $this->post(route('ticket.pay.merchant', $this->ticket->qr_code), ['merchant_phone' => '6311 1111'])
        ->assertRedirect(route('ticket.download', ['qr_code' => $this->ticket->qr_code]));

    expect($this->ticket->fresh()->status)->toBe('active')
        ->and(MobileMoneyMessage::first()->status)->toBe('matched');
});

it('lets a super admin match a payment to a ticket by entry code', function () {
    forward(ecocashIn(150, '63111111', 'my ticket', 'MP261014.1000.A00050', 150));
    $super = User::factory()->create(['organization_id' => null]);
    $super->assignRole('super_admin');

    $this->actingAs($super)->post(route('ventiq.money.sms.decide', MobileMoneyMessage::first()), ['decision' => 'match', 'entry_code' => strtolower($this->code)])
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'is active'));

    expect($this->ticket->fresh()->status)->toBe('active');
});

it('offers the organizer\'s own accounts only on sponsored events', function () {
    $account = OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'ecocash', 'account_number' => '62999999', 'account_name' => 'Org', 'is_active' => true]);
    $accounts = app(\App\Services\Payments\PaymentAccountService::class);

    expect($accounts->offeredDirectAccounts($this->event))->toBeEmpty()
        ->and($accounts->merchantForEvent($this->event))->not->toBeNull();
    $this->get(route('ticket.pay', $this->ticket->qr_code))->assertSee('62000000')->assertSee($this->code)->assertDontSee('62999999');
    $this->post(route('ticket.pay.manual', $this->ticket->qr_code), ['payment_method_id' => $account->id, 'payment_reference' => 'X'])
        ->assertRedirect(route('ticket.pay', $this->ticket->qr_code));
    expect($this->ticket->payments()->whereNotNull('submitted_at')->exists())->toBeFalse();

    $this->event->update(['fees_sponsored' => true]);
    expect($accounts->offeredDirectAccounts($this->event->fresh())->pluck('id')->all())->toBe([$account->id]);
});
