<?php

namespace App\Http\Controllers\Ventiq;

use App\Http\Controllers\Controller;
use App\Models\{Organization, SessionAgreement, SessionInvoice};
use App\Services\Sessions\CorporateBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Corporate rates for Sessions: an organization's agreed price per
 * session, set here, and the monthly invoices that come from it.
 */
class CorporateRatesController extends Controller
{
    public function __construct(private CorporateBilling $billing) {}

    public function index()
    {
        $agreements = SessionAgreement::with('organization')->orderByRaw('ended_at IS NOT NULL')->orderBy('created_at', 'desc')->get();

        return Inertia::render('Ventiq/CorporateRates', [
            'agreements' => $agreements->map(fn (SessionAgreement $a) => [
                'id'             => $a->id,
                'organization'   => ['id' => $a->organization_id, 'name' => $a->organization?->name],
                'rate'           => (float) $a->rate,
                'free_per_month' => $a->free_per_month,
                'billing_email'  => $a->billing_email ?? '',
                'notes'          => $a->notes ?? '',
                'starts_on'      => $a->starts_on->toDateString(),
                'startsLabel'    => $a->starts_on->format('j M Y'),
                'ended'          => $a->ended_at?->format('j M Y'),
                'month'          => $a->organization && !$a->ended_at ? $this->billing->monthToDate($a->organization) : null,
            ]),
            'invoices' => SessionInvoice::with('organization')->latest()->limit(100)->get()->map(fn (SessionInvoice $i) => [
                'id'           => $i->id,
                'number'       => $i->number,
                'organization' => ['id' => $i->organization_id, 'name' => $i->organization?->name],
                'period'       => $i->periodLabel(),
                'sessions'     => $i->sessions_count,
                'free'         => $i->free_count,
                'total'        => (float) $i->total,
                'outstanding'  => $i->outstanding(),
                'status'       => $i->isOverdue() ? 'overdue' : $i->status,
                'due'          => $i->due_date->format('j M Y'),
                'khoebo'       => $i->khoebo_reference,
                'khoeboError'  => $i->khoebo_error,
                'emailed'      => $i->emailed_at?->format('j M Y, H:i'),
                'paid'         => $i->paid_at?->format('j M Y'),
                'pdf'          => route('sessions.invoices.pdf', $i),
            ]),
            'organizations' => Organization::orderBy('name')->get(['id', 'name'])->map(fn ($o) => ['value' => $o->id, 'label' => $o->name]),
            'methods'       => collect(CorporateBilling::methods())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'lastMonth'     => now()->subMonthNoOverflow()->format('Y-m'),
            'meta'          => ['title' => 'Corporate rates | VENTIQ'],
        ]);
    }

    /** A new rate, or a change to one: from now on, for sessions not yet held. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')],
            'rate'            => ['required', 'numeric', 'min:0', 'max:1000000'],
            'free_per_month'  => ['required', 'integer', 'min:0', 'max:100'],
            'billing_email'   => ['nullable', 'email', 'max:255'],
            'notes'           => ['nullable', 'string', 'max:1000'],
            'starts_on'       => ['required', 'date'],
        ]);

        $agreement = SessionAgreement::updateOrCreate(['organization_id' => $data['organization_id']], $data + ['ended_at' => null, 'created_by' => $request->user()->id]);

        return back()->with('status', "{$agreement->organization->name} is on M" . number_format((float) $agreement->rate, 2) . ' a session, ' . ($agreement->free_per_month ?: 'none') . ' free a month. Sessions held from now on are invoiced monthly.');
    }

    /** Back to the plans: sessions held so far still go on their month's invoice. */
    public function end(SessionAgreement $agreement)
    {
        $agreement->update(['ended_at' => now()]);

        return back()->with('status', "{$agreement->organization->name}'s corporate rate has ended. Sessions already held are still invoiced; new ones use its plan.");
    }

    /** A finished month's invoice now, rather than waiting for the 1st. */
    public function invoice(Request $request)
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')],
            'month'           => ['required', 'date_format:Y-m', 'before:' . now()->format('Y-m') . '-01'],
        ], ['month.before' => 'Only a month that has finished can be invoiced.']);

        $organization = Organization::findOrFail($data['organization_id']);
        $invoice = $this->billing->invoice($organization, Carbon::createFromFormat('Y-m-d', "{$data['month']}-01"));

        return back()->with('status', $invoice
            ? "{$invoice->number} made for {$organization->name}: M" . number_format((float) $invoice->total, 2) . ($invoice->khoebo_reference ? ", {$invoice->khoebo_reference} in Khoebo" : '') . ', emailed.'
            : "{$organization->name} has nothing to invoice for that month (already invoiced, or only free sessions).");
    }

    public function khoebo(SessionInvoice $invoice)
    {
        return back()->with('status', $this->billing->raiseInKhoebo($invoice)
            ? "{$invoice->number} is in Khoebo as {$invoice->fresh()->khoebo_reference}."
            : "{$invoice->number} still isn't in Khoebo: " . ($invoice->fresh()->khoebo_error ?? 'Khoebo isn\'t set up here.'));
    }

    public function resend(SessionInvoice $invoice)
    {
        $sent = $this->billing->email($invoice);

        return back()->with('status', $sent ? "{$invoice->number} emailed again to {$sent} " . ($sent === 1 ? 'address' : 'addresses') . '.' : "{$invoice->organization->name} has no billing email or admins to send it to.");
    }

    public function pay(Request $request, SessionInvoice $invoice)
    {
        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'date'      => ['required', 'date', 'before_or_equal:today'],
            'method'    => ['required', Rule::in(array_keys(CorporateBilling::methods()))],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $invoice = $this->billing->recordPayment($invoice, (float) $data['amount'], $data['date'], $data['method'], $data['reference'] ?? null, $request->user());
        } catch (\InvalidArgumentException | \App\Services\Khoebo\KhoeboException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', $invoice->status === 'paid' ? "{$invoice->number} is paid." : "Payment recorded. M" . number_format($invoice->outstanding(), 2) . " still owed on {$invoice->number}.");
    }
}
