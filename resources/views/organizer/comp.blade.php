@extends('layouts.app')
@section('title', 'Issue a ticket · ' . $event->name . ' | VENTIQ')
@section('content')
@php
    $field = 'w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-4 py-3 text-[14px] font-semibold text-gray-900 focus:bg-white focus:border-[#F07F22] outline-none transition-all';
    $label = 'block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1';
    $selected = (int) old('event_tier_id', $tiers->firstWhere('is_full', false)?->id);
@endphp
<div class="max-w-5xl mx-auto px-4 py-8"
     x-data="{ kind: @js($kind), tier: {{ $selected ?: 'null' }},
               people: @js($tiers->mapWithKeys(fn ($t) => [$t->id => max(1, (int) ($t->quantity_per_purchase ?? 1))])),
               price: @js($tiers->mapWithKeys(fn ($t) => [$t->id => (float) $t->price])) }">
    @include('organizer.partials.header', [
        'title'    => 'Issue a ticket',
        'subtitle' => $event->name . ' · for someone who paid you in person, or a guest who comes free. It\'s active straight away.',
        'crumbs'   => [['Events', route('organizer.home')], [$event->name, route('organizer.events.attendees', $event)], ['Issue a ticket']],
    ])
    {{-- Narrower than the page frame, left-aligned under the tabs. --}}
    <div class="max-w-3xl">

    @if($errors->any())
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-[12px] font-bold text-rose-700">
            <ul class="list-disc ml-5 font-medium">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('organizer.events.comp.store', $event) }}" class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6 sm:p-8 space-y-6">
        @csrf
        <input type="hidden" name="kind" :value="kind">

        <div class="grid grid-cols-2 gap-2">
            <button type="button" x-on:click="kind = 'sold'" class="p-4 rounded-2xl border-2 text-left transition-all"
                    :class="kind === 'sold' ? 'border-[#F07F22] bg-action-soft' : 'border-slate-50 bg-slate-50'">
                <span class="block text-[13px] font-black text-[#1D4069]"><i class="fas fa-money-bill-wave mr-1"></i>Sold</span>
                <span class="block text-[11px] font-medium text-gray-500">They paid you: cash at your office or a reseller, or a transfer you've checked.</span>
            </button>
            <button type="button" x-on:click="kind = 'comp'" class="p-4 rounded-2xl border-2 text-left transition-all"
                    :class="kind === 'comp' ? 'border-[#F07F22] bg-action-soft' : 'border-slate-50 bg-slate-50'">
                <span class="block text-[13px] font-black text-[#1D4069]"><i class="fas fa-gift mr-1"></i>Complimentary</span>
                <span class="block text-[11px] font-medium text-gray-500">Free entry for a speaker, sponsor or guest.</span>
            </button>
        </div>

        <div>
            <span class="{{ $label }}">Ticket type</span>
            <div class="grid sm:grid-cols-2 gap-2">
                @foreach($tiers as $tier)
                    <label class="flex items-center gap-3 p-4 rounded-2xl border-2 transition-all {{ $tier->is_full ? 'opacity-50 cursor-not-allowed border-slate-50 bg-slate-50' : 'cursor-pointer' }}"
                           :class="tier === {{ $tier->id }} ? 'border-[#F07F22] bg-action-soft' : 'border-slate-50 bg-slate-50'">
                        <input type="radio" name="event_tier_id" value="{{ $tier->id }}" x-model.number="tier" @disabled($tier->is_full) class="accent-[#F07F22]">
                        <span class="min-w-0">
                            <span class="block text-[13px] font-black text-[#1D4069]">{{ $tier->tier_name }}</span>
                            <span class="block text-[11px] font-medium text-gray-500">
                                @if(($tier->quantity_per_purchase ?? 1) > 1) Admits {{ $tier->quantity_per_purchase }} · @endif
                                {{ $tier->is_full ? 'Full' : 'M' . number_format((float) $tier->price, 2) }}
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label for="full_name" class="{{ $label }}">Guest's full name</label>
                <input id="full_name" name="full_name" value="{{ old('full_name') }}" required class="{{ $field }}">
            </div>
            <div>
                <label for="phone" class="{{ $label }}">WhatsApp number</label>
                <input id="phone" name="phone" value="{{ old('phone') }}" required inputmode="tel" placeholder="5949 4756" class="{{ $field }}">
                <p class="text-[11px] text-gray-400 mt-1 ml-1">Lesotho numbers need just the 8 digits. Others: start with +.</p>
            </div>
            <div>
                <label for="email" class="{{ $label }}">Email (optional)</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" class="{{ $field }}">
            </div>
            <div class="sm:col-span-2" x-show="kind === 'comp'">
                <label for="reason" class="{{ $label }}">Reason (for your records)</label>
                <input id="reason" name="reason" value="{{ old('reason') }}" placeholder="e.g. Keynote speaker" class="{{ $field }}">
            </div>
            <div x-show="kind === 'sold'">
                <label for="method" class="{{ $label }}">Paid with</label>
                <select id="method" name="method" class="{{ $field }}">
                    @foreach($methods as $value => $name)
                        <option value="{{ $value }}" @selected(old('method', 'cash') === $value)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="kind === 'sold'">
                <label for="reference" class="{{ $label }}">Receipt or reference (optional)</label>
                <input id="reference" name="reference" value="{{ old('reference') }}" placeholder="e.g. Receipt 0042" class="{{ $field }}">
            </div>
        </div>

        <label class="flex items-center gap-3 text-[13px] font-bold text-[#1D4069]">
            <input type="checkbox" name="send_whatsapp" value="1" @checked(old('send_whatsapp', true)) class="w-5 h-5 rounded accent-[#F07F22]">
            Send the ticket to their WhatsApp now
        </label>

        @if($event->fees_sponsored)
            <p class="p-4 rounded-2xl bg-lilac text-[12px] font-bold text-lilac-ink"><i class="fas fa-gift mr-1"></i>VENTIQ is sponsoring this event's fees, so this ticket costs you nothing.</p>
        @else
            <p class="p-4 rounded-2xl bg-lilac text-[12px] font-medium text-lilac-ink" x-show="kind === 'comp'">
                <i class="fas fa-circle-info mr-1"></i>
                Complimentary tickets carry VENTIQ's operational fee of M{{ number_format($feeEach, 2) }} per person
                (<strong x-text="'M' + ({{ $feeEach }} * (people[tier] || 1)).toFixed(2)"></strong> for this ticket), added to your fee invoice.
            </p>
            <p class="p-4 rounded-2xl bg-lilac text-[12px] font-medium text-lilac-ink" x-show="kind === 'sold'">
                <i class="fas fa-circle-info mr-1"></i>
                You keep the money. VENTIQ's fee on this ticket
                (<strong x-text="'M' + (({{ $feePercent }} * (price[tier] || 0)) + {{ $feeEach }} * (people[tier] || 1)).toFixed(2)"></strong>)
                is added to your fee invoice, like any ticket paid to you.
            </p>
        @endif

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button class="px-6 py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[11px] font-black uppercase tracking-[0.2em]">
                <span x-show="kind === 'sold'"><i class="fas fa-money-bill-wave mr-1"></i>Sell ticket · <span x-text="'M' + (price[tier] || 0).toFixed(2)"></span></span>
                <span x-show="kind === 'comp'"><i class="fas fa-gift mr-1"></i>Issue free ticket</span>
            </button>
            <a href="{{ route('organizer.events.attendees', $event) }}" wire:navigate class="text-[11px] font-black uppercase tracking-widest text-gray-400 hover:text-[#1D4069]">Cancel</a>
        </div>
    </form>
    </div>
</div>
@endsection
