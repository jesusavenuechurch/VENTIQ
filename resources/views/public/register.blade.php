@extends('layouts.attendee')

@section('title', "Register: {$event->name} | {$organization->name}")
@section('width', '3xl')

@php
    $paid  = $selectedTier && $selectedTier->price > 0;
    $group = (int) ($selectedTier->quantity_per_purchase ?? 1);
    $input = 'w-full bg-white border-2 border-gray-100 rounded-2xl px-4 py-3.5 text-[15px] font-bold text-gray-900 outline-none focus:border-[#F07F22] transition-colors';
    $label = 'block text-[12px] font-black text-gray-600 mb-1.5';
@endphp

@section('content')
<a href="{{ route('event.short', [$organization->slug, $event->slug]) }}" class="inline-flex items-center gap-2 text-[12px] font-bold text-gray-500 hover:text-[#1D4069] mb-4">
    <i class="fas fa-arrow-left text-[10px]"></i>{{ $event->name }}
</a>

@if (session('error'))
    <div role="alert" class="mb-4 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-[13px] font-bold text-rose-700">
        <i class="fas fa-circle-exclamation mr-1"></i>{{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div role="alert" class="mb-4 p-4 rounded-2xl bg-rose-50 border border-rose-100">
        <p class="text-[13px] font-black text-rose-700 mb-1">Please check these:</p>
        <ul class="list-disc list-inside text-[13px] font-bold text-rose-600">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

{{-- What you're getting --}}
<div class="mb-4 p-5 rounded-[1.5rem] bg-[#1D4069] text-white flex items-center justify-between gap-4">
    <div class="min-w-0">
        <p class="text-[11px] font-bold text-white/60">Your ticket</p>
        <p class="text-[18px] font-black leading-tight truncate">{{ $selectedTier->tier_name }}</p>
        <p class="text-[12px] text-white/70 mt-1">
            {{ $event->event_date?->format('D j M, g:i A') }}@if($event->venue) · {{ $event->venue }}@endif
        </p>
        @if($group > 1)
            <p class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/10 text-[11px] font-bold"><i class="fas fa-users"></i>Admits {{ $group }} people with one QR code</p>
        @endif
    </div>
    <p class="text-[26px] font-black whitespace-nowrap {{ $paid ? '' : 'text-emerald-300' }}">{{ \App\Support\Money::price($selectedTier->price) }}</p>
</div>

<form id="regForm" method="POST" action="{{ route('registration.submit', ['orgSlug' => $organization->slug, 'eventSlug' => $event->slug]) }}"
      class="p-5 sm:p-8 rounded-[1.5rem] bg-white border border-gray-100 shadow-sm space-y-5">
    @csrf
    <input type="hidden" name="tier_id" value="{{ $selectedTier->id ?? '' }}">

    <div>
        <h1 class="text-[22px] font-black">Your details</h1>
        <p class="text-[13px] text-gray-500">{{ $paid ? "We'll hold your place while you pay." : "That's all we need. It's free!" }}</p>
    </div>

    <div>
        <label for="full_name" class="{{ $label }}">Full name <span class="text-rose-500">*</span></label>
        <input id="full_name" type="text" name="full_name" value="{{ old('full_name') }}" required autocomplete="name" placeholder="e.g. Lerato Molapo" class="{{ $input }}">
        @if($group > 1)<p class="mt-1.5 text-[12px] text-gray-500">The name of the person booking for the group.</p>@endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label for="phone_input" class="{{ $label }}">Phone number <span class="text-rose-500">*</span></label>
            <div class="flex">
                <span class="inline-flex items-center px-3.5 rounded-l-2xl border-2 border-r-0 border-gray-100 bg-slate-50 text-[13px] font-black text-gray-500">+266</span>
                <input type="tel" name="phone" id="phone_input" value="{{ preg_replace('/^266(?=\d{8}$)/', '', preg_replace('/\D/', '', (string) old('phone'))) }}" required
                       placeholder="5949 4756" inputmode="tel" autocomplete="tel-national"
                       class="{{ $input }} rounded-l-none">
            </div>
        </div>
        <div>
            <label for="email" class="{{ $label }}">Email <span class="font-medium text-gray-400">(optional)</span></label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="lerato@example.com" class="{{ $input }}">
        </div>
    </div>

    {{-- How the ticket reaches them --}}
    <label class="flex items-start gap-3 p-4 rounded-2xl bg-mint/60 border border-mint cursor-pointer">
        <input type="checkbox" name="has_whatsapp" id="has_whatsapp_checkbox" value="1" {{ old('has_whatsapp') ? 'checked' : '' }}
               class="mt-0.5 w-5 h-5 rounded accent-[#1E7B4B]" onchange="toggleWhatsAppConfirmation()">
        <span>
            <span class="block text-[14px] font-black text-mint-ink"><i class="fa-brands fa-whatsapp mr-1"></i>Send my ticket on WhatsApp</span>
            <span class="block text-[12px] text-mint-ink/80">To this number. Your ticket also goes to your email if you gave one, and you can open it straight after registering.</span>
            <span id="whatsapp-confirmation" class="hidden mt-2 text-[12px] font-black text-mint-ink">On its way to +266 <span id="phone-display-confirm"></span> once it's ready.</span>
        </span>
    </label>

    @if($event->event_type === 'workshop')
        <div class="pt-5 border-t border-gray-100 space-y-5">
            <div>
                <h2 class="text-[16px] font-black">Workshop details</h2>
                <p class="text-[12px] text-gray-500">The organizer needs these for the attendance register.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="position" class="{{ $label }}">Position or title <span class="text-rose-500">*</span></label>
                    <input id="position" type="text" name="position" value="{{ old('position') }}" required placeholder="e.g. Teacher, Principal" class="{{ $input }}">
                </div>
                <div>
                    <label for="institution" class="{{ $label }}">Institution <span class="text-rose-500">*</span></label>
                    <input id="institution" type="text" name="institution" value="{{ old('institution') }}" required placeholder="e.g. Maseru High School" class="{{ $input }}">
                </div>
            </div>
            <div>
                <label for="district" class="{{ $label }}">District <span class="text-rose-500">*</span></label>
                <select id="district" name="district" required class="{{ $input }} cursor-pointer">
                    <option value="" disabled {{ old('district') ? '' : 'selected' }}>Choose your district</option>
                    @foreach(config('constants.workshop_districts') as $key => $districtLabel)
                        <option value="{{ $key }}" {{ old('district') == $key ? 'selected' : '' }}>{{ $districtLabel }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    <div class="pt-5 border-t border-gray-100 space-y-4">
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="terms" class="mt-0.5 w-5 h-5 rounded accent-[#F07F22]" required>
            <span class="text-[13px] font-medium text-gray-600">I agree to the <a href="{{ route('terms') }}" target="_blank" rel="noopener" class="font-bold underline text-[#1D4069] hover:text-[#F07F22]">ticket terms</a>.</span>
        </label>

        <button type="submit" class="hidden sm:block w-full py-4 rounded-2xl bg-[#F07F22] hover:bg-[#1D4069] text-white text-[12px] font-black uppercase tracking-widest shadow-lg transition-colors">
            {{ $paid ? 'Continue to payment' : 'Get my ticket' }}
        </button>
    </div>
</form>

{{-- Phones: the button stays in reach. --}}
<div class="sm:hidden fixed inset-x-0 bottom-0 z-30 px-4 py-3 bg-white border-t border-gray-100 shadow-[0_-10px_30px_rgba(0,0,0,0.06)] flex items-center gap-4">
    <div>
        <p class="text-[10px] font-bold text-gray-400">{{ $paid ? 'To pay' : 'Price' }}</p>
        <p class="text-[20px] font-black leading-none">{{ \App\Support\Money::price($selectedTier->price) }}</p>
    </div>
    <button type="submit" form="regForm" class="flex-1 py-4 rounded-2xl bg-[#F07F22] text-white text-[12px] font-black uppercase tracking-widest shadow-lg">
        {{ $paid ? 'Continue to payment' : 'Get my ticket' }}
    </button>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const phoneInput = document.getElementById('phone_input');

    phoneInput?.addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '');
        // "+266 5949 4756" pasted whole: drop the country code, not the end.
        if (value.length > 8 && value.startsWith('266')) value = value.substring(3);
        if (value.length > 8) value = value.substring(0, 8);
        if (value.length > 4) value = value.substring(0, 4) + ' ' + value.substring(4);
        e.target.value = value;
        updateWhatsAppConfirmation();
    });

    const whatsappCheckbox = document.getElementById('has_whatsapp_checkbox');
    const whatsappConfirmation = document.getElementById('whatsapp-confirmation');
    const phoneDisplayConfirm = document.getElementById('phone-display-confirm');

    function updateWhatsAppConfirmation() {
        if (!whatsappCheckbox || !whatsappConfirmation) return;
        whatsappConfirmation.classList.toggle('hidden', !whatsappCheckbox.checked);
        if (phoneDisplayConfirm) phoneDisplayConfirm.textContent = phoneInput?.value || '';
    }
    window.toggleWhatsAppConfirmation = updateWhatsAppConfirmation;
    updateWhatsAppConfirmation();

    document.getElementById('regForm')?.addEventListener('submit', function () {
        const emailInput = document.querySelector('input[name="email"]');
        if (emailInput && !emailInput.value.trim()) emailInput.removeAttribute('name');

        // The server adds +266; only the 8 local digits are sent, so a
        // number re-shown after an error can't get the prefix twice.
        if (phoneInput) {
            let cleanPhone = phoneInput.value.replace(/\D/g, '');
            if (cleanPhone.length > 8 && cleanPhone.startsWith('266')) cleanPhone = cleanPhone.slice(3);
            phoneInput.value = cleanPhone;
        }
    });
});
</script>
@endpush
