@extends('layouts.app')
@section('title', 'Payment reviewed | VENTIQ')
@section('content')
<div class="max-w-xl mx-auto px-4 py-16 text-center">
    <i class="fas fa-check-circle text-mint-ink text-4xl mb-4"></i>
    <p class="text-lg font-black text-[#1D4069]">{{ $message }}</p>
    <p class="mt-2 text-[12px] font-medium text-gray-500">{{ $payment->ticket->event->name }}</p>
    @auth
        <a href="{{ route('organizer.payments.index') }}" class="inline-block mt-8 px-5 py-3 rounded-2xl bg-[#1D4069] text-white text-[10px] font-black uppercase tracking-widest">See other payments</a>
    @endauth
</div>
@endsection
