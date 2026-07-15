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
            <div x-data="{ editing: false }" class="bg-white rounded-2xl border border-gray-100">
                <div x-show="!editing" class="flex items-center justify-between px-5 py-3">
                    <div>
                        <p class="text-sm font-black text-[#1D4069]">{{ $p->client->full_name }}</p>
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-wide">{{ $p->attended_at->format('g:i A') }}</p>
                        <p class="text-[10px] font-bold mt-1 {{ $p->institution ? 'text-gray-500' : 'text-rose-400' }}">
                            {{ $p->institution ?: 'Institution missing' }}
                        </p>
                        <p class="text-[10px] font-bold {{ $p->position ? 'text-gray-500' : 'text-rose-400' }}">
                            {{ $p->position ?: 'Position missing' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-600">Checked In</span>
                        <button @click="editing = true" type="button" class="text-[9px] font-black uppercase tracking-widest text-[#1D4069] hover:text-[#F07F22]">Edit</button>
                    </div>
                </div>

                <form x-show="editing" method="POST" action="{{ route('sessions.checkin.update', [$session, $p]) }}" class="px-5 py-4 space-y-2">
                    @csrf
                    @method('PATCH')
                    <input type="text" name="full_name" value="{{ $p->client->full_name }}" placeholder="Full name"
                        class="w-full bg-gray-50 border-none rounded-xl px-4 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                    <input type="tel" name="phone" value="{{ $p->client->phone }}" placeholder="Phone"
                        class="w-full bg-gray-50 border-none rounded-xl px-4 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                    <input type="text" name="institution" value="{{ $p->institution }}" placeholder="Organization / Institution"
                        class="w-full bg-gray-50 border-none rounded-xl px-4 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                    <input type="text" name="position" value="{{ $p->position }}" placeholder="Position / Role"
                        class="w-full bg-gray-50 border-none rounded-xl px-4 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="flex-1 py-2.5 rounded-xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[9px] font-black uppercase tracking-widest">Save</button>
                        <button type="button" @click="editing = false" class="px-4 py-2.5 rounded-xl bg-gray-50 text-gray-500 text-[9px] font-black uppercase tracking-widest">Cancel</button>
                    </div>
                </form>
            </div>
        @empty
            <p class="text-sm text-gray-400 italic text-center py-10">No one checked in yet.</p>
        @endforelse
    </div>
</div>
@endsection