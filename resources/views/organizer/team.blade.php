@extends('layouts.app')
@section('title', 'Team | VENTIQ')
@section('content')
@php
    $field = 'w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-4 py-3 text-[14px] font-semibold text-gray-900 focus:bg-white focus:border-[#F07F22] outline-none transition-all';
    $label = 'block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1';
    $roleBadge = ['org_admin' => 'bg-brand text-white', 'staff' => 'bg-mint text-mint-ink', 'scanner' => 'bg-action-soft text-action-ink', 'viewer' => 'bg-slate-100 text-slate-500'];
@endphp
<div class="max-w-5xl mx-auto px-4 py-8">
    @include('organizer.partials.header', [
        'title'    => 'Settings',
        'subtitle' => 'The people who make your events happen, and what each of them can do.',
        'subnav'   => 'organizer.partials.settings-nav',
    ])
    {{-- Narrower than the page frame, left-aligned under the tabs. --}}
    <div class="max-w-4xl">

    @if($errors->any())
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-[12px] font-bold text-rose-700">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="grid lg:grid-cols-5 gap-6">
        <div class="lg:col-span-3 space-y-6">
            <div class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm divide-y divide-gray-50">
                @foreach($members as $member)
                    @php($role = $member->roles->first()?->name)
                    <div class="p-5 flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0 flex items-center gap-3">
                            <x-avatar :seed="$member->email" size="w-11 h-11" />
                            <div class="min-w-0">
                            <p class="text-[14px] font-black text-[#1D4069]">
                                {{ $member->name }}
                                @if($member->is(auth()->user()))<span class="text-[11px] font-bold text-gray-400">(you)</span>@endif
                            </p>
                            <p class="text-[11px] font-medium text-gray-500 truncate">{{ $member->email }}</p>
                            </div>
                        </div>

                        @if($canManage && !$member->is(auth()->user()))
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('organizer.team.role', $member) }}">
                                    @csrf @method('PUT')
                                    <select name="role" onchange="this.form.submit()" aria-label="Role for {{ $member->name }}"
                                            class="bg-slate-50 rounded-full pl-3 pr-8 py-1.5 text-[11px] font-black text-[#1D4069] border-0 focus:outline-[#F07F22]">
                                        @foreach($roles as $key => [$name])
                                            <option value="{{ $key }}" @selected($role === $key)>{{ $name }}</option>
                                        @endforeach
                                        @unless($role)<option value="" selected disabled>No role</option>@endunless
                                    </select>
                                    <noscript><button class="text-[10px] font-black uppercase">Save</button></noscript>
                                </form>
                                <form method="POST" action="{{ route('organizer.team.remove', $member) }}" onsubmit="return confirm('Remove {{ addslashes($member->name) }} from the team? They lose access straight away.')">
                                    @csrf @method('DELETE')
                                    <button class="w-8 h-8 rounded-full bg-white border border-gray-200 text-gray-400 hover:text-rose-600" aria-label="Remove {{ $member->name }}"><i class="fas fa-user-minus text-[11px]"></i></button>
                                </form>
                            </div>
                        @else
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest {{ $roleBadge[$role] ?? 'bg-slate-100 text-slate-500' }}">
                                {{ $role ? \App\Support\TeamRoles::label($role) : 'No role' }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($invites->isNotEmpty())
                <div>
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Invited, not joined yet</p>
                    <div class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm divide-y divide-gray-50">
                        @foreach($invites as $invite)
                            <div class="p-4 flex flex-wrap items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[13px] font-bold text-[#1D4069] truncate">{{ $invite->email }}</p>
                                    <p class="text-[11px] font-medium text-gray-400">{{ \App\Support\TeamRoles::label($invite->role) }} · link works until {{ $invite->expires_at->format('d M') }}</p>
                                </div>
                                @if($canManage)
                                    <form method="POST" action="{{ route('organizer.team.invite.revoke', $invite) }}">
                                        @csrf @method('DELETE')
                                        <button class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-rose-600">Cancel invite</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="lg:col-span-2 space-y-6">
            @if($canManage)
                <form method="POST" action="{{ route('organizer.team.invite') }}" class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6 space-y-4"
                      x-data="{ role: @js(old('role', 'staff')) }">
                    @csrf
                    <p class="text-[15px] font-black text-[#1D4069]">Invite someone</p>
                    <div>
                        <label for="email" class="{{ $label }}">Their email</label>
                        <input id="email" name="email" type="email" required value="{{ old('email') }}" class="{{ $field }}">
                    </div>
                    <div>
                        <span class="{{ $label }}">What they can do</span>
                        <div class="space-y-2">
                            @foreach($roles as $key => [$name, $description])
                                <label class="flex gap-3 p-3 rounded-2xl border-2 cursor-pointer transition-all"
                                       :class="role === '{{ $key }}' ? 'border-[#F07F22] bg-action-soft' : 'border-slate-50 bg-slate-50'">
                                    <input type="radio" name="role" value="{{ $key }}" x-model="role" class="mt-0.5 accent-[#F07F22]">
                                    <span>
                                        <span class="block text-[12px] font-black text-[#1D4069]">{{ $name }}</span>
                                        <span class="block text-[11px] font-medium text-gray-500">{{ $description }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <button class="w-full py-3 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[11px] font-black uppercase tracking-[0.2em]">
                        <i class="fas fa-paper-plane mr-1"></i>Send invite
                    </button>
                    @if(!is_null($seatsLeft))
                        <p class="text-[11px] font-medium text-gray-400 text-center">{{ $seatsLeft }} team {{ \Illuminate\Support\Str::plural('place', $seatsLeft) }} left on your package.</p>
                    @endif
                </form>
            @else
                <div class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6">
                    <p class="text-[12px] font-medium text-gray-500">Ask an Admin on your team to invite people or change what they can do.</p>
                </div>
            @endif

            <div class="p-5 rounded-[1.5rem] bg-slate-50 space-y-2">
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Roles</p>
                @foreach($roles as $key => [$name, $description])
                    <p class="text-[11px] text-gray-500"><strong class="text-[#1D4069]">{{ $name }}:</strong> {{ $description }}</p>
                @endforeach
            </div>
        </div>
    </div>
    </div>
</div>
@endsection
