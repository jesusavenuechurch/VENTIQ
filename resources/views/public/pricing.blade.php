@extends('layouts.app')

@section('title', 'Pricing | Event tickets in Lesotho - VENTIQ')

@php
    // From config, so the page always matches what's charged.
    $rate = (float) config('constants.fees.service_percent');      // 0.049
    $perPerson = (float) config('constants.fees.operational_per_person'); // 7.50
    $pct = rtrim(rtrim(number_format($rate * 100, 2), '0'), '.');
    $m = fn ($v) => 'M' . number_format($v, 2);
    $examples = [
        ['label' => 'M100 ticket', 'price' => 100, 'people' => 1],
        ['label' => 'M250 ticket', 'price' => 250, 'people' => 1],
        ['label' => 'Table of 4 at M900', 'price' => 900, 'people' => 4],
        ['label' => 'Free ticket', 'price' => 0, 'people' => 1],
    ];
    $sessionPlans = collect(\App\Support\SessionPackageDefinition::tiers());
@endphp

@section('content')
<div class="bg-gray-50 text-[#1D4069]">

    {{-- The headline --}}
    <section class="max-w-5xl mx-auto px-4 pt-14 pb-10 text-center">
        <span class="inline-block px-3 py-1 rounded-full bg-mint text-mint-ink text-[11px] font-black uppercase tracking-widest">No monthly fee for events</span>
        <h1 class="mt-4 text-4xl md:text-6xl font-black tracking-tight leading-[1.05]">Free to start.<br class="hidden sm:block"> You pay only when people come.</h1>
        <p class="mt-4 text-[16px] text-gray-500 max-w-2xl mx-auto">Create your event, sell tickets, take payments and scan people in at the door. VENTIQ takes a small fee on each ticket, and nothing else.</p>
    </section>

    {{-- The fee --}}
    <section class="max-w-5xl mx-auto px-4 grid grid-cols-1 lg:grid-cols-5 gap-6">
        <div class="lg:col-span-3 rounded-[2rem] bg-[#1D4069] text-white p-8">
            <p class="text-[12px] font-black uppercase tracking-widest text-white/60">VENTIQ Events · per ticket</p>
            <div class="mt-4 flex flex-wrap items-end gap-x-4 gap-y-2">
                <p class="text-5xl md:text-6xl font-black leading-none">{{ $pct }}%</p>
                <p class="text-2xl font-black text-[#F07F22] leading-none pb-1">+ {{ $m($perPerson) }}</p>
            </div>
            <p class="mt-3 text-[14px] text-white/75">{{ $pct }}% of the ticket price, plus {{ $m($perPerson) }} for each person the ticket admits. Free and complimentary tickets: {{ $m($perPerson) }} per person only.</p>

            <ul class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3 text-[14px]">
                @foreach([
                    ['fa-globe', 'Your event page and registration'],
                    ['fa-mobile-screen', 'EcoCash payments online'],
                    ['fa-building-columns', 'Or payments straight to your account'],
                    ['fa-qrcode', 'QR tickets by WhatsApp and email'],
                    ['fa-door-open', 'Door scanning and entry codes'],
                    ['fa-users', 'Group tickets and deposits'],
                    ['fa-chart-simple', 'Event-day dashboard and reports'],
                    ['fa-user-group', 'Your team, with their own roles'],
                ] as [$icon, $text])
                    <li class="flex items-start gap-3"><i class="fas {{ $icon }} text-[#F07F22] mt-1 w-4"></i><span>{{ $text }}</span></li>
                @endforeach
            </ul>

            <a href="{{ route('org.register.direct') }}" class="mt-8 inline-block px-6 py-4 rounded-2xl bg-[#F07F22] hover:bg-white hover:text-[#1D4069] text-white text-[12px] font-black uppercase tracking-widest transition-colors">Create your free account</a>
        </div>

        {{-- Calculator --}}
        <div class="lg:col-span-2 rounded-[2rem] bg-white border border-gray-100 shadow-sm p-6"
             x-data="{ price: 150, people: 1, get fee() { const p = Math.max(0, +this.price || 0), n = Math.max(1, +this.people || 1); return Math.round((p * {{ $rate }} + n * {{ $perPerson }}) * 100) / 100; } }">
            <p class="text-[18px] font-black">What would I pay?</p>
            <p class="text-[12px] text-gray-500 mb-5">Try your own ticket price.</p>
            <label class="block text-[12px] font-black text-gray-600 mb-1.5" for="calc-price">Ticket price (M)</label>
            <input id="calc-price" type="number" min="0" step="10" x-model="price" class="w-full border-2 border-gray-100 rounded-2xl px-4 py-3 text-[16px] font-black outline-none focus:border-[#F07F22]">
            <label class="block text-[12px] font-black text-gray-600 mt-4 mb-1.5" for="calc-people">People it admits</label>
            <input id="calc-people" type="number" min="1" step="1" x-model="people" class="w-full border-2 border-gray-100 rounded-2xl px-4 py-3 text-[16px] font-black outline-none focus:border-[#F07F22]">

            <div class="mt-5 p-4 rounded-2xl bg-slate-50 space-y-2 text-[14px]">
                <div class="flex justify-between"><span class="text-gray-500">VENTIQ fee</span><span class="font-black" x-text="'M' + fee.toFixed(2)"></span></div>
                <div class="flex justify-between"><span class="text-gray-500">You keep</span><span class="font-black text-mint-ink" x-text="'M' + Math.max(0, (+price || 0) - fee).toFixed(2)"></span></div>
            </div>
            <p class="mt-3 text-[11px] text-gray-400">Online payment costs are on us: the fee is all you pay.</p>
        </div>
    </section>

    {{-- Examples --}}
    <section class="max-w-5xl mx-auto px-4 mt-10">
        <h2 class="text-2xl font-black">A few examples</h2>
        <div class="mt-4 rounded-[1.5rem] bg-white border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-[14px]">
                <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-widest text-gray-500">
                    <tr><th class="text-left p-4">Ticket</th><th class="text-right p-4">VENTIQ fee</th><th class="text-right p-4">You keep</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($examples as $e)
                        @php $fee = round($e['price'] * $rate + $e['people'] * $perPerson, 2); @endphp
                        <tr>
                            <td class="p-4 font-bold">{{ $e['label'] }}</td>
                            <td class="p-4 text-right font-black">{{ $m($fee) }}</td>
                            <td class="p-4 text-right font-black {{ $e['price'] > 0 ? 'text-mint-ink' : 'text-gray-400' }}">{{ $e['price'] > 0 ? $m($e['price'] - $fee) : 'M0.00 (free)' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- How it's collected --}}
    <section class="max-w-5xl mx-auto px-4 mt-10 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-[1.5rem] bg-white border border-gray-100 p-6">
            <span class="w-10 h-10 rounded-xl bg-lilac text-lilac-ink flex items-center justify-center"><i class="fas fa-bolt"></i></span>
            <p class="mt-3 text-[16px] font-black">Paid online</p>
            <p class="mt-1 text-[13px] text-gray-500">The money comes to VENTIQ. We take the fee off and pay the rest out to your account. Every payment is listed in your Accounts tab.</p>
        </div>
        <div class="rounded-[1.5rem] bg-white border border-gray-100 p-6">
            <span class="w-10 h-10 rounded-xl bg-action-soft text-action-ink flex items-center justify-center"><i class="fas fa-building-columns"></i></span>
            <p class="mt-3 text-[16px] font-black">Paid to you directly</p>
            <p class="mt-1 text-[13px] text-gray-500">The money goes straight to your EcoCash, M-Pesa, bank or cash box. You confirm it in VENTIQ, and the fees are invoiced to you.</p>
        </div>
        <div class="rounded-[1.5rem] bg-white border border-gray-100 p-6">
            <span class="w-10 h-10 rounded-xl bg-mint text-mint-ink flex items-center justify-center"><i class="fas fa-gift"></i></span>
            <p class="mt-3 text-[16px] font-black">Free events</p>
            <p class="mt-1 text-[13px] text-gray-500">No service fee, just {{ $m($perPerson) }} per person who registers, invoiced to you. Complimentary tickets you give away count the same.</p>
        </div>
    </section>

    {{-- Sessions --}}
    <section class="max-w-5xl mx-auto px-4 mt-14">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[12px] font-black uppercase tracking-widest text-gray-400">VENTIQ Sessions</p>
                <h2 class="text-2xl font-black">For regular meetings, trainings and programmes</h2>
                <p class="text-[13px] text-gray-500">Check-in, attendance cards and reports for sessions you run every month. Monthly plans.</p>
            </div>
        </div>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($sessionPlans as $key => $plan)
                <div class="rounded-[1.5rem] bg-white border {{ $key === 'team' ? 'border-[#F07F22]' : 'border-gray-100' }} p-6 flex flex-col">
                    <p class="text-[16px] font-black">{{ $plan['label'] }}</p>
                    <p class="mt-1 text-[24px] font-black">
                        @if($plan['price'] === null) Let's talk
                        @elseif($plan['price'] == 0) Free
                        @else M{{ number_format($plan['price']) }}<span class="text-[13px] font-bold text-gray-400">/month</span>
                        @endif
                    </p>
                    <p class="mt-2 text-[13px] text-gray-500 flex-1">{{ $plan['description'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Questions --}}
    <section class="max-w-3xl mx-auto px-4 mt-14 pb-20">
        <h2 class="text-2xl font-black text-center">Questions</h2>
        <div class="mt-5 space-y-3">
            @foreach([
                ['Is there a setup or monthly fee for events?', 'No. Creating an account and events is free. You only pay the per-ticket fee when tickets are issued.'],
                ['Do my attendees pay anything extra?', 'No. They pay your ticket price. The fee comes out of it, and online payment costs are covered by VENTIQ.'],
                ['What does "per person" mean on group tickets?', "A Table of 4 admits 4 people, so its fee includes " . $m($perPerson * 4) . " (4 × " . $m($perPerson) . ") plus {$pct}% of the table's price."],
                ['Can VENTIQ cover the fees for a good cause?', 'Sometimes we sponsor the fees for community and charity events. Get in touch.'],
            ] as [$q, $a])
                <details class="group rounded-2xl bg-white border border-gray-100 p-5">
                    <summary class="cursor-pointer list-none flex items-center justify-between gap-3 text-[15px] font-black">{{ $q }}<i class="fas fa-chevron-down text-gray-400 text-[12px] group-open:rotate-180 transition-transform"></i></summary>
                    <p class="mt-2 text-[14px] text-gray-600">{{ $a }}</p>
                </details>
            @endforeach
        </div>
        <div class="mt-8 text-center">
            <a href="{{ route('org.register.direct') }}" class="inline-block px-6 py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[12px] font-black uppercase tracking-widest">Start for free</a>
            <button type="button" @click="showChat = true" class="ml-2 px-6 py-4 rounded-2xl bg-white border border-gray-200 text-[12px] font-black uppercase tracking-widest">Talk to us</button>
        </div>
    </section>
</div>
@endsection
