@extends('layouts.attendee')

@section('title', "Your ticket | {$ticket->event->name}")

@push('head')<meta name="robots" content="noindex">@endpush

{{-- The ticket exists but won't scan: shown instead of the pass until it's
     activated, or once it has expired or been cancelled. No QR here, so a
     screenshot can't be mistaken for a valid ticket. --}}
@php
    $event = $ticket->event;
    $owed = max(0, (float) $ticket->amount - (float) $ticket->amount_paid);
    [$headline, $body, $tone, $icon] = match ($ticket->status) {
        'expired'   => ['Payment window ended', 'This ticket was not paid in time, so its place has been released. No payment is needed for it. If places are still available, you can register again.', 'rose', 'fa-calendar-xmark'],
        'cancelled', 'void', 'refunded' => ['Ticket cancelled', 'This ticket is no longer valid. Contact the organizer if you think this is a mistake.', 'rose', 'fa-ban'],
        default     => $submitted
            ? ['Payment being confirmed', 'Your payment is being confirmed. This ticket activates as soon as it is, and we will let you know. There is no need to pay again.', 'mint', 'fa-hourglass-half']
            : ['Not active yet: waiting for payment', 'Your place is held. Pay to activate this ticket; the same ticket then works at the door.', 'action', 'fa-lock'],
    };
    $toneClasses = [
        'action' => 'bg-action-soft text-action-ink',
        'mint'   => 'bg-mint text-mint-ink',
        'rose'   => 'bg-rose-50 text-rose-700',
    ][$tone];
@endphp

@section('content')
<section class="rounded-[1.5rem] bg-white border border-gray-100 shadow-sm overflow-hidden">
    <div class="p-5 {{ $toneClasses }} flex items-start gap-3">
        <span class="w-10 h-10 shrink-0 rounded-xl bg-white/70 flex items-center justify-center"><i class="fas {{ $icon }}"></i></span>
        <div>
            <p class="text-[16px] font-black">{{ $headline }}</p>
            <p class="text-[13px] mt-0.5">{{ $body }}</p>
            @if($ticket->status === 'pending' && $ticket->payment_due_at && !$submitted)
                <p class="text-[13px] font-black mt-1">Pay by {{ $ticket->payment_due_at->format('d M Y, H:i') }}.</p>
            @endif
        </div>
    </div>

    <div class="p-5">
        <p class="text-[18px] font-black leading-tight">{{ $event->name }}</p>
        <p class="text-[12px] text-gray-500">{{ $event->organization->name }} · {{ $event->event_date?->format('D j M Y') }}</p>

        <div class="mt-4 grid grid-cols-2 gap-4 text-[13px]">
            <div>
                <p class="text-[11px] font-black text-gray-400">Name</p>
                <p class="font-black">{{ $ticket->holder_name }}</p>
            </div>
            <div>
                <p class="text-[11px] font-black text-gray-400">Ticket</p>
                <p class="font-black">{{ $ticket->tier->tier_name }}@if(($ticket->admissions ?? 1) > 1) · admits {{ $ticket->admissions }}@endif</p>
            </div>
            <div>
                <p class="text-[11px] font-black text-gray-400">{{ (float) $ticket->amount_paid > 0 ? 'Balance left' : 'Amount' }}</p>
                <p class="font-black">M{{ number_format($ticket->status === 'pending' ? $owed : (float) $ticket->amount, 2) }}</p>
            </div>
            <div>
                <p class="text-[11px] font-black text-gray-400">Ticket number</p>
                <p class="font-mono font-black text-[12px]">{{ $ticket->ticket_number }}</p>
            </div>
        </div>
    </div>

    @if($ticket->status === 'pending' && !$submitted)
        <div class="px-5 pb-5">
            <a href="{{ $paymentUrl }}" class="block w-full text-center py-4 rounded-2xl bg-[#F07F22] hover:bg-[#1D4069] text-white text-[12px] font-black uppercase tracking-widest">
                Pay or submit your payment
            </a>
        </div>
    @elseif($ticket->status === 'expired' && !$event->registrationClosedReason())
        <div class="px-5 pb-5">
            <a href="{{ route('registration.form', [$event->organization->slug, $event->slug]) }}" class="block w-full text-center py-4 rounded-2xl bg-[#F07F22] hover:bg-[#1D4069] text-white text-[12px] font-black uppercase tracking-widest">
                Register again
            </a>
        </div>
    @elseif($ticket->status === 'pending')
        <div class="px-5 pb-5 text-center">
            <a href="{{ $paymentUrl }}" class="text-[12px] font-bold text-gray-400 hover:text-[#1D4069]">Something wrong? Send proof or pay another way</a>
        </div>
    @endif
</section>

<p class="mt-4 text-center text-[12px] text-gray-400"><i class="fas fa-lock mr-1"></i>This ticket can't be scanned until it's active.</p>
@endsection
