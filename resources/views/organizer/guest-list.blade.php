@extends('layouts.app')
@section('title', 'Import guest list · ' . $event->name . ' | VENTIQ')
@section('content')
@php
    $field = 'w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-4 py-3 text-[14px] font-semibold text-gray-900 focus:bg-white focus:border-[#F07F22] outline-none transition-all';
    $label = 'block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1';
    $card  = 'bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6 sm:p-8';
@endphp
<div class="max-w-5xl mx-auto px-4 py-8">
    @include('organizer.partials.header', [
        'title'    => 'Import a guest list',
        'subtitle' => $event->name . ' · tickets for many people at once, from a spreadsheet.',
        'crumbs'   => [['Events', route('organizer.home')], [$event->name, route('organizer.events.attendees', $event)], ['Import a guest list']],
    ])
    {{-- Narrower than the page frame, left-aligned under the tabs. --}}
    <div class="max-w-3xl">

    @if($errors->any())
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-[12px] font-bold text-rose-700">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    @if(!$preview)
        {{-- Step 1: the file and what the tickets are. --}}
        <form method="POST" action="{{ route('organizer.events.guests.check', $event) }}" enctype="multipart/form-data"
              class="{{ $card }} space-y-6" x-data="{ mode: @js(old('mode', 'complimentary')) }">
            @csrf

            <div>
                <span class="{{ $label }}">1 · Your spreadsheet</span>
                <input type="file" name="file" required accept=".csv,.xlsx,.xls">
                <p class="text-[11px] text-gray-500 mt-2">
                    CSV or Excel with a header row: <span class="font-mono">full_name</span>, <span class="font-mono">phone</span>, and optionally <span class="font-mono">email</span>. Up to 500 people.
                    <a href="{{ route('organizer.guests.template') }}" class="font-bold text-action-ink hover:underline"><i class="fas fa-download mr-0.5"></i>Download a template</a>
                </p>
            </div>

            <div>
                <label for="tier" class="{{ $label }}">2 · Ticket type for everyone on the list</label>
                <select id="tier" name="event_tier_id" required class="{{ $field }}">
                    @foreach($tiers as $tier)
                        <option value="{{ $tier->id }}" @selected(old('event_tier_id') == $tier->id)>
                            {{ $tier->tier_name }} · M{{ number_format((float) $tier->price, 2) }}@if(($tier->quantity_per_purchase ?? 1) > 1) · admits {{ $tier->quantity_per_purchase }}@endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <span class="{{ $label }}">3 · These tickets are</span>
                <div class="grid sm:grid-cols-2 gap-2">
                    @foreach(['complimentary' => ['Complimentary', 'Free: speakers, sponsors, invited guests.'], 'paid_direct' => ['Already paid to you', 'People who paid you in cash, by EcoCash, M-Pesa or bank.']] as $value => [$title, $hint])
                        <label class="flex gap-3 p-4 rounded-2xl border-2 cursor-pointer transition-all" :class="mode === '{{ $value }}' ? 'border-[#F07F22] bg-action-soft' : 'border-slate-50 bg-slate-50'">
                            <input type="radio" name="mode" value="{{ $value }}" x-model="mode" class="mt-0.5 accent-[#F07F22]">
                            <span><span class="block text-[13px] font-black text-[#1D4069]">{{ $title }}</span><span class="block text-[11px] text-gray-500">{{ $hint }}</span></span>
                        </label>
                    @endforeach
                </div>
                <div class="mt-3" x-show="mode === 'complimentary'">
                    <input name="reason" value="{{ old('reason') }}" placeholder="Reason, for your records (e.g. Invited partners)" class="{{ $field }}">
                </div>
                <div class="mt-3" x-show="mode === 'paid_direct'" x-cloak>
                    <label for="method" class="{{ $label }}">How they paid</label>
                    <select id="method" name="method" class="{{ $field }}">
                        @foreach($methods as $value => $name)<option value="{{ $value }}" @selected(old('method') === $value)>{{ $name }}</option>@endforeach
                    </select>
                </div>
            </div>

            <label class="flex items-center gap-3 text-[13px] font-bold text-[#1D4069]">
                <input type="checkbox" name="send_whatsapp" value="1" @checked(old('send_whatsapp', true)) class="w-5 h-5 rounded accent-[#F07F22]">
                Send each person their ticket on WhatsApp
            </label>

            <button class="w-full sm:w-auto px-6 py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[11px] font-black uppercase tracking-[0.2em]">
                Check the list <i class="fas fa-arrow-right ml-1"></i>
            </button>
            <p class="text-[11px] text-gray-400">Nothing is created yet: you'll see every row checked first.</p>
        </form>
    @else
        {{-- Step 2: every row checked; confirm to create. --}}
        <div class="{{ $card }} mb-4">
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-3 py-1 rounded-full bg-mint text-mint-ink text-[11px] font-black">{{ $preview['ok'] }} ready</span>
                @if($preview['problems'])
                    <span class="px-3 py-1 rounded-full bg-rose-50 text-rose-700 text-[11px] font-black"><i class="fas fa-triangle-exclamation mr-1"></i>{{ $preview['problems'] }} with a problem, will be skipped</span>
                @endif
            </div>
            <p class="mt-3 text-[13px] text-gray-600">
                <strong class="text-[#1D4069]">{{ $preview['tier']->tier_name }}</strong> tickets,
                {{ $preview['mode'] === 'complimentary' ? 'complimentary' : 'recorded as paid to you (M' . number_format((float) $preview['tier']->price, 2) . ' each)' }}{{ $preview['send_whatsapp'] ? ', sent on WhatsApp.' : ', not sent yet.' }}
            </p>
            @unless($event->fees_sponsored)
                <p class="mt-2 text-[11px] font-medium text-lilac-ink">
                    VENTIQ fees apply to each ticket as usual{{ $preview['mode'] === 'complimentary' ? ' (M' . number_format((float) config('constants.fees.operational_per_person'), 2) . ' per person)' : '' }}, added to your fee invoice.
                </p>
            @endunless
        </div>

        <div class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm overflow-hidden mb-6">
            <div class="max-h-[50vh] overflow-y-auto">
                <table class="w-full text-left text-[12px]">
                    <thead class="sticky top-0 bg-slate-50 text-[10px] font-black uppercase tracking-widest text-gray-400">
                        <tr><th class="px-4 py-2">Row</th><th class="px-4 py-2">Name</th><th class="px-4 py-2">Phone</th><th class="px-4 py-2">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($preview['rows'] as $row)
                            <tr class="{{ $row['problem'] ? 'bg-rose-50/40' : '' }}">
                                <td class="px-4 py-2 text-gray-400">{{ $row['row'] }}</td>
                                <td class="px-4 py-2 font-bold text-[#1D4069]">{{ $row['full_name'] ?: '—' }}</td>
                                <td class="px-4 py-2 font-mono text-gray-600">{{ $row['phone'] ?: '—' }}</td>
                                <td class="px-4 py-2">
                                    @if($row['problem'])
                                        <span class="text-rose-700 font-bold"><i class="fas fa-xmark mr-1"></i>{{ $row['problem'] }}</span>
                                    @else
                                        <span class="text-mint-ink font-bold"><i class="fas fa-check mr-1"></i>Ready</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if($preview['ok'])
                <form method="POST" action="{{ route('organizer.events.guests.store', $event) }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $preview['token'] }}">
                    <button class="px-6 py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[11px] font-black uppercase tracking-[0.2em]">
                        Create {{ $preview['ok'] }} {{ \Illuminate\Support\Str::plural('ticket', $preview['ok']) }}
                    </button>
                </form>
            @endif
            <a href="{{ route('organizer.events.guests.create', $event) }}" wire:navigate class="text-[11px] font-black uppercase tracking-widest text-gray-400 hover:text-[#1D4069]">Fix the file and upload again</a>
        </div>
    @endif
    </div>
</div>
@endsection
