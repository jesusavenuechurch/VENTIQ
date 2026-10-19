@extends('layouts.app')
@section('title', 'Verify your email | VENTIQ')
@section('content')
<div class="max-w-md mx-auto px-4 py-16 text-center">
    <div class="w-14 h-14 mx-auto mb-6 rounded-2xl bg-[#F07F22]/10 text-[#F07F22] flex items-center justify-center">
        <i class="fas fa-envelope-open-text text-xl"></i>
    </div>
    <h1 class="text-2xl font-black text-[#1D4069] tracking-tight">Check your email</h1>
    <p class="mt-3 text-[13px] font-medium text-gray-500 leading-relaxed">
        We sent a verification link to <strong class="text-gray-700">{{ auth()->user()->email }}</strong>.
        Open it to finish setting up your account and start creating events.
    </p>

    @if(session('status') === 'verification-link-sent')
        <div class="mt-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-100 text-[12px] font-bold text-emerald-700">
            A new verification link is on its way.
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mt-8">
        @csrf
        <button class="px-6 py-3 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[10px] font-black uppercase tracking-widest">
            Send the link again
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-[#1D4069]">Log out</button>
    </form>
</div>
@endsection
