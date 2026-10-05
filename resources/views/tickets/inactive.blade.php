<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventiq Pass - {{ $ticket->event->name }}</title>
    @vite('resources/css/app.css')
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
{{-- The ticket exists but won't scan: shown instead of the pass until it's
     activated, or once it has expired or been cancelled. No QR here, so a
     screenshot can't be mistaken for a valid ticket. --}}
@php
    [$headline, $body, $tone] = match ($ticket->status) {
        'expired'   => ['Payment window ended', 'This ticket was not paid in time, so its place has been released. Contact the organizer if you still want to attend.', 'rose'],
        'cancelled', 'void', 'refunded' => ['Ticket cancelled', 'This ticket is no longer valid. Contact the organizer if you think this is a mistake.', 'rose'],
        default     => $submitted
            ? ['Payment being confirmed', 'Your payment is being confirmed. This ticket activates as soon as it is, and we will let you know. There is no need to pay again.', 'sky']
            : ['Inactive — awaiting payment', 'Your place is reserved. Pay to activate this ticket; the same ticket then works at the entrance.', 'amber'],
    };
    $toneClasses = [
        'amber' => 'bg-amber-50 border-amber-200 text-amber-800',
        'sky'   => 'bg-sky-50 border-sky-200 text-sky-800',
        'rose'  => 'bg-rose-50 border-rose-200 text-rose-800',
    ][$tone];
@endphp
<body class="min-h-screen flex items-center justify-center p-6 bg-slate-900">
    <div class="w-full max-w-lg">
        <div class="flex justify-between items-center mb-6 px-2">
            <span class="text-white font-black tracking-tighter text-sm uppercase">Ventiq</span>
            <span class="text-white/60 font-bold text-[9px] uppercase tracking-widest">{{ $ticket->ticket_number }}</span>
        </div>

        <div class="bg-white rounded-[2rem] overflow-hidden">
            <div class="p-8">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ $ticket->event->organization->name }}</p>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight uppercase leading-tight mt-1">{{ $ticket->event->name }}</h1>

                <div class="grid grid-cols-2 gap-6 mt-6 text-sm">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Guest</p>
                        <p class="font-extrabold text-slate-900">{{ $ticket->client->full_name }}</p>
                        @if(($ticket->admissions ?? 1) > 1)
                            <p class="text-[11px] font-bold text-slate-500">Group of {{ $ticket->admissions }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Date</p>
                        <p class="font-extrabold text-slate-900">{{ $ticket->event->event_date?->format('d M Y') }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Ticket</p>
                        <p class="font-extrabold text-slate-900">{{ $ticket->tier->tier_name }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Amount</p>
                        <p class="font-extrabold text-slate-900">M{{ number_format((float) $ticket->amount, 2) }}</p>
                    </div>
                </div>
            </div>

            <div class="mx-8 mb-8 p-5 rounded-2xl border {{ $toneClasses }}">
                <p class="text-[12px] font-black uppercase tracking-widest"><i class="fas fa-lock mr-1"></i>{{ $headline }}</p>
                <p class="text-[13px] font-medium mt-2 leading-relaxed">{{ $body }}</p>
                @if($ticket->status === 'pending' && $ticket->payment_due_at && !$submitted)
                    <p class="text-[12px] font-bold mt-2">Pay by {{ $ticket->payment_due_at->format('d M Y, H:i') }}.</p>
                @endif
            </div>

            @if($ticket->status === 'pending' && !$submitted)
                <div class="px-8 pb-8">
                    <a href="{{ $paymentUrl }}" class="block w-full text-center py-4 rounded-2xl bg-slate-900 hover:bg-[#1D4069] text-white text-[11px] font-black uppercase tracking-[0.2em]">
                        Pay or submit your payment
                    </a>
                </div>
            @elseif($ticket->status === 'pending')
                <div class="px-8 pb-8 text-center">
                    <a href="{{ $paymentUrl }}" class="text-[11px] font-bold text-gray-400 hover:text-[#1D4069]">Something wrong? Send proof or pay another way</a>
                </div>
            @endif
        </div>

        <p class="text-center mt-6 text-[10px] font-bold text-white/40 uppercase tracking-widest">This ticket can't be scanned until it's active</p>
    </div>
</body>
</html>
