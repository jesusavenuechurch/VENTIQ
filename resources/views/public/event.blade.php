@extends('layouts.attendee')

@section('title', "{$event->name} | {$organization->name}")
@section('width', '5xl')

@push('head')
    <meta property="og:title" content="{{ $event->name }}">
    <meta property="og:description" content="{{ Str::limit($event->description, 150) }}">
    @if($event->banner_image)<meta property="og:image" content="{{ Storage::url($event->banner_image) }}">@endif
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">
@endpush

@php
    $date = $event->event_date;
    $openTiers = $event->tiers->filter(fn ($t) => !($tierAvailability[$t->id]['is_sold_out'] ?? false));
@endphp

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-10">

    {{-- About the event --}}
    <div class="lg:col-span-7 space-y-6">
        @if($event->banner_image)
            <div class="overflow-hidden rounded-[1.5rem] border border-gray-100 bg-white">
                <img src="{{ Storage::url($event->banner_image) }}" alt="{{ $event->name }} poster" class="w-full max-h-[420px] object-contain bg-slate-50">
            </div>
        @endif

        <div>
            @if($event->status === 'draft')
                <p class="mb-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-lilac text-lilac-ink text-[12px] font-bold"><i class="fas fa-eye"></i>Preview: only your team can see this draft. Publish it to open registration.</p>
            @elseif($closedReason ?? null)
                <p class="mb-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-action-soft text-action-ink text-[12px] font-bold"><i class="fas fa-circle-info"></i>{{ $closedReason }}</p>
            @endif
            <h1 class="text-3xl md:text-5xl font-black tracking-tight leading-[1.05]">{{ $event->name }}</h1>
            <p class="mt-2 text-[13px] font-medium text-gray-500">Hosted by {{ $organization->name }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @if($date)
            <div class="flex items-start gap-3 p-4 rounded-[1.25rem] bg-white border border-gray-100">
                <span class="w-10 h-10 shrink-0 rounded-xl bg-action-soft text-action-ink flex items-center justify-center"><i class="far fa-calendar"></i></span>
                <div>
                    <p class="text-[14px] font-black">{{ $date->format('l, j F Y') }}</p>
                    <p class="text-[12px] text-gray-500">Starts {{ $date->format('g:i A') }}</p>
                </div>
            </div>
            @endif
            @if($event->venue || $event->city)
            <div class="flex items-start gap-3 p-4 rounded-[1.25rem] bg-white border border-gray-100">
                <span class="w-10 h-10 shrink-0 rounded-xl bg-mint text-mint-ink flex items-center justify-center"><i class="fas fa-location-dot"></i></span>
                <div class="min-w-0">
                    <p class="text-[14px] font-black leading-snug">{{ $event->venue ?: $event->city }}</p>
                    @if($event->venue && $event->city)<p class="text-[12px] text-gray-500">{{ $event->city }}</p>@endif
                </div>
            </div>
            @endif
        </div>

        @if($event->description)
            <section class="p-5 rounded-[1.5rem] bg-white border border-gray-100">
                <h2 class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-3">About this event</h2>
                <div class="text-[14px] leading-relaxed text-gray-600 space-y-3">{!! nl2br(e($event->description)) !!}</div>
            </section>
        @endif
    </div>

    {{-- Tickets --}}
    <div class="lg:col-span-5" id="tickets">
        <div class="lg:sticky lg:top-20 space-y-4">
            <section class="p-5 rounded-[1.5rem] bg-white border border-gray-100 shadow-sm">
                <h2 class="text-[18px] font-black">Tickets</h2>
                <p class="text-[12px] text-gray-500 mb-4">
                    {{ $canRegister ? 'Pick one to register. Paid tickets are held for you while you pay.' : 'Registration is closed.' }}
                </p>

                <div class="space-y-3">
                    @forelse($event->tiers as $tier)
                        @php
                            $a = $tierAvailability[$tier->id] ?? ['is_sold_out' => false, 'available' => null];
                            $soldOut = $a['is_sold_out'];
                            $fewLeft = !$soldOut && $a['available'] !== null && $a['available'] <= 10;
                            $group = (int) ($tier->quantity_per_purchase ?? 1);
                        @endphp
                        <a @unless($soldOut) href="{{ route('registration.form', [$organization->slug, $event->slug, 'tier' => $tier->id]) }}" @endunless
                           class="block p-4 rounded-[1.25rem] border-2 transition-all {{ $soldOut ? 'border-gray-100 bg-slate-50 opacity-60 cursor-not-allowed' : 'border-gray-100 hover:border-[#F07F22] hover:bg-action-soft/40' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[15px] font-black leading-tight">{{ $tier->tier_name }}</p>
                                    @if($tier->description)<p class="text-[12px] text-gray-500 mt-0.5">{{ $tier->description }}</p>@endif
                                </div>
                                <p class="text-[18px] font-black whitespace-nowrap {{ $tier->price > 0 ? '' : 'text-mint-ink' }}">{{ \App\Support\Money::price($tier->price) }}</p>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px] font-bold">
                                @if($group > 1)
                                    <span class="px-2.5 py-1 rounded-full bg-lilac text-lilac-ink"><i class="fas fa-users mr-1"></i>Admits {{ $group }}</span>
                                @endif
                                @if($event->allow_installments && $tier->price > 0 && !$soldOut)
                                    <span class="px-2.5 py-1 rounded-full bg-mint text-mint-ink">Pay from {{ \App\Support\Money::price($tier->price * ($event->minimum_deposit_percentage / 100)) }}</span>
                                @endif
                                @if($fewLeft)
                                    <span class="px-2.5 py-1 rounded-full bg-action-soft text-action-ink">Only {{ $a['available'] }} left</span>
                                @endif
                                @if($soldOut)
                                    <span class="px-2.5 py-1 rounded-full bg-gray-200 text-gray-500">{{ $canRegister ? 'Sold out' : 'Closed' }}</span>
                                @else
                                    <span class="ml-auto text-[#F07F22] font-black">{{ $tier->price > 0 ? 'Get ticket' : 'Register' }} <i class="fas fa-arrow-right ml-0.5"></i></span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <p class="text-[13px] text-gray-500">No tickets are on sale yet.</p>
                    @endforelse
                </div>
            </section>

            <a href="{{ route('ticket.find') }}" class="flex items-center gap-3 p-4 rounded-[1.25rem] bg-white border border-gray-100 hover:border-[#1D4069]">
                <span class="w-10 h-10 shrink-0 rounded-xl bg-slate-100 flex items-center justify-center"><i class="fas fa-ticket"></i></span>
                <span class="min-w-0">
                    <span class="block text-[13px] font-black">Already registered?</span>
                    <span class="block text-[12px] text-gray-500">Find your ticket to pay, pay the rest, or show it at the door.</span>
                </span>
            </a>
        </div>
    </div>
</div>

{{-- Phones: one tap down to the tickets. --}}
@if($canRegister && $openTiers->isNotEmpty())
    <div class="lg:hidden fixed inset-x-0 bottom-0 z-30 p-4 bg-gradient-to-t from-gray-50 via-gray-50/95 to-transparent">
        <a href="#tickets" class="flex items-center justify-center gap-2 w-full py-4 rounded-2xl bg-[#1D4069] text-white text-[12px] font-black uppercase tracking-widest shadow-xl">
            <i class="fas fa-ticket text-[#F07F22]"></i>Get tickets
            <span class="font-bold normal-case tracking-normal text-white/70">· from {{ \App\Support\Money::price($openTiers->min('price')) }}</span>
        </a>
    </div>
@endif
@endsection
