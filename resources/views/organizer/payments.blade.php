@extends('layouts.app')
@section('title', 'Payments to confirm | VENTIQ')
@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    @include('organizer.partials.header', [
        'title'    => 'Payments to confirm',
        'subtitle' => 'These people paid you directly. Check the money arrived, then send them their ticket.',
    ])

    @if(!$canDecide)
        <div class="mb-6 p-4 rounded-2xl bg-slate-50 border border-slate-100 text-[12px] font-medium text-gray-500">
            You can see these payments, but only team members allowed to confirm payments can activate tickets.
        </div>
    @endif

    <div class="space-y-4">
        @forelse($payments as $payment)
            @include('organizer.partials.payment-card', [
                'payment' => $payment,
                'action'  => route('organizer.payments.decide', $payment),
            ])
        @empty
            <div class="bg-white rounded-[1.5rem] border border-dashed border-gray-200 p-10 text-center">
                @include('organizer.partials.crowd', ['seeds' => ['happy-1', 'happy-2', 'happy-3']])
                <p class="text-[15px] font-black text-[#1D4069]">All caught up!</p>
                <p class="text-[13px] font-medium text-gray-500 mt-1">Nobody's waiting on you. When someone pays you directly, they'll show up here and we'll email you.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
