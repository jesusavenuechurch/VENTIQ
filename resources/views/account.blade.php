@extends('layouts.app')
@section('title', 'My account | VENTIQ')
@section('content')
@php
    $field = 'w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-4 py-3 text-[14px] font-semibold text-gray-900 focus:bg-white focus:border-[#F07F22] outline-none transition-all';
    $label = 'block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1';
    $card  = 'bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6 sm:p-8';
    $button = 'px-6 py-3 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[11px] font-black uppercase tracking-[0.2em]';
@endphp
<div class="max-w-xl mx-auto px-4 py-8 space-y-6">
    <div>
        <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.3em] mb-1">Ventiq</p>
        <h1 class="text-2xl font-black text-[#1D4069] tracking-tight">My account</h1>
        <p class="text-[13px] font-medium text-gray-500 mt-1">{{ $user->email }}@if($user->google_id) · signs in with Google @endif</p>
    </div>

    @if(session('status'))
        <div class="p-4 rounded-2xl bg-mint text-[12px] font-bold text-mint-ink">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-100 text-[12px] font-bold text-rose-700">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('account.update') }}" class="{{ $card }} space-y-4">
        @csrf @method('PUT')
        <div>
            <label for="name" class="{{ $label }}">Your name</label>
            <input id="name" name="name" value="{{ old('name', $user->name) }}" required class="{{ $field }}">
        </div>
        <button class="{{ $button }}">Save name</button>
    </form>

    <form method="POST" action="{{ route('account.password') }}" class="{{ $card }} space-y-4">
        @csrf @method('PUT')
        <div>
            <p class="text-[15px] font-black text-[#1D4069]">{{ $needsCurrent ? 'Change your password' : ($user->google_id ? 'Add a password' : 'Set a new password') }}</p>
            <p class="text-[12px] text-gray-500 mt-1">
                @if($needsCurrent)
                    Forgot it? Sign out and choose "Email me a code instead"; you can set a new one here after.
                @else
                    @if($user->google_id)
                        You sign in with Google or an emailed code. A password lets you sign in on any device without either.
                    @else
                        You signed in with an emailed code, so you can set a new password without the old one.
                    @endif
                @endif
            </p>
        </div>
        @if($needsCurrent)
            <div>
                <label for="current_password" class="{{ $label }}">Current password</label>
                <input id="current_password" type="password" name="current_password" required autocomplete="current-password" class="{{ $field }}">
            </div>
        @endif
        <div>
            <label for="password" class="{{ $label }}">New password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" minlength="10" class="{{ $field }}">
            <p class="text-[11px] text-gray-400 mt-1 ml-1">At least 10 characters, with letters and numbers.</p>
        </div>
        <div>
            <label for="password_confirmation" class="{{ $label }}">New password again</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="{{ $field }}">
        </div>
        <button class="{{ $button }}">Save password</button>
    </form>
</div>
@endsection
