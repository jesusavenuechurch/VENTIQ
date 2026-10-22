<?php

use App\Mail\SessionInvoiceMail;
use App\Models\{KhoeboProduct, Organization, Session, SessionAgreement, SessionInvoice, User};
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Http, Mail};
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-05 09:00'));
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->super = User::factory()->create(['organization_id' => null]);
    $this->super->assignRole('super_admin');
    $this->org = Organization::factory()->create(['name' => 'Lesotho Highlands Water']);
    $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'email' => 'palesa@example.com']);
    $this->admin->assignRole('org_admin');

    // Held: a session started (and so counted) at a given moment.
    $this->hold = function (string $title, string $when) {
        $this->travelTo(Carbon::parse($when));
        $session = Session::create(['organization_id' => $this->org->id, 'type' => 'meeting', 'title' => $title, 'date' => substr($when, 0, 10), 'status' => 'draft']);
        $this->actingAs($this->admin)->postJson(route('sessions.start', $session))->assertOk();

        return $session->fresh();
    };
    $this->khoeboUp = true;
    $this->khoebo = function (bool $up = true) {
        $this->khoeboUp = $up;
        // The Sessions books: a Khoebo company of their own.
        config(['services.khoebo.sessions.url' => 'https://khoebo.test/api/v1', 'services.khoebo.sessions.token' => 'itk_test', 'services.khoebo.sessions.journals.default' => 3]);
        KhoeboProduct::create(['key' => 'sessions:session', 'khoebo_id' => 9]);
        Http::fake([
            'khoebo.test/api/v1/customers' => Http::response(['data' => ['id' => 41]], 201),
            'khoebo.test/api/v1/invoices' => fn () => $this->khoeboUp ? Http::response(['data' => ['id' => 77, 'reference' => 'INV-00077', 'status' => 'draft']], 201) : Http::response(['error' => ['code' => 'server_error', 'message' => 'down']], 503),
            // Posted: its number now, and it can be paid and sent.
            'khoebo.test/api/v1/invoices/*/post' => Http::response(['data' => ['id' => 77, 'reference' => 'INV/2026/0077', 'status' => 'posted']]),
            'khoebo.test/api/v1/invoices/*/pay' => Http::response(['data' => ['id' => 5]], 201),
            'khoebo.test/api/v1/invoices/*/send' => Http::response(['data' => ['id' => 77], 'meta' => ['send' => ['id' => 1, 'status' => 'pending']]]),
        ]);
    };
});

it('lets a super admin set a corporate rate, and the organization sees it instead of plans', function () {
    $this->actingAs($this->super)->post(route('ventiq.corporate.store'), [
        'organization_id' => $this->org->id, 'rate' => 450, 'free_per_month' => 1, 'billing_email' => 'accounts@example.org', 'starts_on' => '2026-10-01',
    ])->assertRedirect()->assertSessionHas('status');
    expect(SessionAgreement::sole())->rate->toEqual('450.00');

    $this->actingAs($this->admin)->get(route('sessions.plan'))->assertInertia(fn (Assert $page) => $page
        ->where('corporate.rate', 450)->where('corporate.free', 1)->where('corporate.month.held', 0));
    $this->get(route('sessions.index'))->assertInertia(fn (Assert $page) => $page->where('sessionsArea.quota.corporate', true));

    // Only super admins set rates.
    $this->post(route('ventiq.corporate.store'), ['organization_id' => $this->org->id, 'rate' => 1, 'free_per_month' => 9, 'starts_on' => '2026-10-01'])->assertForbidden();
});

it('never stops a corporate organization, prices each session when it starts, and keeps the first free', function () {
    SessionAgreement::create(['organization_id' => $this->org->id, 'rate' => 450, 'free_per_month' => 1, 'starts_on' => '2026-10-01']);

    $one = ($this->hold)('Board', '2026-10-06 09:00');
    $two = ($this->hold)('Ops', '2026-10-13 09:00');
    $three = ($this->hold)('Ops', '2026-10-20 09:00');

    expect([(float) $one->charge, (float) $two->charge, (float) $three->charge])->toBe([0.0, 450.0, 450.0])
        ->and(app(\App\Services\Sessions\CorporateBilling::class)->monthToDate($this->org))->toBe(['held' => 3, 'free' => 1, 'owed' => 900.0]);

    // Starting the same session again doesn't charge it twice.
    $this->postJson(route('sessions.start', $three))->assertOk();
    expect(Session::whereNotNull('charge')->count())->toBe(3);

    // A new month: the first is free again.
    expect((float) ($this->hold)('Ops', '2026-11-02 09:00')->charge)->toBe(0.0);
});

it('invoices last month on the 1st: in Khoebo, by email with the PDF, once', function () {
    SessionAgreement::create(['organization_id' => $this->org->id, 'rate' => 450, 'free_per_month' => 1, 'billing_email' => 'accounts@example.org', 'starts_on' => '2026-10-01']);
    ($this->hold)('Board meeting', '2026-10-06 09:00');
    ($this->hold)('Weekly ops', '2026-10-13 09:00');
    ($this->hold)('Weekly ops', '2026-10-20 09:00');
    ($this->khoebo)();

    $this->travelTo(Carbon::parse('2026-11-01 06:10'));
    $this->artisan('sessions:invoice')->expectsOutputToContain('VQS-2026-0001')->assertSuccessful();

    $invoice = SessionInvoice::sole();
    expect($invoice)->total->toEqual('900.00')->sessions_count->toBe(3)->free_count->toBe(1)->status->toBe('unpaid')
        ->and($invoice->khoebo_reference)->toBe('INV/2026/0077')
        ->and($invoice->due_date->toDateString())->toBe('2026-11-15')
        ->and($invoice->sessions()->count())->toBe(3);

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/invoices') && $r['external_reference'] === 'VQS-2026-0001'
        && count($r['lines']) === 2 && $r['lines'][0]['unit_price'] === '450.00' && $r['lines'][0]['product_id'] === 9
        && str_starts_with($r['lines'][0]['description'], 'Weekly ops, 13 Oct 2026'));
    // Posted before it's paid or sent, then emailed by Khoebo to the billing contact and the Admins.
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/invoices/77/post'));
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/invoices/77/send') && $r['to'] === ['accounts@example.org', 'palesa@example.com'] && $r['attach_pdf'] === true);
    Mail::assertNotQueued(SessionInvoiceMail::class);
    expect($invoice->emailed_at)->not->toBeNull();

    $mail = new SessionInvoiceMail($invoice);
    expect(substr((string) $mail->attachments()[0]->attachWith(fn ($path) => '', fn ($data) => $data()), 0, 4))->toBe('%PDF');

    // Run again: nothing doubled.
    $this->artisan('sessions:invoice')->expectsOutputToContain('0 invoice(s)')->assertSuccessful();
    expect(SessionInvoice::count())->toBe(1);
});

it("doesn't invoice a month of only free sessions, and still invoices when Khoebo is down", function () {
    SessionAgreement::create(['organization_id' => $this->org->id, 'rate' => 450, 'free_per_month' => 1, 'starts_on' => '2026-10-01']);
    ($this->hold)('Board', '2026-10-06 09:00');
    $this->travelTo(Carbon::parse('2026-11-01 06:10'));
    $this->artisan('sessions:invoice')->assertSuccessful();
    expect(SessionInvoice::count())->toBe(0);

    ($this->hold)('Board', '2026-11-03 09:00');
    ($this->hold)('Ops', '2026-11-10 09:00');
    ($this->khoebo)(false);
    $this->travelTo(Carbon::parse('2026-12-01 06:10'));
    $this->artisan('sessions:invoice')->expectsOutputToContain('not in Khoebo yet')->assertSuccessful();

    $invoice = SessionInvoice::sole();
    expect($invoice->khoebo_invoice_id)->toBeNull()->and($invoice->khoebo_error)->not->toBeNull()->and($invoice->emailed_at)->not->toBeNull();

    $this->khoeboUp = true;
    $this->actingAs($this->super)->post(route('ventiq.corporate.khoebo', $invoice))->assertSessionHas('status', fn ($s) => str_contains($s, 'INV/2026/0077'));
});

it('records payments, in Khoebo too, until the invoice is paid', function () {
    SessionAgreement::create(['organization_id' => $this->org->id, 'rate' => 300, 'free_per_month' => 0, 'starts_on' => '2026-10-01']);
    ($this->hold)('Board', '2026-10-06 09:00');
    ($this->hold)('Ops', '2026-10-13 09:00');
    ($this->khoebo)();
    $this->travelTo(Carbon::parse('2026-11-01 06:10'));
    $this->artisan('sessions:invoice');
    $invoice = SessionInvoice::sole();
    $this->actingAs($this->super);
    $this->travelTo(Carbon::parse('2026-11-05 10:00'));

    $this->post(route('ventiq.corporate.pay', $invoice), ['amount' => 700, 'date' => '2026-11-03', 'method' => 'bank_transfer'])->assertSessionHasErrors('amount');
    $this->post(route('ventiq.corporate.pay', $invoice), ['amount' => 200, 'date' => '2026-11-03', 'method' => 'bank_transfer', 'reference' => 'FNB 123'])->assertSessionHasNoErrors();
    expect($invoice->fresh())->status->toBe('unpaid')->and($invoice->fresh()->outstanding())->toBe(400.0);

    $this->post(route('ventiq.corporate.pay', $invoice), ['amount' => 400, 'date' => '2026-11-04', 'method' => 'ecocash'])->assertSessionHasNoErrors();
    expect($invoice->fresh())->status->toBe('paid')->and($invoice->fresh()->paid_at->toDateString())->toBe('2026-11-04');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/invoices/77/pay') && $r['amount'] === '200.00' && $r['journal_id'] === 3);
});

it("shows the organization its invoices, and keeps each one to its Admins and VENTIQ", function () {
    SessionAgreement::create(['organization_id' => $this->org->id, 'rate' => 300, 'free_per_month' => 0, 'starts_on' => '2026-10-01']);
    ($this->hold)('Board', '2026-10-06 09:00');
    $this->travelTo(Carbon::parse('2026-11-01 06:10'));
    $this->artisan('sessions:invoice');
    $invoice = SessionInvoice::sole();

    $this->actingAs($this->admin)->get(route('sessions.plan'))->assertInertia(fn (Assert $page) => $page->where('invoices.0.number', 'VQS-2026-0001')->where('invoices.0.total', 300));
    $this->get(route('sessions.invoices.pdf', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($this->super)->get(route('sessions.invoices.pdf', $invoice))->assertOk();

    $staff = User::factory()->create(['organization_id' => $this->org->id]);
    $staff->assignRole('staff');
    $this->actingAs($staff)->get(route('sessions.invoices.pdf', $invoice))->assertNotFound();
    $other = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $other->assignRole('org_admin');
    $this->actingAs($other)->get(route('sessions.invoices.pdf', $invoice))->assertNotFound();
});

it('goes back to the plan when the rate ends, still invoicing what was held', function () {
    $agreement = SessionAgreement::create(['organization_id' => $this->org->id, 'rate' => 300, 'free_per_month' => 0, 'starts_on' => '2026-10-01']);
    ($this->hold)('Board', '2026-10-06 09:00');
    $this->actingAs($this->super)->post(route('ventiq.corporate.end', $agreement))->assertRedirect();

    // The free plan's one session, then the plan stops it.
    ($this->hold)('Ops', '2026-10-13 09:00');
    $blocked = Session::create(['organization_id' => $this->org->id, 'type' => 'meeting', 'title' => 'Ops', 'date' => '2026-10-20', 'status' => 'draft']);
    $this->actingAs($this->admin)->postJson(route('sessions.start', $blocked))->assertStatus(402);

    // Invoice now for a finished month only.
    $this->actingAs($this->super)->post(route('ventiq.corporate.invoice'), ['organization_id' => $this->org->id, 'month' => '2026-10'])->assertSessionHasErrors('month');
    $this->travelTo(Carbon::parse('2026-11-02'));
    $this->post(route('ventiq.corporate.invoice'), ['organization_id' => $this->org->id, 'month' => '2026-10'])->assertSessionHas('status', fn ($s) => str_contains($s, 'VQS-2026-0001'));
    expect(SessionInvoice::sole()->sessions_count)->toBe(1);
});
