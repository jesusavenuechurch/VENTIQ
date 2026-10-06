@extends('layouts.app')
@section('title', 'Events | VENTIQ')
@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    @php
        $firstName = \Illuminate\Support\Str::before(auth()->user()->name ?? '', ' ') ?: 'there';
        $mood = match (true) {
            $toConfirm > 0      => 'A few payments need you, then you\'re all set.',
            $events->isEmpty()  => 'Let\'s get your first event out there.',
            default             => 'Here\'s how your events are doing.',
        };
    @endphp
    @include('organizer.partials.header', [
        'title'    => "Lumela, {$firstName}!",
        'subtitle' => $mood,
    ])

    @if($toConfirm > 0)
        <a href="{{ route('organizer.payments.index') }}" wire:navigate
           class="mb-6 flex items-center justify-between gap-4 p-5 rounded-[1.5rem] bg-action text-white shadow-lg hover:bg-action-ink transition-all">
            <span class="text-[13px] font-black">
                <i class="fas fa-receipt mr-2"></i>{{ $toConfirm }} {{ Str::plural('person', $toConfirm) }} paid you and {{ $toConfirm === 1 ? 'is' : 'are' }} waiting for their ticket
            </span>
            <span class="text-[10px] font-black uppercase tracking-widest">Review <i class="fas fa-arrow-right ml-1"></i></span>
        </a>
    @endif

    <div class="flex justify-end mb-4">
        @can('create_event')
        <a href="{{ route('organizer.events.create') }}" wire:navigate
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
                            <a href="{{ route('organizer.payments.index', ['event' => $event->id]) }}" wire:navigate class="px-2.5 py-1 rounded-full bg-action-soft text-action-ink hover:underline">{{ $event->to_confirm_count }} to confirm</a>
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
                    @if($event->event_date && $event->event_date->isToday())
                        <a href="{{ route('organizer.events.day', $event) }}" wire:navigate class="px-4 py-2 rounded-full bg-action text-white text-[10px] font-black uppercase tracking-widest hover:bg-action-ink"><i class="fas fa-door-open mr-1"></i>Event day</a>
                    @endif
                    <a href="{{ route('organizer.events.attendees', $event) }}" wire:navigate class="px-4 py-2 rounded-full bg-brand text-white text-[10px] font-black uppercase tracking-widest hover:bg-action">Attendees &amp; money</a>
                    @can('edit_event')
                    <a href="{{ route('organizer.events.edit', $event) }}" wire:navigate class="px-4 py-2 rounded-full bg-slate-50 border border-slate-100 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:bg-white">Edit event</a>
                    @endcan
                    @include('organizer.partials.share-button', ['event' => $event])
                </div>
            </div>
        @empty
            <div class="bg-white rounded-[1.5rem] border border-dashed border-gray-200 p-10 text-center">
                @include('organizer.partials.crowd', ['seeds' => ['host', 'guest-a', 'guest-b']])
                <p class="text-[15px] font-black text-[#1D4069]">Your first event starts here</p>
                <p class="text-[13px] font-medium text-gray-500 mt-1">It takes a few minutes. We'll help you share it and sell tickets.</p>
                @can('create_event')
                    <a href="{{ route('organizer.events.create') }}" wire:navigate class="mt-5 inline-block px-5 py-3 rounded-2xl bg-action hover:bg-action-ink text-white text-[10px] font-black uppercase tracking-widest"><i class="fas fa-plus mr-1"></i>Create my first event</a>
                @endcan
            </div>
        @endforelse
    </div>
</div>
    @include('organizer.partials.share-event')
@endsection
