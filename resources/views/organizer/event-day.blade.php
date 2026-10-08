@extends('layouts.app')
@section('title', $event->name . ' · Event day | VENTIQ')
@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    @include('organizer.partials.header', [
        'title'    => $event->name,
        'subtitle' => 'Event day: who\'s in, who\'s still coming, and tickets that didn\'t reach people.',
        'crumbs'   => [['Events', route('organizer.home')], [$event->name, route('organizer.events.attendees', $event)], ['Event day']],
    ])

    <div class="flex flex-wrap items-center gap-2 -mt-4 mb-6">
        <a href="{{ route('organizer.events.attendees', $event) }}" wire:navigate class="px-4 py-2 rounded-full bg-white border border-gray-100 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-[#1D4069]"><i class="fas fa-users mr-1"></i>Attendees &amp; money</a>
        @can('view_reports')
            <a href="{{ route('reports.attendance-excel', $event) }}" class="px-4 py-2 rounded-full bg-white border border-gray-100 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-[#1D4069]"><i class="fas fa-file-excel mr-1 text-mint-ink"></i>Attendance (Excel)</a>
        @endcan
        <span class="ml-auto text-[10px] font-bold text-gray-400 uppercase tracking-widest" aria-live="polite">
            <i class="fas fa-circle text-[6px] text-mint-ink align-middle mr-1"></i>Live · updated <span id="day-updated">{{ now()->format('H:i:s') }}</span>
        </span>
    </div>

    <div id="day-live"
         x-data
         x-init="const timer = setInterval(async () => {
            {{-- After a tab switch this page is gone: stop refreshing. --}}
            if (!$el.isConnected) return clearInterval(timer);
            if (document.hidden) return;
            try {
                const r = await fetch('{{ route('organizer.events.day', [$event, 'partial' => 1]) }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) return;
                $el.innerHTML = await r.text();
                document.getElementById('day-updated').textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
            } catch (e) {}
         }, 30000)">
        @include('organizer.partials.event-day-live')
    </div>

    {{-- Not refreshed: it holds the Send forms. --}}
    <div class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest"><i class="fab fa-whatsapp mr-1"></i>WhatsApp tickets</p>
            <p class="text-[12px] font-semibold text-gray-500">
                {{ $whatsapp['sent'] }} delivered
                @if($whatsapp['unsent']) · {{ $whatsapp['unsent'] }} not sent yet @endif
                @if($whatsapp['failed']->count()) · <span class="text-rose-600 font-bold"><i class="fas fa-triangle-exclamation"></i> {{ $whatsapp['failed']->count() }} failed</span> @endif
            </p>
        </div>
        @forelse($whatsapp['failed'] as $ticket)
            <div class="py-2.5 border-t border-gray-50 flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[13px] font-black text-[#1D4069]">{{ $ticket->holder_name }}</p>
                    <p class="text-[11px] font-semibold text-rose-600"><i class="fas fa-triangle-exclamation mr-1"></i>Not delivered to {{ $ticket->client->phone }}</p>
                </div>
                <form method="POST" action="{{ route('organizer.tickets.resend', $ticket) }}" class="flex items-center gap-2">
                    @csrf
                    <input name="phone" value="{{ $ticket->client->phone }}" inputmode="tel" aria-label="WhatsApp number for {{ $ticket->holder_name }}"
                           class="w-40 bg-slate-50 rounded-full px-3 py-1.5 text-[12px] font-semibold text-[#1D4069] focus:bg-white focus:outline-[#F07F22]">
                    <button class="px-3 py-1.5 rounded-full bg-[#25D366] text-white text-[10px] font-black uppercase tracking-widest"><i class="fab fa-whatsapp mr-1"></i>Send again</button>
                    <a href="{{ route('ticket.download', $ticket->qr_code) }}" target="_blank" class="px-3 py-1.5 rounded-full bg-white border border-gray-200 text-gray-500 text-[10px] font-black uppercase tracking-widest">View</a>
                </form>
            </div>
        @empty
            <p class="text-[12px] text-gray-400 border-t border-gray-50 pt-3">No failed deliveries. If someone says they didn't get their ticket, find them in Attendees and send it again.</p>
        @endforelse
    </div>
</div>
@endsection
