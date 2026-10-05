@extends('layouts.app')
@section('title', 'VENTIQ money | VENTIQ')
@section('content')
@php
    $m = fn ($v) => 'M' . number_format((float) $v, 2);
    $card = 'bg-white rounded-[1.5rem] border border-gray-100 shadow-sm';
    $input = 'bg-slate-50 rounded-full px-3 py-1.5 text-[12px] font-semibold text-[#1D4069] focus:bg-white focus:outline-[#F07F22]';
    $button = 'px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest';
@endphp
<div class="max-w-5xl mx-auto px-4 py-8 space-y-8">
    <div>
        <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.3em] mb-1">Ventiq · Super admin</p>
        <h1 class="text-2xl font-black text-[#1D4069] tracking-tight">VENTIQ money</h1>
        <p class="text-[13px] font-medium text-gray-500 mt-1">What VENTIQ has earned, what it holds for organizers, and what to invoice.</p>
    </div>

    @if(session('status'))
        <div class="p-4 rounded-2xl bg-mint text-[12px] font-bold text-mint-ink">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="p-4 rounded-2xl bg-rose-50 text-[12px] font-bold text-rose-700">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    {{-- Headline figures --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach([
            ['Fees taken from payouts', $summary['fees_from_payouts'], 'Online tickets', 'text-lilac-ink'],
            ['Fees paid on invoices', $summary['fees_invoiced_paid'], 'Direct, free and comp tickets', 'text-lilac-ink'],
            ['Invoiced, not paid yet', $summary['fees_invoiced_unpaid'], 'Waiting on organizers', 'text-action-ink'],
            ['Fees to invoice', $summary['fees_to_invoice'], 'Events from ' . \Illuminate\Support\Carbon::parse($invoiceFrom)->format('j M Y'), 'text-action-ink'],
            ['Held for organizers', $summary['held_for_organizers'], 'Online money not yet batched', 'text-[#1D4069]'],
            ['In payout batches', $summary['in_payout_batches'], 'Batched, not paid yet', 'text-action-ink'],
            ['Paid out to organizers', $summary['paid_out'], 'All time', 'text-mint-ink'],
            ['Fees sponsored', $summary['fees_sponsored'], 'Given away by VENTIQ', 'text-gray-500'],
        ] as [$label, $value, $hint, $tone])
            <div class="{{ $card }} p-5">
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ $label }}</p>
                <p class="text-xl font-black mt-1 {{ $tone }}">{{ $m($value) }}</p>
                <p class="text-[11px] text-gray-400 mt-0.5">{{ $hint }}</p>
            </div>
        @endforeach
    </div>
    @php($earned = $summary['fees_from_payouts'] + $summary['fees_invoiced_paid'])
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 -mt-5">
        <div class="{{ $card }} p-5">
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Fees collected</p>
            <p class="text-xl font-black mt-1 text-lilac-ink">{{ $m($earned) }}</p>
            <p class="text-[11px] text-gray-400 mt-0.5">From payouts and paid invoices</p>
        </div>
        <div class="{{ $card }} p-5">
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Gateway costs</p>
            <p class="text-xl font-black mt-1 text-gray-500">−{{ $m($summary['gateway_costs']) }}</p>
            <p class="text-[11px] text-gray-400 mt-0.5">{{ rtrim(rtrim(number_format($gatewayRate * 100, 2), '0'), '.') }}% of online payments, absorbed by VENTIQ</p>
        </div>
        <div class="{{ $card }} p-5">
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Net earned</p>
            <p class="text-xl font-black mt-1 text-mint-ink">{{ $m($earned - $summary['gateway_costs']) }}</p>
            <p class="text-[11px] text-gray-400 mt-0.5">Fees collected less gateway costs</p>
        </div>
    </div>

    {{-- Online payments with no answer from the gateway --}}
    @if($toCheck->isNotEmpty())
        <section class="space-y-3">
            <h2 class="text-[13px] font-black text-action-ink uppercase tracking-widest"><i class="fas fa-magnifying-glass-dollar mr-1"></i>Online payments to check</h2>
            <p class="text-[12px] text-gray-500">EcoCash gave no answer for these, or the attendee paid the merchant by hand. Look for each in the EcoCash merchant statement (amount, number, time), then mark it.</p>
            <div class="{{ $card }} divide-y divide-gray-50">
                @foreach($toCheck as $s)
                    <div class="p-5 flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[14px] font-black text-[#1D4069]">{{ $m($s->amount) }} · {{ ucfirst($s->payment_method) }}</p>
                            <p class="text-[11px] text-gray-500">
                                {{ $s->ticket?->client?->full_name ?? 'Unknown' }} · {{ $s->ticket?->client?->phone }} · {{ $s->ticket?->event?->name }} ({{ $s->ticket?->event?->organization?->name }})
                            </p>
                            <p class="text-[11px] text-gray-400 font-mono">{{ $s->created_at->format('j M, H:i') }} · ref {{ $s->client_reference }}@if($s->transaction_id) · {{ $s->transaction_id }}@endif</p>
                            @if($hand = ($s->callback_payload['by_hand'] ?? null))
                                <p class="text-[11px] font-bold text-action-ink mt-1">
                                    <i class="fas fa-hand-holding-dollar mr-1"></i>Paid the merchant by hand from {{ $hand['paid_from'] ?? '?' }}@if($hand['reference'] ?? null) · ref {{ $hand['reference'] }}@endif
                                    @if($hand['proof_path'] ?? null) · <a href="{{ route('ventiq.money.online.proof', $s) }}" target="_blank" rel="noopener" class="underline">screenshot</a>@endif
                                </p>
                            @elseif($s->callback_payload['push_to'] ?? null)
                                <p class="text-[11px] text-gray-400 mt-1">Push to {{ $s->callback_payload['push_to'] }}@if($s->callback_payload['no_answer'] ?? false) · EcoCash gave no answer @endif</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('ventiq.money.online.decide', $s) }}" class="flex items-center gap-2">@csrf
                                <input type="hidden" name="decision" value="received">
                                <input name="reference" placeholder="Merchant reference (optional)" class="{{ $input }} w-48" aria-label="Merchant reference">
                                <button class="{{ $button }} bg-mint-ink text-white">Received</button>
                            </form>
                            <form method="POST" action="{{ route('ventiq.money.online.decide', $s) }}" onsubmit="return confirm('Mark this payment as not received?')">@csrf
                                <input type="hidden" name="decision" value="not_received">
                                <button class="{{ $button }} bg-white border border-gray-200 text-gray-500 hover:text-rose-600">Not received</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Paid twice --}}
    @if($paidTwice->isNotEmpty())
        <section class="space-y-3">
            <h2 class="text-[13px] font-black text-rose-600 uppercase tracking-widest"><i class="fas fa-rotate-left mr-1"></i>Paid twice: refund these</h2>
            <p class="text-[12px] text-gray-500">These came in for tickets that were already paid. Send the money back, then mark it.</p>
            <div class="{{ $card }} divide-y divide-gray-50">
                @foreach($paidTwice as $s)
                    <div class="p-5 flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[14px] font-black text-[#1D4069]">{{ $m($s->amount) }} · {{ ucfirst($s->payment_method) }}</p>
                            <p class="text-[11px] text-gray-500">{{ $s->ticket?->client?->full_name ?? 'Unknown' }} · {{ $s->callback_payload['push_to'] ?? $s->ticket?->client?->phone }} · {{ $s->ticket?->event?->name }}</p>
                            <p class="text-[11px] text-gray-400 font-mono">{{ $s->created_at->format('j M, H:i') }} · {{ $s->transaction_id }}</p>
                        </div>
                        <form method="POST" action="{{ route('ventiq.money.online.refunded', $s) }}" class="flex items-center gap-2">@csrf
                            <input name="reference" placeholder="Refund reference" class="{{ $input }} w-44" aria-label="Refund reference">
                            <button class="{{ $button }} bg-[#1D4069] text-white">Refunded</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Payouts --}}
    <section class="space-y-3">
        <h2 class="text-[13px] font-black text-[#1D4069] uppercase tracking-widest">Payouts to organizers</h2>

        <div class="{{ $card }} divide-y divide-gray-50">
            @forelse($payoutsDue as $row)
                <div class="p-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[14px] font-black text-[#1D4069]">{{ $row->organization?->name ?? 'Organization #' . $row->organization_id }}</p>
                        <p class="text-[11px] text-gray-500">{{ $row->line_count }} online {{ \Illuminate\Support\Str::plural('payment', $row->line_count) }} · VENTIQ fees {{ $m($row->fees) }} taken off</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-lg font-black text-[#1D4069]">{{ $m($row->owed) }}</span>
                        <form method="POST" action="{{ route('ventiq.money.payouts.create', $row->organization_id) }}">@csrf
                            <button class="{{ $button }} bg-brand text-white hover:bg-action">Create payout batch</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="p-6 text-[12px] text-gray-400">No online money waiting to be paid out.</p>
            @endforelse
        </div>

        @if($batches->isNotEmpty())
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1 pt-2">Batches to pay</p>
            <div class="{{ $card }} divide-y divide-gray-50">
                @foreach($batches as $batch)
                    <div class="p-5 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-[14px] font-black text-[#1D4069]">{{ $batch->organization?->name }} · {{ $m($batch->amount_owed_to_org) }}</p>
                            <p class="text-[11px] text-gray-500">Batch #{{ $batch->id }} · created {{ $batch->created_at->format('j M, H:i') }}</p>
                        </div>
                        <form method="POST" action="{{ route('ventiq.money.payouts.paid', $batch) }}" class="flex flex-wrap items-center gap-2">@csrf
                            <select name="method" class="{{ $input }}" aria-label="How it was paid">
                                @foreach($methods as $value => $name)<option value="{{ $value }}">{{ $name }}</option>@endforeach
                            </select>
                            <input name="reference" required placeholder="Transfer reference" class="{{ $input }} w-40" aria-label="Transfer reference">
                            <button class="{{ $button }} bg-mint-ink text-white">Mark paid</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Fees to invoice --}}
    <section class="space-y-3">
        <h2 class="text-[13px] font-black text-[#1D4069] uppercase tracking-widest">Fees to invoice</h2>
        <p class="text-[12px] text-gray-500">Tickets paid directly to organizers, free and complimentary tickets. Download the lines for the invoicing system, then record the invoice number here.</p>

        <div class="{{ $card }} divide-y divide-gray-50">
            @forelse($toInvoice as $row)
                <div class="p-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[14px] font-black text-[#1D4069]">{{ $row->organization?->name }}</p>
                        <p class="text-[11px] text-gray-500">{{ $row->tickets }} {{ \Illuminate\Support\Str::plural('ticket', $row->tickets) }} · {{ $row->people }} {{ \Illuminate\Support\Str::plural('person', (int) $row->people) }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-lg font-black text-lilac-ink mr-2">{{ $m($row->total) }}</span>
                        <a href="{{ route('ventiq.money.fees.csv', [$row->organization_id, 'up_to' => $row->up_to]) }}" class="{{ $button }} bg-white border border-gray-200 text-[#1D4069]"><i class="fas fa-download mr-1"></i>CSV</a>
                        <form method="POST" action="{{ route('ventiq.money.fees.invoiced', $row->organization_id) }}" class="flex items-center gap-2">@csrf
                            <input type="hidden" name="up_to" value="{{ $row->up_to }}">
                            <input name="reference" required placeholder="Invoice number" class="{{ $input }} w-36" aria-label="Invoice number">
                            <button class="{{ $button }} bg-brand text-white hover:bg-action">Mark invoiced</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="p-6 text-[12px] text-gray-400">Nothing to invoice.</p>
            @endforelse
        </div>

        @if($awaiting->isNotEmpty())
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1 pt-2">Invoices waiting for payment</p>
            <div class="{{ $card }} divide-y divide-gray-50">
                @foreach($awaiting as $row)
                    <div class="p-5 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-[14px] font-black text-[#1D4069]">{{ $row->organization?->name }} · {{ $row->invoice_reference }}</p>
                            <p class="text-[11px] text-gray-500">{{ $row->tickets }} {{ \Illuminate\Support\Str::plural('ticket', $row->tickets) }} · invoiced {{ \Illuminate\Support\Carbon::parse($row->invoiced_at)->format('j M Y') }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-lg font-black text-action-ink">{{ $m($row->total) }}</span>
                            <form method="POST" action="{{ route('ventiq.money.fees.paid', $row->organization_id) }}">@csrf
                                <input type="hidden" name="reference" value="{{ $row->invoice_reference }}">
                                <button class="{{ $button }} bg-mint-ink text-white">Mark paid</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
