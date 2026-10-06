@extends('layouts.attendee')

@section('title', "You're registered | {$event->name}")

@php
    $paid    = $ticket->payment_status === 'completed';
    $owed    = max(0, $allTickets->sum('amount') - $allTickets->sum('amount_paid'));
    $first   = \Illuminate\Support\Str::before($ticket->holder_name ?? '', ' ') ?: 'there';
    $waiting = !$paid && ($paymentMethodDetails || ($byHand ?? false));
@endphp

@section('content')
<div class="text-center mb-6">
    <div class="mx-auto mb-4 w-20 h-20 rounded-3xl flex items-center justify-center shadow-lg {{ $paid ? 'bg-mint text-mint-ink' : 'bg-action-soft text-action-ink' }}">
        <i class="fas {{ $paid ? 'fa-check' : ($waiting ? 'fa-hourglass-half' : 'fa-ticket') }} text-3xl"></i>
    </div>
    <h1 class="text-3xl font-black tracking-tight">
        {{ $paid ? "You're in, {$first}!" : ($waiting ? "Thanks, {$first}! We're on it" : "You're registered, {$first}!") }}
    </h1>
    <p class="mt-2 text-[14px] text-gray-500 max-w-md mx-auto">
        @if($paid)
            Your ticket is ready. Show it at the door, on your phone or printed.
        @elseif($byHand ?? false)
            VENTIQ is matching your EcoCash payment with the merchant statement, usually within a few hours. Your place is held and your ticket comes as soon as it's confirmed. No need to pay again.
        @elseif($paymentMethodDetails)
            {{ $event->organization->name }} is checking your payment. Your place is held, and your ticket activates as soon as they confirm it. We'll let you know.
        @else
            Your place is held. Pay to activate your ticket.
        @endif
    </p>
</div>

<section class="rounded-[1.5rem] bg-white border border-gray-100 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-gray-50 flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="text-[16px] font-black truncate">{{ $event->name }}</p>
            <p class="text-[12px] text-gray-500">{{ $event->event_date?->format('D j M Y, g:i A') }}@if($event->venue) · {{ $event->venue }}@endif</p>
        </div>
        <span class="px-3 py-1 rounded-full text-[11px] font-black {{ $paid ? 'bg-mint text-mint-ink' : 'bg-action-soft text-action-ink' }}">{{ $paid ? 'Active' : 'Not active yet' }}</span>
    </div>

    <div class="divide-y divide-gray-50">
        @foreach($allTickets as $singleTicket)
            <div class="p-5 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <x-avatar :seed="$singleTicket->holder_name" size="w-11 h-11" />
                    <div class="min-w-0">
                        <p class="text-[15px] font-black truncate">{{ $singleTicket->holder_name }}</p>
                        <p class="text-[12px] text-gray-500">
                            {{ $singleTicket->tier->tier_name }}@if(($singleTicket->admissions ?? 1) > 1) · admits {{ $singleTicket->admissions }}@endif
                            · <span class="font-mono">{{ $singleTicket->ticket_number }}</span>
                        </p>
                    </div>
                </div>
                @if($paid)
                    <a href="{{ route('ticket.download', $singleTicket->qr_code) }}" class="px-5 py-3 rounded-2xl bg-[#F07F22] hover:bg-[#1D4069] text-white text-[11px] font-black uppercase tracking-widest">
                        <i class="fas fa-ticket mr-1"></i>Open ticket
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    @unless($paid)
        <div class="p-5 bg-slate-50 space-y-4">
            <div class="flex items-end justify-between">
                <p class="text-[13px] font-black text-gray-600">{{ $allTickets->sum('amount_paid') > 0 ? 'Balance left' : 'To pay' }}</p>
                <p class="text-[26px] font-black leading-none">M{{ number_format($owed, 2) }}</p>
            </div>

            @if($paymentMethodDetails)
                <div class="p-4 rounded-2xl bg-white border border-gray-100">
                    <p class="text-[11px] font-black text-gray-400">Paid to</p>
                    <p class="text-[14px] font-black">{{ $paymentMethodDetails->display_label }}</p>
                    @if($paymentMethodDetails->account_number)
                        <p class="font-mono text-[13px] text-gray-600">{{ $paymentMethodDetails->account_number }}</p>
                    @endif
                    @if($paymentMethodDetails->instructions)
                        <p class="mt-2 text-[12px] text-gray-500">"{{ $paymentMethodDetails->instructions }}"</p>
                    @endif
                </div>
            @endif

            @unless($waiting)
                <a href="{{ route('ticket.pay', $ticket->qr_code) }}" class="block text-center w-full py-4 rounded-2xl bg-[#F07F22] hover:bg-[#1D4069] text-white text-[12px] font-black uppercase tracking-widest">Pay now</a>
            @endunless
        </div>
    @endunless

    @if($paid && ($ticket->client->email || $ticket->has_whatsapp))
        <div class="p-5 bg-slate-50 flex flex-wrap gap-2 text-[12px] font-bold">
            @if($ticket->client->email)
                <span class="px-3 py-1.5 rounded-full bg-white border border-gray-100"><i class="fas fa-envelope mr-1 text-gray-400"></i>Sent to {{ $ticket->client->email }}</span>
            @endif
            @if($ticket->has_whatsapp)
                <span class="px-3 py-1.5 rounded-full bg-mint text-mint-ink"><i class="fa-brands fa-whatsapp mr-1"></i>Sent on WhatsApp</span>
            @endif
        </div>
    @endif
</section>

@include('tickets.partials.save-link', ['ticket' => $ticket, 'class' => 'mt-4'])

<a href="{{ route('event.short', [$event->organization->slug, $event->slug]) }}" class="mt-4 block text-center text-[12px] font-bold text-gray-500 hover:text-[#1D4069]">
    <i class="fas fa-arrow-left mr-1 text-[10px]"></i>Back to the event
</a>
@endsection
