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
            {{-- The whole card opens Attendees & money; its buttons sit above that link. --}}
            <div class="relative bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6 flex flex-wrap items-center justify-between gap-4 hover:border-gray-200 hover:shadow-md transition-all">
                <div class="min-w-0">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                        {{ $event->event_date?->format('d M Y') }} · {{ ucfirst($event->status) }}
                    </p>
                    <a href="{{ route('organizer.events.attendees', $event) }}" wire:navigate
                       class="block text-lg font-black text-[#1D4069] leading-tight mt-1 truncate after:absolute after:inset-0 after:rounded-[1.5rem] focus:outline-none">{{ $event->name }}</a>
                    <div class="flex flex-wrap gap-2 mt-3 text-[11px] font-bold">
                        <span class="px-2.5 py-1 rounded-full bg-mint text-mint-ink">{{ $event->active_count }} active</span>
                        <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-500">{{ $event->awaiting_count }} awaiting payment</span>
                        @if($event->to_confirm_count)
                            <a href="{{ route('organizer.payments.index', ['event' => $event->id]) }}" wire:navigate class="relative z-10 px-2.5 py-1 rounded-full bg-action-soft text-action-ink hover:underline">{{ $event->to_confirm_count }} to confirm</a>
                        @endif
                        @if($event->collected_total > 0)
                            <span class="px-2.5 py-1 rounded-full bg-mint text-mint-ink">M{{ number_format($event->collected_total, 2) }} collected</span>
                        @endif
                        @if($event->fees_sponsored)
                            <span class="px-2.5 py-1 rounded-full bg-lilac text-lilac-ink"><i class="fas fa-gift mr-1"></i>Sponsored by VENTIQ</span>
                        @endif
                    </div>
                </div>
                <div class="relative z-10 flex flex-wrap gap-2">
                    @if($event->status === 'draft')
                        @can('edit_event')
                            @include('organizer.partials.publish-button', ['event' => $event])
                        @endcan
                    @endif
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

    {{-- The door app, for whoever scans tickets at the event. --}}
    @php($scannerApp = \App\Models\AppRelease::current())
    @if($scannerApp && ($scannerApp->hasApk() || $scannerApp->play_store_url))
        <div class="mt-8 bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6 flex flex-wrap items-center gap-5">
            <img src="{{ asset('images/favicon_io/android-chrome-192x192.png') }}" alt="" class="w-14 h-14 rounded-2xl border border-gray-100 p-1.5 shrink-0">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">For the door</p>
                <p class="text-[15px] font-black text-[#1D4069] mt-0.5">VENTIQ Scanner</p>
                <p class="text-[12px] font-medium text-gray-500 mt-0.5">Scan tickets at the entrance, even without internet. Android phones; your team signs in with their VENTIQ account.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($scannerApp->play_store_url)
                    <a href="{{ $scannerApp->play_store_url }}" target="_blank" rel="noopener" class="px-4 py-2.5 rounded-full bg-[#1D4069] text-white text-[10px] font-black uppercase tracking-widest hover:bg-action"><i class="fab fa-google-play mr-1"></i>Get it on Google Play</a>
                @endif
                @if($scannerApp->hasApk())
                    <a href="{{ route('scanner-app.download') }}" class="px-4 py-2.5 rounded-full {{ $scannerApp->play_store_url ? 'bg-white border border-gray-200 text-gray-600 hover:text-[#1D4069]' : 'bg-action text-white hover:bg-action-ink' }} text-[10px] font-black uppercase tracking-widest"
                       title="For phones without Google Play"><i class="fas fa-download mr-1"></i>Download APK · v{{ $scannerApp->version }}@if($scannerApp->sizeLabel()) · {{ $scannerApp->sizeLabel() }}@endif</a>
                @endif
            </div>
        </div>
    @endif
</div>
    @include('organizer.partials.share-event')
@endsection
