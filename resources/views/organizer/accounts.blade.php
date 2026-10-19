@extends('layouts.app')
@section('title', 'Payment accounts | VENTIQ')
@section('content')
@php
    $field = 'w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-4 py-3 text-[14px] font-semibold text-gray-900 focus:bg-white focus:border-[#F07F22] outline-none transition-all';
    $label = 'block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1';
    $canCreate = auth()->user()->can('create_payment_method');
    $canEdit   = auth()->user()->can('edit_payment_method');
    $canDelete = auth()->user()->can('delete_payment_method');
@endphp
<div class="max-w-5xl mx-auto px-4 py-8">
    @include('organizer.partials.header', [
        'title'    => 'Payment accounts',
        'subtitle' => 'Where attendees can pay you directly. Add as many as you need; each event chooses which to offer, starting from your defaults.',
    ])
    {{-- Narrower than the page frame, left-aligned under the tabs. --}}
    <div class="max-w-3xl">

    @if($errors->any())
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-[12px] font-bold text-rose-700">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    @if($canCreate)
        <details class="mb-6 bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6" @if($errors->any() || $accounts->isEmpty()) open @endif>
            <summary class="cursor-pointer text-[11px] font-black uppercase tracking-widest text-[#1D4069]"><i class="fas fa-plus mr-1"></i>Add an account</summary>
            <form method="POST" action="{{ route('organizer.accounts.store') }}" class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                @csrf
                <div>
                    <label class="{{ $label }}">Method</label>
                    <select name="payment_method" required class="{{ $field }}">
                        @foreach($methods as $key => $methodLabel)
                            <option value="{{ $key }}" @selected(old('payment_method') === $key)>{{ $methodLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $label }}">Name for this account</label>
                    <input name="account_name" value="{{ old('account_name') }}" maxlength="255" class="{{ $field }}" placeholder="e.g. Events Account">
                </div>
                <div class="sm:col-span-2">
                    <label class="{{ $label }}">Number attendees pay to <span class="normal-case text-gray-300">(not needed for cash)</span></label>
                    <input name="account_number" value="{{ old('account_number') }}" maxlength="255" class="{{ $field }}" placeholder="e.g. 6255 2155 or bank account number">
                </div>
                <div class="sm:col-span-2">
                    <label class="{{ $label }}">Instructions <span class="normal-case text-gray-300">(optional)</span></label>
                    <input name="instructions" value="{{ old('instructions') }}" maxlength="1000" class="{{ $field }}" placeholder="e.g. Use your ticket number as the reference">
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <button class="px-6 py-3 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[10px] font-black uppercase tracking-widest">Add account</button>
                </div>
            </form>
        </details>
    @endif

    <div class="space-y-3">
        @forelse($accounts as $account)
            <div class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6 {{ $account->is_active ? '' : 'opacity-60' }}" x-data="{ editing: false }">
                <div class="flex flex-wrap items-start justify-between gap-4" x-show="!editing">
                    <div>
                        <p class="text-[15px] font-black text-[#1D4069]">
                            {{ $account->display_label }}
                            @if($account->is_default)<span class="ml-2 px-2 py-0.5 rounded-full bg-brand text-white text-[9px] font-black uppercase tracking-widest align-middle">Default</span>@endif
                            @unless($account->is_active)<span class="ml-2 px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 text-[9px] font-black uppercase tracking-widest align-middle">Off</span>@endunless
                        </p>
                        @if($account->account_number)<p class="font-mono text-[13px] text-gray-600 mt-1">{{ $account->account_number }}</p>@endif
                        @if($account->instructions)<p class="text-[12px] text-gray-500 mt-1">{{ $account->instructions }}</p>@endif
                        @if($account->has_payments)<p class="text-[11px] text-gray-400 mt-2"><i class="fas fa-lock mr-1"></i>Has received payments, so its number is locked.</p>@endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if($canEdit)
                            @if($account->is_active && !$account->is_default)
                                <form method="POST" action="{{ route('organizer.accounts.default', $account) }}">@csrf
                                    <button class="px-3 py-1.5 rounded-full bg-slate-50 border border-slate-100 text-[10px] font-black uppercase tracking-widest text-[#1D4069]">Make default</button>
                                </form>
                            @endif
                            <button type="button" @click="editing = true" class="px-3 py-1.5 rounded-full bg-slate-50 border border-slate-100 text-[10px] font-black uppercase tracking-widest text-gray-500">Edit</button>
                            <form method="POST" action="{{ route('organizer.accounts.toggle', $account) }}">@csrf
                                <button class="px-3 py-1.5 rounded-full bg-slate-50 border border-slate-100 text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $account->is_active ? 'Switch off' : 'Switch on' }}</button>
                            </form>
                        @endif
                        @if($canDelete && !$account->has_payments)
                            <form method="POST" action="{{ route('organizer.accounts.destroy', $account) }}" onsubmit="return confirm('Remove this account?')">@csrf @method('DELETE')
                                <button class="px-3 py-1.5 rounded-full bg-white border border-gray-200 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-rose-600">Remove</button>
                            </form>
                        @endif
                    </div>
                </div>

                @if($canEdit)
                    <form x-show="editing" x-cloak method="POST" action="{{ route('organizer.accounts.update', $account) }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @csrf @method('PUT')
                        <div>
                            <label class="{{ $label }}">Name for this account</label>
                            <input name="account_name" value="{{ $account->account_name }}" maxlength="255" class="{{ $field }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Number</label>
                            <input name="account_number" value="{{ $account->account_number }}" maxlength="255" class="{{ $field }}" @disabled($account->has_payments)>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $label }}">Instructions</label>
                            <input name="instructions" value="{{ $account->instructions }}" maxlength="1000" class="{{ $field }}">
                        </div>
                        <div class="sm:col-span-2 flex justify-end gap-2">
                            <button type="button" @click="editing = false" class="px-5 py-3 text-[10px] font-black uppercase tracking-widest text-gray-400">Cancel</button>
                            <button class="px-5 py-3 rounded-2xl bg-[#1D4069] text-white text-[10px] font-black uppercase tracking-widest">Save</button>
                        </div>
                    </form>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-[1.5rem] border border-dashed border-gray-200 p-10 text-center text-[13px] font-bold text-gray-500">
                No accounts yet. Add one so attendees can pay you directly, or use online payment through VENTIQ.
            </div>
        @endforelse
    </div>
    </div>
</div>
@endsection
