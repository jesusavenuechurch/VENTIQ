@extends('layouts.app')
@section('title', $event->name . ' · Attendees | VENTIQ')
@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    @include('organizer.partials.header', [
        'title'    => $event->name,
        'subtitle' => 'Everyone who registered, and where their ticket stands.',
    ])

    <div class="flex flex-wrap gap-2 mb-6">
        @foreach($filters as $key => $label)
            <a href="{{ route('organizer.events.attendees', [$event, 'filter' => $key]) }}"
               class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest
                      {{ $filter === $key ? 'bg-[#F07F22] text-white' : 'bg-white border border-gray-100 text-gray-500 hover:text-[#1D4069]' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @php
        $canDecide = auth()->user()->can('approve_payment');
        $state = function ($ticket) {
            $submitted = $ticket->payments->contains(fn ($p) => $p->status === 'pending' && $p->submitted_at);
            return match (true) {
                $ticket->status === 'pending' && $submitted => ['Payment to confirm', 'bg-orange-50 text-[#F07F22]'],
                $ticket->status === 'pending'               => ['Awaiting payment', 'bg-amber-50 text-amber-700'],
                $ticket->status === 'active' && $ticket->payment_status === 'partial' => ['Active · balance due', 'bg-emerald-50 text-emerald-700'],
                $ticket->status === 'active'                => ['Active', 'bg-emerald-50 text-emerald-700'],
                $ticket->status === 'checked_in'            => ['Used', 'bg-slate-100 text-slate-600'],
                $ticket->status === 'expired'               => ['Expired', 'bg-rose-50 text-rose-600'],
                default                                     => [ucfirst($ticket->status), 'bg-slate-100 text-slate-500'],
            };
        };
    @endphp

    <div class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm divide-y divide-gray-50">
        @forelse($tickets as $ticket)
            @php([$label, $badge] = $state($ticket))
            <div class="p-5 flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[14px] font-black text-[#1D4069]">{{ $ticket->client->full_name }}</p>
                    <p class="text-[11px] font-medium text-gray-500">
                        {{ $ticket->tier->tier_name }}
                        @if(($ticket->admissions ?? 1) > 1) · Group of {{ $ticket->admissions }} ({{ $ticket->admitted_count }} admitted) @endif
                        · M{{ number_format((float) $ticket->amount, 2) }}
                        @if($ticket->status === 'pending' && $ticket->payment_due_at) · pay by {{ $ticket->payment_due_at->format('d M, H:i') }} @endif
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest {{ $badge }}">{{ $label }}</span>
                    @if($canDecide && $ticket->status === 'expired')
                        <form method="POST" action="{{ route('organizer.tickets.reinstate', $ticket) }}">@csrf
                            <button class="px-3 py-1 rounded-full bg-[#1D4069] text-white text-[10px] font-black uppercase tracking-widest">Reinstate</button>
                        </form>
                    @endif
                    @if($canDecide && in_array($ticket->status, ['pending', 'expired']))
                        <form method="POST" action="{{ route('organizer.tickets.cancel', $ticket) }}" onsubmit="return confirm('Cancel this ticket? The attendee will no longer be able to pay for it.')">@csrf
                            <button class="px-3 py-1 rounded-full bg-white border border-gray-200 text-gray-500 hover:text-rose-600 text-[10px] font-black uppercase tracking-widest">Cancel</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="p-10 text-center text-[13px] font-bold text-gray-500">No attendees in this group.</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $tickets->links() }}</div>
</div>
@endsection
