@extends('layouts.app')
@section('title', 'Organization | VENTIQ')
@section('content')
@php
    $field = 'w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-4 py-3 text-[14px] font-semibold text-gray-900 focus:bg-white focus:border-[#F07F22] outline-none transition-all disabled:opacity-70';
    $label = 'block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1';
    $logoUrl = $organization->logo_path ? Storage::url($organization->logo_path) : null;
@endphp
<div class="max-w-5xl mx-auto px-4 py-8">
    @include('organizer.partials.header', [
        'title'    => 'Settings',
        'subtitle' => 'Who\'s hosting: the name, logo and contact details attendees see on your events and tickets.',
        'subnav'   => 'organizer.partials.settings-nav',
    ])
    {{-- Narrower than the page frame, left-aligned under the tabs. --}}
    <div class="max-w-3xl">

    @if($errors->any())
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-[12px] font-bold text-rose-700">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    @unless($canEdit)
        <div class="mb-6 p-4 rounded-2xl bg-slate-50 border border-slate-100 text-[12px] font-medium text-gray-500">
            You can see your organization's details. Only an admin on your team can change them.
        </div>
    @endunless

    <form method="POST" action="{{ route('organizer.organization.update') }}" enctype="multipart/form-data"
          class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6 sm:p-8 grid grid-cols-1 md:grid-cols-2 gap-5">
        @csrf @method('PUT')
        <fieldset @disabled(!$canEdit) class="contents">

        {{-- Logo --}}
        <div class="md:col-span-2" x-data="{ preview: @js($logoUrl), name: null, remove: false }">
            <span class="{{ $label }}">Logo</span>
            <div class="flex flex-wrap items-center gap-4">
                <div class="w-20 h-20 shrink-0 rounded-2xl bg-slate-50 border-2 border-dashed border-slate-200 flex items-center justify-center overflow-hidden">
                    <img x-show="preview && !remove" :src="preview" alt="" class="w-full h-full object-contain" x-cloak>
                    <i x-show="!preview || remove" class="fas fa-image text-slate-300 text-2xl"></i>
                </div>
                @if($canEdit)
                <div class="min-w-0 space-y-1.5">
                    <label for="logo" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full bg-action hover:bg-action-ink text-white text-[10px] font-black uppercase tracking-widest cursor-pointer">
                        <i class="fas fa-upload"></i><span x-text="preview ? 'Change logo' : 'Upload logo'">Upload logo</span>
                    </label>
                    <p class="text-[11px] text-gray-400 truncate" x-text="name || 'PNG, JPG or WebP, up to 4 MB'">PNG, JPG or WebP, up to 4 MB</p>
                    @if($logoUrl)
                        <label class="flex items-center gap-2 text-[11px] font-bold text-gray-500 cursor-pointer">
                            <input type="checkbox" name="remove_logo" value="1" x-model="remove" class="rounded"> Remove the logo
                        </label>
                    @endif
                </div>
                @endif
            </div>
            <input id="logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="sr-only"
                   x-on:change="const f = $event.target.files[0]; name = f ? f.name : null; preview = f ? URL.createObjectURL(f) : @js($logoUrl); remove = false">
        </div>

        <div class="md:col-span-2">
            <label class="{{ $label }}" for="name">Organization name</label>
            <input id="name" name="name" required maxlength="255" class="{{ $field }}" value="{{ old('name', $organization->name) }}">
        </div>

        <div class="md:col-span-2">
            <label class="{{ $label }}" for="tagline">Tagline <span class="normal-case text-gray-300">(optional)</span></label>
            <input id="tagline" name="tagline" maxlength="120" class="{{ $field }}" value="{{ old('tagline', $organization->tagline) }}" placeholder="e.g. Bringing Maseru's young leaders together">
        </div>

        <div>
            <label class="{{ $label }}" for="phone">Phone number</label>
            <div class="flex">
                <span class="inline-flex items-center px-4 bg-slate-100 rounded-l-2xl font-bold text-gray-500 text-sm">+266</span>
                <input id="phone" name="phone" required inputmode="numeric" maxlength="9" class="{{ $field }} rounded-l-none"
                       value="{{ old('phone', $organization->phone ? substr(preg_replace('/\D/', '', $organization->phone), -8) : '') }}" placeholder="5949 4756">
            </div>
            <p class="text-[11px] text-gray-400 mt-1 ml-1">We message this number when someone pays you.</p>
        </div>

        <div>
            <label class="{{ $label }}" for="contact_email">Contact email for attendees <span class="normal-case text-gray-300">(optional)</span></label>
            <input id="contact_email" type="email" name="contact_email" maxlength="255" class="{{ $field }}" value="{{ old('contact_email', $organization->contact_email) }}">
        </div>

        <div class="md:col-span-2">
            <label class="{{ $label }}" for="website">Website or social page <span class="normal-case text-gray-300">(optional)</span></label>
            <input id="website" type="url" name="website" maxlength="255" class="{{ $field }}" value="{{ old('website', $organization->website) }}" placeholder="https://facebook.com/yourpage">
        </div>

        <div class="md:col-span-2">
            <label class="{{ $label }}" for="description">About your organization <span class="normal-case text-gray-300">(optional)</span></label>
            <textarea id="description" name="description" rows="4" maxlength="1000" class="{{ $field }}">{{ old('description', $organization->description) }}</textarea>
        </div>

        {{-- The organization's own events page: super admins only until it's sold. --}}
        @if(auth()->user()->isSuperAdmin())
        <div class="md:col-span-2 p-4 rounded-2xl bg-slate-50 text-[12px] text-gray-500">
            <p class="font-black text-gray-600"><i class="fas fa-link mr-1"></i>Your events page</p>
            <a href="{{ route('public.events', $organization->slug) }}" target="_blank" rel="noopener" class="font-mono text-[#1D4069] break-all hover:underline">{{ route('public.events', $organization->slug) }}</a>
            @if($organization->events()->exists())
                <p class="mt-1">This address stays the same if you rename the organization, so posters, QR codes and links you've shared keep working.</p>
            @else
                <p class="mt-1">Until you create your first event, this address follows your organization's name. After that it stays fixed.</p>
            @endif
        </div>
        @endif

        @if($canEdit)
            <div class="md:col-span-2">
                <button class="w-full sm:w-auto px-8 py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[11px] font-black uppercase tracking-[0.2em]">Save changes</button>
            </div>
        @endif
        </fieldset>
    </form>
    </div>
</div>
@endsection
