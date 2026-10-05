@extends('layouts.app')
@section('title', $event->name . ' · Attendees | VENTIQ')
@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    @include('organizer.partials.header', [
        'title'    => $event->name,
        'subtitle' => 'Who registered, where each ticket stands, and the money so far.',
    ])

    <div class="flex flex-wrap gap-2 -mt-4 mb-6">
        @include('organizer.partials.share-button', ['event' => $event, 'class' => 'px-4 py-2 rounded-full bg-brand text-white text-[10px] font-black uppercase tracking-widest hover:bg-action'])
        @if($event->share_url)
            <a href="{{ $event->share_url }}" target="_blank" class="px-4 py-2 rounded-full bg-white border border-gray-100 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-[#1D4069]"><i class="fas fa-arrow-up-right-from-square mr-1"></i>Public page</a>
        @endif
        <a href="{{ route('organizer.events.day', $event) }}" class="px-4 py-2 rounded-full {{ $event->event_date?->isToday() ? 'bg-action text-white hover:bg-action-ink' : 'bg-white border border-gray-100 text-gray-500 hover:text-[#1D4069]' }} text-[10px] font-black uppercase tracking-widest"><i class="fas fa-door-open mr-1"></i>Event day</a>
        @can('approve_payment')
            <a href="{{ route('organizer.events.comp.create', $event) }}" class="px-4 py-2 rounded-full bg-white border border-gray-100 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-[#1D4069]"><i class="fas fa-gift mr-1"></i>Complimentary ticket</a>
        @endcan
        @can('edit_event')
            <a href="{{ route('organizer.events.edit', $event) }}" class="px-4 py-2 rounded-full bg-white border border-gray-100 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-[#1D4069]"><i class="fas fa-pen mr-1"></i>Edit event</a>
        @endcan
    </div>

    @include('organizer.partials.event-money', ['finance' => $finance, 'event' => $event])

    @can('view_reports')
        <div class="flex flex-wrap gap-2 mb-6">
            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest self-center mr-1">Download</span>
            <a href="{{ route('reports.revenue', $event) }}" class="px-3 py-1.5 rounded-full bg-white border border-gray-100 text-[10px] font-black uppercase tracking-widest text-[#1D4069]"><i class="fas fa-file-pdf mr-1"></i>Revenue report</a>
            <a href="{{ route('reports.registration-summary', $event) }}" class="px-3 py-1.5 rounded-full bg-white border border-gray-100 text-[10px] font-black uppercase tracking-widest text-[#1D4069]"><i class="fas fa-file-pdf mr-1"></i>Registration summary</a>
            <a href="{{ route('reports.attendance', $event) }}" class="px-3 py-1.5 rounded-full bg-white border border-gray-100 text-[10px] font-black uppercase tracking-widest text-[#1D4069]"><i class="fas fa-file-pdf mr-1"></i>Attendance register</a>
            <a href="{{ route('reports.attendance-excel', $event) }}" class="px-3 py-1.5 rounded-full bg-white border border-gray-100 text-[10px] font-black uppercase tracking-widest text-[#1D4069]"><i class="fas fa-file-excel mr-1 text-mint-ink"></i>Attendance (Excel)</a>
        </div>
    @endcan

    <form method="GET" action="{{ route('organizer.events.attendees', $event) }}" class="mb-4 flex gap-2" role="search">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <div class="relative flex-1">
            <i class="fas fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 text-[12px]"></i>
            <input type="search" name="q" value="{{ $search }}" placeholder="Name, phone, or entry code (VQ-…)" aria-label="Search attendees"
                   class="w-full pl-10 pr-4 py-3 rounded-full bg-white border border-gray-100 text-[13px] font-semibold text-[#1D4069] focus:border-[#F07F22] outline-none">
        </div>
        <button class="px-5 rounded-full bg-brand text-white text-[10px] font-black uppercase tracking-widest hover:bg-action">Search</button>
        @if($search !== '')
            <a href="{{ route('organizer.events.attendees', [$event, 'filter' => $filter]) }}" class="px-4 py-3 rounded-full bg-white border border-gray-100 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-[#1D4069]">Clear</a>
        @endif
    </form>

    <div class="flex flex-wrap gap-2 mb-6">
        @foreach($filters as $key => $label)
            <a href="{{ route('organizer.events.attendees', array_filter([$event, 'filter' => $key, 'q' => $search ?: null])) }}"
               class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest
                      {{ $filter === $key ? 'bg-brand text-white' : 'bg-white border border-gray-100 text-gray-500 hover:text-[#1D4069]' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @php
        $canDecide = auth()->user()->can('approve_payment');
        $state = function ($ticket) {
            $submitted = $ticket->payments->contains(fn ($p) => $p->status === 'pending' && $p->submitted_at);
            return match (true) {
                $ticket->status === 'pending' && $submitted => ['Payment to confirm', 'bg-action-soft text-action-ink'],
                $ticket->status === 'pending'               => ['Awaiting payment', 'bg-slate-100 text-slate-500'],
                $ticket->status === 'active' && $ticket->payment_status === 'partial' => ['Active · balance due', 'bg-mint text-mint-ink'],
                $ticket->status === 'active'                => ['Active', 'bg-mint text-mint-ink'],
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
                    <p class="text-[14px] font-black text-[#1D4069]">
                        {{ $ticket->client->full_name }}
                        @if($ticket->is_complimentary)
                            <span class="ml-1 px-2 py-0.5 rounded-full bg-lilac text-lilac-ink text-[9px] font-black uppercase tracking-widest align-middle" title="{{ $ticket->complimentary_reason }}"><i class="fas fa-gift mr-0.5"></i>Comp</span>
                        @endif
                    </p>
                    <p class="text-[11px] font-semibold text-gray-400">
                        {{ $ticket->client->phone }} · <span class="font-mono">{{ $ticket->voucher_code }}</span>
                        @if(in_array($ticket->status, ['active', 'checked_in']))
                            @if($ticket->delivery_status === 'failed')
                                · <span class="text-rose-600"><i class="fab fa-whatsapp"></i> not delivered</span>
                            @elseif($ticket->whatsapp_delivered_at)
                                · <span class="text-mint-ink"><i class="fab fa-whatsapp"></i> sent {{ $ticket->whatsapp_delivered_at->diffForHumans() }}</span>
                            @endif
                        @endif
                    </p>
                    <p class="text-[11px] font-medium text-gray-500">
                        {{ $ticket->tier->tier_name }}
                        @if(($ticket->admissions ?? 1) > 1) · Group of {{ $ticket->admissions }} ({{ $ticket->admitted_count }} admitted) @endif
                        · M{{ number_format((float) $ticket->amount, 2) }}
                        @if($ticket->status === 'pending' && $ticket->payment_due_at) · pay by {{ $ticket->payment_due_at->format('d M, H:i') }} @endif
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest {{ $badge }}">{{ $label }}</span>
                    @if(in_array($ticket->status, ['active', 'checked_in']))
                        <a href="{{ route('ticket.download', $ticket->qr_code) }}" target="_blank" class="px-3 py-1 rounded-full bg-white border border-gray-200 text-gray-500 hover:text-[#1D4069] text-[10px] font-black uppercase tracking-widest" title="Open the attendee's ticket"><i class="fas fa-ticket mr-1"></i>View</a>
                        <details class="relative">
                            <summary class="list-none cursor-pointer px-3 py-1 rounded-full bg-[#25D366] text-white text-[10px] font-black uppercase tracking-widest"><i class="fab fa-whatsapp mr-1"></i>Send</summary>
                            <form method="POST" action="{{ route('organizer.tickets.resend', $ticket) }}"
                                  class="absolute right-0 z-10 mt-2 w-72 p-4 rounded-2xl bg-white border border-gray-100 shadow-xl space-y-3">
                                @csrf
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Send to</label>
                                <input name="phone" value="{{ $ticket->client->phone }}" inputmode="tel"
                                       class="w-full bg-slate-50 rounded-xl px-3 py-2 text-[13px] font-semibold text-[#1D4069] focus:bg-white focus:outline-[#F07F22]">
                                <p class="text-[11px] text-gray-400">Fix the number here if it was wrong; it's saved for this attendee.</p>
                                <button class="w-full py-2 rounded-xl bg-[#25D366] text-white text-[10px] font-black uppercase tracking-widest">Send ticket on WhatsApp</button>
                            </form>
                        </details>
                    @endif
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
            <p class="p-10 text-center text-[13px] font-bold text-gray-500">
                {{ $search !== '' ? "Nobody matches \"{$search}\"" . ($filter !== 'all' ? ' in this group.' : '.') : 'No attendees in this group.' }}
            </p>
        @endforelse
    </div>

    <div class="mt-6">{{ $tickets->links() }}</div>
</div>
    @include('organizer.partials.share-event')
@endsection
