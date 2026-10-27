<?php

use App\Mail\{BankTransferClaimedMail, BankTransferResultMail};
use App\Models\{Organization, PaymentSession, SessionAgreement, SessionInvoice, SessionPackage, User};
use App\Services\SessionQuotaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-21 10:00'));
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    config(['ventiq.billing.email' => 'billing@example.com']);
    $this->org = Organization::factory()->create(['name' => 'Maloti Water']);
    $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'email' => 'palesa@example.com']);
    $this->admin->assignRole('org_admin');
    $this->super = User::factory()->create(['organization_id' => null]);
    $this->super->assignRole('super_admin');
    $this->ref = 'VQ' . str_pad((string) $this->org->id, 4, '0', STR_PAD_LEFT) . '-TEAM';
});

it("shows VENTIQ's bank account with the purchase's reference, even when mobile money is off", function () {
    config(['gateways.paylesotho.enabled' => false]);
    $this->actingAs($this->admin)->get(route('organization.session-plan.payment', ['type' => 'plan', 'tier' => 'team']))
        ->assertInertia(fn (Assert $page) => $page->component('Sessions/PlanPayment')
            ->where('bank.reference', $this->ref)->where('bank.claimed', false)
            ->where('bank.lines', fn ($lines) => collect($lines)->pluck('value', 'label')->only(['Account number', 'Branch', 'SWIFT code', 'Reference'])->all() === [
                'Account number' => '63212657621', 'Branch' => 'Kingsway, code 280261', 'SWIFT code' => 'FIRNLSMX', 'Reference' => $this->ref,
            ]));
});

it('takes "we\'ve paid" once, tells VENTIQ, and shows it waiting', function () {
    $this->actingAs($this->admin);
    $page = route('organization.session-plan.payment', ['type' => 'plan', 'tier' => 'team']);
    $claim = fn () => $this->from($page)->post(route('sessions.plan.bank-transfer'), ['type' => 'plan', 'tier' => 'team', 'paid_on' => '2026-10-20', 'note' => 'From our Standard Lesotho account']);
    // Back on the payment page, which shows it waiting.
    $claim()->assertRedirect($page)->assertSessionHas('status');
    $claim();

    $payment = PaymentSession::sole();
    expect($payment)->gateway->toBe('bank')->status->toBe('pending')->client_reference->toBe($this->ref)
        ->and((float) $payment->amount)->toBe(500.0)->and($payment->purchase_meta['tier'])->toBe('team');
    Mail::assertQueued(BankTransferClaimedMail::class, fn ($m) => $m->hasTo('billing@example.com') && $m->payment->is($payment));

    $this->get(route('sessions.plan'))->assertInertia(fn (Assert $page) => $page->where('transfers.0.reference', $this->ref)->where('transfers.0.paidOn', '20 Oct 2026'));
    $this->get(route('organization.session-plan.payment', ['type' => 'plan', 'tier' => 'team']))->assertInertia(fn (Assert $page) => $page->where('bank.claimed', true));

    // Nothing applied yet.
    expect(app(SessionQuotaService::class)->currentPlan($this->org)->tier)->toBe('free');

    // Not in the future, and only an Admin buys.
    $this->post(route('sessions.plan.bank-transfer'), ['type' => 'payg', 'quantity' => 3, 'paid_on' => '2026-10-25'])->assertSessionHasErrors('paid_on');
    $staff = User::factory()->create(['organization_id' => $this->org->id]);
    $staff->assignRole('staff');
    $this->actingAs($staff)->post(route('sessions.plan.bank-transfer'), ['type' => 'payg', 'quantity' => 3, 'paid_on' => '2026-10-20'])->assertForbidden();
});

it('applies the plan once a super admin confirms it, and tells the Admins', function () {
    $this->actingAs($this->admin)->post(route('sessions.plan.bank-transfer'), ['type' => 'plan', 'tier' => 'team', 'paid_on' => '2026-10-20']);
    $payment = PaymentSession::sole();

    $this->actingAs($this->super)->get(route('ventiq.session-plans.index'))->assertInertia(fn (Assert $page) => $page->where('transfers.0.reference', $this->ref)->where('transfers.0.amount', 500)
        ->where('adminWaiting', fn ($w) => $w['ventiq.session-plans.index'] === 1));
    $this->post(route('ventiq.session-plans.transfers.confirm', $payment), ['bank_reference' => 'FNB 99812'])->assertSessionHas('status');

    expect($payment->fresh())->status->toBe('completed')->transaction_id->toBe('FNB 99812')
        ->and(app(SessionQuotaService::class)->currentPlan($this->org))->tier->toBe('team')->sessions_included->toBe(3)
        ->and((float) SessionPackage::where('organization_id', $this->org->id)->where('tier', 'team')->value('price_paid'))->toBe(500.0);
    Mail::assertQueued(BankTransferResultMail::class, fn ($m) => $m->hasTo('palesa@example.com') && $m->confirmed);

    // Once only.
    $this->post(route('ventiq.session-plans.transfers.confirm', $payment))->assertSessionHasErrors('transfer');
    expect(SessionPackage::where('organization_id', $this->org->id)->where('tier', 'team')->count())->toBe(1);

    // Only a super admin confirms.
    $this->actingAs($this->admin)->post(route('ventiq.session-plans.transfers.confirm', $payment))->assertForbidden();
});

it('adds pay-as-you-go sessions paid by transfer, or says why not when it isn\'t found', function () {
    $this->actingAs($this->admin)->post(route('sessions.plan.bank-transfer'), ['type' => 'payg', 'quantity' => 3, 'paid_on' => '2026-10-20']);
    $payment = PaymentSession::sole();
    expect($payment->client_reference)->toEndWith('-S3')->and((float) $payment->amount)->toBe(400.0);

    $this->actingAs($this->super)->post(route('ventiq.session-plans.transfers.reject', $payment), ['reason' => 'No transfer with this reference yet.'])->assertSessionHas('status');
    expect($payment->fresh()->status)->toBe('failed')->and(app(SessionQuotaService::class)->remainingSessions($this->org))->toBe(1);
    Mail::assertQueued(BankTransferResultMail::class, fn ($m) => !$m->confirmed && $m->reason === 'No transfer with this reference yet.');

    // They pay again: a new claim, confirmed this time.
    $this->actingAs($this->admin)->post(route('sessions.plan.bank-transfer'), ['type' => 'payg', 'quantity' => 3, 'paid_on' => '2026-10-21']);
    $again = PaymentSession::where('status', 'pending')->sole();
    $this->actingAs($this->super)->post(route('ventiq.session-plans.transfers.confirm', $again));
    expect(app(SessionQuotaService::class)->remainingSessions($this->org))->toBe(4);
});

it('puts the bank account on a corporate invoice, with the invoice number as the reference', function () {
    SessionAgreement::create(['organization_id' => $this->org->id, 'rate' => 300, 'free_per_month' => 0, 'starts_on' => '2026-09-01']);
    $invoice = SessionInvoice::create(['organization_id' => $this->org->id, 'number' => 'VQS-2026-0007', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
        'rate' => 300, 'sessions_count' => 1, 'free_count' => 0, 'total' => 300, 'due_date' => '2026-10-15', 'status' => 'unpaid']);

    $html = view('sessions.invoice-pdf', ['invoice' => $invoice->load(['organization', 'sessions']), 'orgLogo' => null, 'ventiqLogo' => '', 'billingEmail' => null, 'freePerMonth' => 0])->render();
    expect($html)->toContain('63212657621')->toContain('FIRNLSMX')->toContain('VQS-2026-0007');

    $mail = (new \App\Mail\SessionInvoiceMail($invoice))->render();
    expect($mail)->toContain('63212657621')->toContain('Kingsway, code 280261');

    $this->actingAs($this->admin)->get(route('sessions.plan'))->assertInertia(fn (Assert $page) => $page->where('bank', fn ($b) => collect($b)->pluck('value')->contains('63212657621')));
});
