@extends('layouts.app')
@section('title', 'Set up your organization | VENTIQ')
@section('content')
@php
    $field = 'w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-4 py-3 text-[14px] font-semibold text-gray-900 focus:bg-white focus:border-[#F07F22] outline-none transition-all';
    $label = 'block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1';
    $firstName = explode(' ', auth()->user()->name ?? '')[0];
@endphp
<div class="max-w-xl mx-auto px-4 py-12">
    <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.3em] mb-1">Welcome to Ventiq</p>
    <h1 class="text-2xl font-black text-[#1D4069] tracking-tight">Hi {{ $firstName }}, tell us who's hosting</h1>
    <p class="text-[13px] font-medium text-gray-500 mt-2">Attendees see this name on your events and tickets. We'll use the phone number to tell you when someone pays.</p>

    @if($errors->any())
        <div class="mt-6 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-[12px] font-bold text-rose-700">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('organizer.setup.store') }}" enctype="multipart/form-data" class="mt-8 space-y-5 bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6 sm:p-8">
        @csrf
        <div>
            <label class="{{ $label }}" for="name">Organization name</label>
            <input id="name" name="name" required maxlength="255" class="{{ $field }}"
                   value="{{ old('name', $organization->name) }}" placeholder="e.g. Maseru Youth Network">
            <p class="text-[11px] font-medium text-gray-400 mt-1 ml-1">We started with your own name; change it if you host as a church, company or group.</p>
        </div>
        <div>
            <label class="{{ $label }}" for="phone">Phone number</label>
            <div class="flex">
                <span class="inline-flex items-center px-4 bg-slate-100 rounded-l-2xl font-bold text-gray-500 text-sm">+266</span>
                <input id="phone" name="phone" required inputmode="numeric" maxlength="9" class="{{ $field }} rounded-l-none"
                       value="{{ old('phone', $organization->phone ? substr($organization->phone, -8) : '') }}" placeholder="5949 4756">
            </div>
        </div>
        <div>
            <label class="{{ $label }}" for="contact_email">Contact email for attendees <span class="normal-case text-gray-300">(optional)</span></label>
            <input id="contact_email" type="email" name="contact_email" maxlength="255" class="{{ $field }}"
                   value="{{ old('contact_email', $organization->contact_email) }}">
        </div>
        <div>
            <label class="{{ $label }}" for="description">About your organization <span class="normal-case text-gray-300">(optional)</span></label>
            <textarea id="description" name="description" rows="3" maxlength="1000" class="{{ $field }}">{{ old('description', $organization->description) }}</textarea>
        </div>
        <div x-data="{ preview: null, name: null }">
            <span class="{{ $label }}">Logo <span class="normal-case text-gray-300">(optional, shown on reports)</span></span>
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 shrink-0 rounded-2xl bg-slate-50 border-2 border-dashed border-slate-200 flex items-center justify-center overflow-hidden">
                    <img x-show="preview" :src="preview" alt="" class="w-full h-full object-contain" x-cloak>
                    <i x-show="!preview" class="fas fa-image text-slate-300 text-xl"></i>
                </div>
                <div class="min-w-0">
                    <label for="logo" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full bg-action hover:bg-action-ink text-white text-[10px] font-black uppercase tracking-widest cursor-pointer">
                        <i class="fas fa-upload"></i><span x-text="preview ? 'Change logo' : 'Upload logo'">Upload logo</span>
                    </label>
                    <p class="text-[11px] text-gray-400 mt-1.5 truncate" x-text="name || 'PNG, JPG or WebP'">PNG, JPG or WebP</p>
                </div>
            </div>
            <input id="logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="sr-only"
                   x-on:change="const f = $event.target.files[0]; name = f ? f.name : null; preview = f ? URL.createObjectURL(f) : null">
        </div>
        <button class="w-full py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[11px] font-black uppercase tracking-[0.2em]">Continue</button>
    </form>
</div>
@endsection
