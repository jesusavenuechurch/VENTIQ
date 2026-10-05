@extends('layouts.app')
@section('title', 'Payments to confirm | VENTIQ')
@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    @include('organizer.partials.header', [
        'title'    => 'Payments to confirm',
        'subtitle' => 'Attendees who paid you directly. Check the money has arrived, then activate their ticket.',
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
                <i class="fas fa-check-circle text-mint-ink text-3xl mb-3"></i>
                <p class="text-[13px] font-bold text-gray-600">Nothing waiting. New payments appear here, and you'll get an email for each one.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
