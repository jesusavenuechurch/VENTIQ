@extends('layouts.app')

@section('title', 'Account kept | VENTIQ')

@section('content')
<div class="max-w-md mx-auto px-4 py-16 text-center text-[#1D4069]">
    <div class="w-16 h-16 bg-mint rounded-2xl flex items-center justify-center mx-auto mb-6">
        <i class="fas fa-check text-mint-ink text-2xl"></i>
    </div>
    <h1 class="text-2xl font-black tracking-tight">Your account stays</h1>
    <p class="mt-2 text-[14px] text-gray-500">Thanks, {{ \Illuminate\Support\Str::before((string) $user->name, ' ') ?: 'there' }}. We won't remove it. When you're ready, create your first event and start selling tickets.</p>
    <a href="{{ route('login') }}" class="mt-8 inline-block px-6 py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[11px] font-black uppercase tracking-[0.2em]">Sign in</a>
</div>
@endsection
