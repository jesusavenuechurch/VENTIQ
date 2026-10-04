@extends('layouts.app')
@section('title', 'Review payment | VENTIQ')
@section('content')
<div class="max-w-xl mx-auto px-4 py-10">
    <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.3em] mb-1">Ventiq · {{ $payment->ticket->event->organization->name }}</p>
    <h1 class="text-2xl font-black text-[#1D4069] tracking-tight mb-6">Has this payment been received?</h1>

    @if($decided)
        <div class="p-6 rounded-[1.5rem] bg-slate-50 border border-slate-100 text-[13px] font-bold text-gray-600">
            This payment has already been {{ $payment->status === 'approved' ? 'confirmed' : 'rejected' }}. Nothing more to do.
        </div>
    @else
        @include('organizer.partials.payment-card', [
            'payment'   => $payment,
            'action'    => $actionUrl,
            'canDecide' => true,
        ])
        <p class="mt-4 text-[11px] font-medium text-gray-400">Check your {{ $payment->paymentAccount?->label ?? 'account' }} statement for this reference before activating.</p>
    @endif
</div>
@endsection
