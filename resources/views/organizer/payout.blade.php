@extends('layouts.app')
@section('title', 'Payouts | VENTIQ')
@section('content')
@php
    $field = 'w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-4 py-3 text-[14px] font-semibold text-gray-900 focus:bg-white focus:border-[#F07F22] outline-none transition-all';
    $label = 'block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1';
    $methods = \App\Models\Organization::PAYOUT_METHODS;
@endphp
<div class="max-w-4xl mx-auto px-4 py-8">
    @include('organizer.partials.header', [
        'title'    => 'Settings',
        'subtitle' => 'Where VENTIQ pays you the money from tickets sold online, after our fees.',
    ])
    @include('organizer.partials.settings-nav')

    @if($errors->any())
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-[12px] font-bold text-rose-700">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    {{-- What's on file --}}
    <div class="mb-6 p-5 rounded-[1.5rem] border {{ $organization->hasPayoutDetails() ? 'bg-mint/40 border-mint' : 'bg-action-soft border-action-soft' }}">
        @if($organization->hasPayoutDetails())
            <p class="text-[11px] font-black uppercase tracking-widest text-mint-ink">We pay you to</p>
            <p class="mt-1 text-[15px] font-black text-[#1D4069]">{{ $organization->payoutSummary(masked: !$canEdit) }}</p>
            <p class="mt-1 text-[11px] text-gray-500">
                Last changed {{ $organization->payout_updated_at?->format('j M Y, H:i') }}@if($changedBy) by {{ $changedBy->name }}@endif.
            </p>
        @else
            <p class="text-[13px] font-black text-action-ink"><i class="fas fa-circle-exclamation mr-1"></i>No payout account yet</p>
            <p class="mt-1 text-[12px] text-action-ink/80">Add one so VENTIQ can pay you for tickets sold online. Payments made directly to you don't need it.</p>
        @endif
    </div>

    @if($canEdit)
        <form method="POST" action="{{ route('organizer.payout.update') }}" x-data="{ method: @js(old('payout_method', $organization->payout_method ?? 'ecocash')) }"
              class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6 sm:p-8 space-y-5">
            @csrf @method('PUT')

            <div>
                <span class="{{ $label }}">Pay me by</span>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @foreach($methods as $value => $name)
                        <label class="cursor-pointer">
                            <input type="radio" name="payout_method" value="{{ $value }}" x-model="method" class="peer sr-only">
                            <span class="block p-4 rounded-2xl border-2 border-slate-100 bg-slate-50 text-center text-[13px] font-black text-gray-700 peer-checked:border-[#F07F22] peer-checked:bg-white">{{ $name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div x-show="method === 'bank_transfer'" x-cloak>
                <label class="{{ $label }}" for="payout_bank_name">Bank</label>
                <input id="payout_bank_name" name="payout_bank_name" maxlength="255" class="{{ $field }}" value="{{ old('payout_bank_name', $organization->payout_bank_name) }}" placeholder="e.g. Standard Lesotho Bank, FNB, Nedbank">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="{{ $label }}" for="payout_account_name">Account name</label>
                    <input id="payout_account_name" name="payout_account_name" required maxlength="255" class="{{ $field }}" value="{{ old('payout_account_name', $organization->payout_account_name) }}" placeholder="Name the account is registered to">
                </div>
                <div>
                    <label class="{{ $label }}" for="payout_account_number" x-text="method === 'bank_transfer' ? 'Account number' : 'Mobile number'">Account number</label>
                    <input id="payout_account_number" name="payout_account_number" required maxlength="60" inputmode="numeric" class="{{ $field }} font-mono" value="{{ old('payout_account_number', $organization->payout_account_number) }}">
                </div>
            </div>

            <p class="text-[12px] text-gray-500"><i class="fas fa-shield-halved mr-1 text-[#1D4069]"></i>For your safety, every change is emailed to all admins on your team, and VENTIQ double-checks a new account with you before the first payout into it.</p>

            <button class="w-full sm:w-auto px-8 py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[11px] font-black uppercase tracking-[0.2em]">Save payout account</button>
        </form>
    @else
        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 text-[12px] font-medium text-gray-500">
            Only an admin on your team can change where VENTIQ pays you.
        </div>
    @endif

    <div class="mt-6 p-5 rounded-[1.5rem] bg-white border border-gray-100 text-[12px] text-gray-500 space-y-1">
        <p class="font-black text-gray-600">How payouts work</p>
        <p>Tickets bought online are paid to VENTIQ. We take our fee off each one and pay you the rest into this account. Each payout and every ticket in it shows in your events' money summaries.</p>
        <p>The accounts attendees pay you into directly are separate: they're under <a href="{{ route('organizer.accounts.index') }}" class="font-bold text-[#1D4069] underline">Accounts</a>.</p>
    </div>
</div>
@endsection
