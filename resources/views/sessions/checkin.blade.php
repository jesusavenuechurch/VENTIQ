@extends('layouts.app')
@section('title', 'Check-in — ' . $session->resolved_title . ' | VENTIQ')
@section('content')
<div class="max-w-2xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-black text-[#1D4069] uppercase tracking-tight mb-1">Check-in</h1>
    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-8">
        {{ $session->resolved_title }} · {{ $participants->count() }} checked in
    </p>

    <form method="POST" action="{{ route('sessions.checkin.store', $session) }}" class="flex flex-col sm:flex-row gap-2 mb-8">
        @csrf
        <input type="text" name="full_name" required placeholder="Full name" autofocus
               class="flex-1 bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
        <input type="tel" name="phone" placeholder="Phone (optional)"
               class="sm:w-40 bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
        <button type="submit" class="px-6 py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white font-black text-[10px] uppercase tracking-widest transition-all">
            Check In
        </button>
    </form>

    <div class="space-y-2">
        @forelse($participants as $p)
            <div class="flex items-center justify-between px-5 py-3 bg-white rounded-2xl border border-gray-100">
                <div>
                    <p class="text-sm font-black text-[#1D4069]">{{ $p->client->full_name }}</p>
                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-wide">{{ $p->attended_at->format('g:i A') }}</p>
                </div>
                <span class="text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-600">Checked In</span>
            </div>
        @empty
            <p class="text-sm text-gray-400 italic text-center py-10">No one checked in yet.</p>
        @endforelse
    </div>
</div>
@endsection