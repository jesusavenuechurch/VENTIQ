@extends('layouts.app')
@section('title', 'Events | VENTIQ')
@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    @include('organizer.partials.header', [
        'title' => 'Your events',
    ])

    @if($toConfirm > 0)
        <a href="{{ route('organizer.payments.index') }}"
           class="mb-6 flex items-center justify-between gap-4 p-5 rounded-[1.5rem] bg-action text-white shadow-lg hover:bg-action-ink transition-all">
            <span class="text-[13px] font-black">
                <i class="fas fa-receipt mr-2"></i>{{ $toConfirm }} {{ Str::plural('payment', $toConfirm) }} waiting for you to confirm
            </span>
            <span class="text-[10px] font-black uppercase tracking-widest">Review <i class="fas fa-arrow-right ml-1"></i></span>
        </a>
    @endif

    <div class="flex justify-end mb-4">
        @can('create_event')
        <a href="{{ route('organizer.events.create') }}"
           class="px-5 py-3 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[10px] font-black uppercase tracking-widest">
            <i class="fas fa-plus mr-1"></i>Create event
        </a>
        @endcan
    </div>

    <div class="space-y-3">
        @forelse($events as $event)
            <div class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6 flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                        {{ $event->event_date?->format('d M Y') }} · {{ ucfirst($event->status) }}
                    </p>
                    <p class="text-lg font-black text-[#1D4069] leading-tight mt-1 truncate">{{ $event->name }}</p>
                    <div class="flex flex-wrap gap-2 mt-3 text-[11px] font-bold">
                        <span class="px-2.5 py-1 rounded-full bg-mint text-mint-ink">{{ $event->active_count }} active</span>
                        <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-500">{{ $event->awaiting_count }} awaiting payment</span>
                        @if($event->to_confirm_count)
                            <a href="{{ route('organizer.payments.index', ['event' => $event->id]) }}" class="px-2.5 py-1 rounded-full bg-action-soft text-action-ink hover:underline">{{ $event->to_confirm_count }} to confirm</a>
                        @endif
                        @if($event->collected_total > 0)
                            <span class="px-2.5 py-1 rounded-full bg-mint text-mint-ink">M{{ number_format($event->collected_total, 2) }} collected</span>
                        @endif
                        @if($event->fees_sponsored)
                            <span class="px-2.5 py-1 rounded-full bg-lilac text-lilac-ink"><i class="fas fa-gift mr-1"></i>Sponsored by VENTIQ</span>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('organizer.events.attendees', $event) }}" class="px-4 py-2 rounded-full bg-brand text-white text-[10px] font-black uppercase tracking-widest hover:bg-action">Attendees &amp; money</a>
                    @can('edit_event')
                    <a href="{{ route('organizer.events.edit', $event) }}" class="px-4 py-2 rounded-full bg-slate-50 border border-slate-100 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:bg-white">Edit event</a>
                    @endcan
                    @if($event->is_public && $event->slug)
                        <a href="{{ route('event.short', [$currentOrganization->slug, $event->slug]) }}" target="_blank" class="px-4 py-2 rounded-full bg-slate-50 border border-slate-100 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:bg-white">Public page</a>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white rounded-[1.5rem] border border-dashed border-gray-200 p-10 text-center">
                <p class="text-[13px] font-bold text-gray-600">No events yet. Create your first one to start selling tickets.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
