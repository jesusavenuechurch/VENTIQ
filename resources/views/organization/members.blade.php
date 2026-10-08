@extends('layouts.app')
@section('title', 'Team | VENTIQ')
@section('content')

<div class="max-w-4xl mx-auto px-4 py-8" x-data="{ showInvite: {{ $errors->has('email') ? 'true' : 'false' }} }">

    <div class="mb-8 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('sessions.index') }}" class="text-[10px] font-bold text-gray-400 hover:text-[#1D4069] uppercase tracking-widest mb-2 inline-block">← Back to Desk</a>
            <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.3em] mb-1">Ventiq</p>
            <p class="text-[15px] font-bold text-[#1D4069]">{{ $organization->name }} — Team</p>
            <p class="text-[11px] text-gray-400 font-medium mt-1">Everyone here shares the same desk: same sessions, same notes, same roster.</p>
        </div>

        <button @click="showInvite = true"
            class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-[#F07F22] text-white text-[12px] font-black uppercase tracking-wide shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all shrink-0">
            + Invite Member
        </button>
    </div>

    @if(session('status'))
        <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-100 text-[11px] font-bold text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @error('email')
        <div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-100 text-[11px] font-bold text-red-600">
            {{ $message }}
        </div>
    @enderror

    <div class="cork-board" style="background:#F7F3EC;border-radius:24px;padding:24px;">
        <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-4">Members</p>
        <div class="space-y-3">
            @foreach($members as $member)
                <div class="flex items-center justify-between px-4 py-3 rounded-xl bg-white/70">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-[#1D4069] text-white flex items-center justify-center text-[11px] font-black">
                            {{ strtoupper(substr($member->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-[12px] font-bold text-[#1D4069]">{{ $member->name }}</p>
                            <p class="text-[10px] text-gray-400">{{ $member->email }}</p>
                        </div>
                    </div>
                    @if($member->id === auth()->id())
                        <span class="text-[9px] font-black text-gray-300 uppercase tracking-widest">You</span>
                    @endif
                </div>
            @endforeach
        </div>

        @if($pendingInvites->isNotEmpty())
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-4 mt-8">Invited — not joined yet</p>
            <div class="space-y-3">
                @foreach($pendingInvites as $invite)
                    <div class="flex items-center justify-between px-4 py-3 rounded-xl bg-amber-50 border border-amber-100">
                        <div>
                            <p class="text-[12px] font-bold text-amber-700">{{ $invite->email }}</p>
                            <p class="text-[10px] text-amber-500">Invited {{ $invite->created_at->diffForHumans() }} · expires {{ $invite->expires_at->diffForHumans() }}</p>
                        </div>
                        <form method="POST" action="{{ route('organization.invite.revoke', $invite) }}">
                            @csrf
                            @method('DELETE')
                            <button class="text-[9px] font-black text-amber-600 uppercase tracking-widest underline">Revoke</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Invite modal --}}
    <div x-show="showInvite" x-cloak
         class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50"
         @click.self="showInvite = false">
        <div class="w-full max-w-md bg-white rounded-[2rem] shadow-2xl p-8">
            <h3 class="text-lg font-black text-[#1D4069] uppercase mb-2">Invite a Member</h3>
            <p class="text-[11px] text-gray-400 font-medium mb-6">They'll get a link to join {{ $organization->name }} directly — no separate signup, no new organization.</p>

            <form method="POST" action="{{ route('organization.invite.store') }}">
                @csrf
                <input type="email" name="email" required placeholder="colleague@example.com"
                    class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20 mb-4">
                @error('email')
                    <p class="text-[10px] font-bold text-red-500 mb-4">{{ $message }}</p>
                @enderror
                <div class="flex gap-3">
                    <button type="button" @click="showInvite = false"
                        class="w-1/3 py-4 rounded-2xl bg-gray-100 text-gray-400 font-black text-[10px] uppercase">Cancel</button>
                    <button type="submit"
                        class="w-2/3 py-4 rounded-2xl bg-[#1D4069] text-white font-black text-[10px] uppercase tracking-[0.3em] shadow-lg">Send Invite</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection