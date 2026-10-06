@extends('layouts.attendee')

@section('title', "Registration closed | {$event->name}")

@section('content')
<section class="rounded-[1.5rem] bg-white border border-gray-100 shadow-sm p-6 sm:p-8 text-center">
    <span class="mx-auto mb-4 w-16 h-16 rounded-2xl bg-action-soft text-action-ink flex items-center justify-center"><i class="fas fa-door-closed text-2xl"></i></span>
    <h1 class="text-2xl font-black">Registration is closed</h1>
    <p class="mt-2 text-[14px] text-gray-500">{{ $closedReason ?? 'Sorry, registration for this event is no longer available.' }}</p>

    <div class="mt-5 p-4 rounded-2xl bg-slate-50 text-left">
        <p class="text-[15px] font-black">{{ $event->name }}</p>
        @if($event->event_date)<p class="text-[12px] text-gray-500">{{ $event->event_date->format('l, j F Y \a\t g:i A') }}</p>@endif
    </div>

    <div class="mt-5 flex flex-col sm:flex-row gap-3 justify-center">
        <a href="{{ route('ticket.find') }}" class="px-5 py-3 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[11px] font-black uppercase tracking-widest">Already registered? Find my ticket</a>
        @if($organization->contact_email)
            <a href="mailto:{{ $organization->contact_email }}" class="px-5 py-3 rounded-2xl bg-white border border-gray-200 text-[11px] font-black uppercase tracking-widest">Contact {{ $organization->name }}</a>
        @endif
    </div>
</section>
@endsection
