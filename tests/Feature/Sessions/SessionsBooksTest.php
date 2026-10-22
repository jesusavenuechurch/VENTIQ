<?php

use App\Models\{KhoeboProduct, Organization, PaymentSession, User};
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Http, Mail};

beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-22 10:00'));
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create(['name' => 'Maloti Water']);
    $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'email' => 'palesa@example.com']);
    $this->admin->assignRole('org_admin');

    // The Sessions company: its own URL and token, its journals.
    config([
        'services.khoebo.sessions.url' => 'https://sventiq.khoebo.test/api/v1', 'services.khoebo.sessions.token' => 'itk_sessions',
        'services.khoebo.sessions.journals' => ['default' => 7, 'bank_transfer' => 7, 'ecocash' => 8, 'mpesa' => 9, 'cash' => 4],
        'services.khoebo.url' => 'https://eventiq.khoebo.test/api/v1', 'services.khoebo.token' => 'itk_events',
    ]);
    KhoeboProduct::create(['key' => 'sessions:plan-team', 'khoebo_id' => 11]);
    KhoeboProduct::create(['key' => 'sessions:payg', 'khoebo_id' => 13]);
    $this->up = true;
    Http::fake([
        'sventiq.khoebo.test/api/v1/customers' => Http::response(['data' => ['id' => 6]], 201),
        'sventiq.khoebo.test/api/v1/invoices' => fn () => $this->up ? Http::response(['data' => ['id' => 3, 'reference' => 'INV-00003', 'status' => 'draft']], 201) : Http::response(['error' => ['code' => 'server_error', 'message' => 'down']], 503),
        'sventiq.khoebo.test/api/v1/invoices/3/post' => Http::response(['data' => ['id' => 3, 'reference' => 'INV-000001', 'status' => 'posted']]),
        'sventiq.khoebo.test/api/v1/invoices/3/pay' => Http::response(['data' => ['id' => 1, 'reference' => 'PAY-000001', 'status' => 'posted']], 201),
        'sventiq.khoebo.test/api/v1/invoices/3/send' => Http::response(['data' => ['id' => 3], 'meta' => ['send' => ['status' => 'pending']]]),
    ]);

    $this->paid = fn (array $attrs = []) => PaymentSession::create(array_merge([
        'payable_type' => 'session_package', 'payable_id' => $this->org->id, 'organization_id' => $this->org->id,
        'gateway' => 'paylesotho', 'client_reference' => 'VQ0001-TEAM', 'amount' => 500, 'status' => 'completed',
        'payment_method' => 'ecocash', 'transaction_id' => 'ECO123',
        'purchase_meta' => ['type' => 'plan', 'tier' => 'team', 'sessions_included' => 3, 'whatsapp_included' => 0, 'sms_included' => 0],
    ], $attrs));
});

it('books a plan paid by EcoCash in the Sessions company: invoiced, posted, paid into EcoCash, emailed', function () {
    $payment = ($this->paid)();
    \App\Jobs\RecordSessionsSale::dispatchSync($payment->id);

    expect($payment->fresh())->khoebo_invoice_id->toBe(3)->khoebo_reference->toBe('INV-000001')->khoebo_payment_id->toBe(1)->khoebo_error->toBeNull();
    Http::assertSent(fn (Request $r) => $r->url() === 'https://sventiq.khoebo.test/api/v1/invoices' && $r->hasHeader('Authorization', 'Bearer itk_sessions')
        && $r['customer_id'] === 6 && $r['external_reference'] === "ventiq-sessions-pay-{$payment->id}"
        && $r['lines'][0]['product_id'] === 11 && $r['lines'][0]['unit_price'] === '500.00' && $r['lines'][0]['description'] === 'Team plan, 22 Oct to 21 Nov 2026');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/invoices/3/pay') && $r['journal_id'] === 8 && $r['amount'] === '500.00' && $r['external_reference'] === 'ECO123');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/invoices/3/send') && $r['to'] === ['palesa@example.com'] && $r['attach_pdf'] === true);
    // Nothing in the Events books.
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'eventiq.'));

    // Once only.
    \App\Jobs\RecordSessionsSale::dispatchSync($payment->id);
    Http::assertSentCount(5);
});

it('pays M-Pesa and bank transfers into their own journals, pay-as-you-go as one line', function () {
    $payment = ($this->paid)(['payment_method' => 'mpesa', 'amount' => 400, 'purchase_meta' => ['type' => 'payg', 'quantity' => 3]]);
    app(\App\Services\Sessions\SessionsBooks::class)->recordSale($payment);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/invoices') && $r['lines'][0]['product_id'] === 13 && $r['lines'][0]['description'] === '3 pay-as-you-go sessions');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/pay') && $r['journal_id'] === 9 && $r['amount'] === '400.00');

    $bank = ($this->paid)(['gateway' => 'bank', 'payment_method' => 'bank_transfer', 'transaction_id' => 'FNB 99812']);
    app(\App\Services\Sessions\SessionsBooks::class)->recordSale($bank);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/pay') && $r['journal_id'] === 7 && $r['external_reference'] === 'FNB 99812');
});

it('books a bank transfer when a super admin confirms it', function () {
    $this->actingAs($this->admin)->post(route('sessions.plan.bank-transfer'), ['type' => 'plan', 'tier' => 'team', 'paid_on' => '2026-10-21']);
    $super = User::factory()->create(['organization_id' => null]);
    $super->assignRole('super_admin');
    $this->actingAs($super)->post(route('ventiq.session-plans.transfers.confirm', PaymentSession::sole()), ['bank_reference' => 'FNB 99812']);

    expect(PaymentSession::sole()->khoebo_reference)->toBe('INV-000001');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/pay') && $r['journal_id'] === 7);
});

it('keeps the error when Khoebo is down, and the hourly catch-up books it later', function () {
    $this->up = false;
    $payment = ($this->paid)();
    try {
        app(\App\Services\Sessions\SessionsBooks::class)->recordSale($payment);
    } catch (\App\Services\Khoebo\KhoeboException) {
    }
    expect($payment->fresh())->khoebo_invoice_id->toBeNull()->khoebo_error->toContain('down');

    $this->up = true;
    $this->artisan('sessions:khoebo')->expectsOutputToContain('1 of 1 booked')->assertSuccessful();
    expect($payment->fresh())->khoebo_reference->toBe('INV-000001')->khoebo_error->toBeNull();
});
