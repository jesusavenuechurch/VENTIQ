@extends('layouts.attendee')

@section('title', "Your ticket | {$ticket->event->name}")

@push('head')
    <meta name="robots" content="noindex">
    @vite('resources/js/ticket.js')
@endpush

@php
    $event = $ticket->event;
    $first = \Illuminate\Support\Str::before($ticket->holder_name ?? '', ' ') ?: 'there';
    $tierColor = str_contains(strtolower($ticket->tier->tier_name), 'vip') ? '#B8860B' : '#1E7B4B';
    $admits = (int) ($ticket->admissions ?? 1);
@endphp

@section('content')
<div class="text-center mb-5">
    <h1 class="text-2xl font-black tracking-tight">
        {{ $ticket->status === 'checked_in' ? "Welcome in, {$first}!" : "You're in, {$first}!" }}
    </h1>
    <p class="text-[13px] text-gray-500">Show this QR code at the door. A screenshot or print works too.</p>
</div>

{{-- The pass (also what "Save image" captures) --}}
<div id="ticket-capture" class="mx-auto max-w-sm">
    <div class="rounded-[2rem] overflow-hidden bg-white border border-gray-100 shadow-xl">
        <div class="p-5 bg-[#1D4069] text-white">
            <div class="flex items-center justify-between gap-2">
                <span class="px-2.5 py-1 rounded-full text-[11px] font-black text-white" style="background-color: {{ $tierColor }}">{{ $ticket->tier->tier_name }}</span>
                @if($ticket->status === 'checked_in')
                    <span class="px-2.5 py-1 rounded-full bg-white/15 text-[11px] font-black"><i class="fas fa-check mr-1"></i>Checked in</span>
                @endif
            </div>
            <p class="mt-3 text-[22px] font-black leading-tight">{{ $event->name }}</p>
            <p class="mt-1 text-[12px] text-white/70">
                {{ $event->event_date?->format('D j M Y, g:i A') }}
                @if($event->venue ?: $event->location)<br>{{ $event->venue ?: $event->location }}@endif
            </p>
        </div>

        <div class="p-6 flex flex-col items-center">
            <div class="w-60 h-60 p-3 rounded-3xl border border-gray-100 bg-white">
                <img src="{{ route('ticket.qr', $ticket->qr_code) }}" crossorigin="anonymous" alt="Your entry QR code" class="w-full h-full object-contain">
            </div>

            @if($ticket->voucher_code)
                <div class="mt-4 px-5 py-2.5 rounded-2xl border-2 border-dashed border-gray-200 text-center">
                    <p class="text-[10px] font-black text-gray-400">Entry code, if the QR won't scan</p>
                    <p class="font-mono text-[22px] font-black tracking-[0.2em]">{{ $ticket->voucher_code }}</p>
                </div>
            @endif
        </div>

        <div class="px-6 pb-6 grid grid-cols-2 gap-4 border-t border-dashed border-gray-200 pt-5">
            <div class="min-w-0">
                <p class="text-[10px] font-black text-gray-400">Name</p>
                <p class="text-[14px] font-black truncate">{{ $ticket->holder_name }}</p>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-black text-gray-400">Admits</p>
                <p class="text-[14px] font-black">{{ $admits }} {{ $admits === 1 ? 'person' : 'people' }}</p>
            </div>
            <div class="col-span-2 flex items-center justify-between text-[11px] text-gray-400">
                <span class="font-mono">{{ $ticket->ticket_number }}</span>
                <span>{{ $event->organization->name }} · VENTIQ</span>
            </div>
        </div>
    </div>
</div>

<div class="mx-auto max-w-sm mt-5 grid grid-cols-2 gap-3">
    <button id="download-png" data-filename="Ventiq-{{ $ticket->ticket_number }}.png" class="py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[11px] font-black uppercase tracking-widest">
        <i class="fas fa-image mr-1"></i>Save image
    </button>
    <a href="{{ route('ticket.avatar.download', $qrCode) }}" class="py-4 rounded-2xl bg-white border border-gray-200 hover:border-[#1D4069] text-center text-[11px] font-black uppercase tracking-widest">
        <i class="fas fa-file-pdf mr-1 text-rose-500"></i>PDF ticket
    </a>
</div>

@if($admits > 1)
    <p class="mx-auto max-w-sm mt-4 p-4 rounded-2xl bg-lilac text-lilac-ink text-[12px] font-bold text-center">
        <i class="fas fa-users mr-1"></i>One code for your whole group: it's scanned once for each of the {{ $admits }} people.
    </p>
@endif

<p class="mx-auto max-w-sm mt-4 text-center text-[12px] text-gray-400">Keep this link to yourself: anyone with it can see your ticket.</p>
@endsection
