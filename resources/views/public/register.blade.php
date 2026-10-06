<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register for {{ $event->name }} - {{ $organization->name }}</title>
    @vite('resources/css/app.css')
    <style>
        body { font-family: 'Inter', sans-serif; }
        .rounded-ventiq { border-radius: 2.5rem; }
        .sticky-mobile-price { position: fixed; bottom: 0; left: 0; right: 0; z-index: 50; }
    </style>
</head>
<body class="bg-[#FBFBFC] text-[#1D4069] antialiased">

    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-gray-100">
        <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ url('/') }}" class="text-xl font-black tracking-tighter hover:text-[#F07F22]">V.</a>
                <div class="h-4 w-[1px] bg-gray-200"></div>
                <span class="text-[9px] font-bold uppercase tracking-widest text-gray-400 truncate max-w-[120px]">{{ $organization->name }}</span>
            </div>
            <span class="text-[9px] font-black text-[#F07F22] uppercase tracking-[0.2em]">Registration Protocol</span>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 lg:px-6 py-8 pb-40 lg:pb-10">

        <a href="{{ route('public.event', ['orgSlug' => $organization->slug, 'eventSlug' => $event->slug]) }}"
           class="inline-flex items-center text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 hover:text-[#F07F22] transition-all mb-8 group">
            <i class="fas fa-arrow-left mr-2 transition-transform group-hover:-translate-x-1"></i>
            Back to Event Details
        </a>

        @if (session('error'))
            <div role="alert" class="mb-8 p-6 bg-rose-50 border-2 border-rose-100 rounded-3xl text-sm font-bold text-rose-700">
                <i class="fas fa-circle-exclamation mr-1"></i>{{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-8 p-6 bg-rose-50 border-2 border-rose-100 rounded-3xl">
                <h4 class="text-[10px] font-black text-rose-900 uppercase tracking-widest mb-2">Registration Errors</h4>
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li class="text-xs font-bold text-rose-600">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">

            <div class="lg:col-span-7 bg-white rounded-ventiq shadow-2xl shadow-gray-200/50 overflow-hidden border border-gray-100">
                <div class="p-8 sm:p-10 border-b border-gray-50">
                    <h2 class="text-4xl font-black text-gray-900 tracking-tighter mb-2 uppercase italic leading-none">Register</h2>
                    <p class="text-gray-500 font-medium">Securing your spot for <span class="text-[#F07F22] font-bold">{{ $event->name }}</span></p>
                </div>

                <form id="regForm" method="POST" action="{{ route('registration.submit', ['orgSlug' => $organization->slug, 'eventSlug' => $event->slug]) }}" class="p-8 sm:p-10 space-y-8">
                    @csrf
                    <input type="hidden" name="tier_id" value="{{ $selectedTier->id ?? '' }}">

                    {{-- ── PERSONAL INFO ─────────────────────────────────── --}}
                    <div class="space-y-6">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">
                                Full Name <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="full_name" value="{{ old('full_name') }}" required
                                placeholder="e.g. Lerato Molapo"
                                class="w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] transition-all outline-none font-bold text-gray-900">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">
                                    Email Address
                                </label>
                                <input type="email" name="email" value="{{ old('email') }}"
                                    placeholder="lerato@example.com"
                                    class="w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] transition-all outline-none font-bold text-gray-900">
                                <p class="text-[10px] font-bold text-[#F07F22]/60 uppercase mt-2 ml-1 tracking-wider">
                                    <i class="fas fa-envelope mr-1"></i> Ticket sent here
                                </p>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">
                                    Phone Number <span class="text-rose-500">*</span>
                                </label>
                                <div class="flex">
                                    <span class="inline-flex items-center px-4 bg-slate-100 border-2 border-r-0 border-slate-100 rounded-l-2xl font-black text-gray-400 text-xs">+266</span>
                                    <input type="tel" name="phone" id="phone_input" value="{{ preg_replace('/^266(?=\d{8}$)/', '', preg_replace('/\D/', '', (string) old('phone'))) }}" required
                                        class="flex-1 bg-slate-50 border-2 border-slate-50 rounded-r-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] transition-all outline-none font-bold text-gray-900"
                                        placeholder="5949 4756" maxlength="10">
                                </div>
                            </div>
                        </div>

                        {{-- Ticket delivery info banner — explains email + download, no WhatsApp mention --}}
                        <div class="flex items-start gap-4 p-5 bg-[#1D4069]/5 border border-[#1D4069]/10 rounded-2xl">
                            <div class="w-10 h-10 bg-[#1D4069] rounded-xl flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-envelope text-white text-sm"></i>
                            </div>
                            <div>
                                <p class="text-xs font-black text-[#1D4069] uppercase tracking-wider mb-1">Getting Your Ticket</p>
                                <p class="text-xs font-medium text-gray-500 leading-relaxed">
                                    If you provide an email above, your ticket will be sent there once your registration is confirmed.
                                    Either way, you can download it directly from the ticket page right after you register.
                                </p>
                            </div>
                        </div>

                        {{-- WhatsApp toggle — re-enabled now that the Meta Cloud API integration is live. --}}
                        <div class="bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-100 rounded-[2rem] p-6 sm:p-8">
                            <div class="flex items-center mb-6">
                                <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center shadow-sm mr-4">
                                    <i class="fa-brands fa-whatsapp text-emerald-500 text-2xl"></i>
                                </div>
                                <div>
                                    <h4 class="font-black text-emerald-900 text-lg">WhatsApp Delivery</h4>
                                    <p class="text-xs text-emerald-700 font-medium">Instant ticket access on your phone</p>
                                </div>
                            </div>

                            <label class="flex items-start p-5 bg-white/60 backdrop-blur-sm border-2 border-emerald-200 rounded-2xl cursor-pointer hover:bg-white hover:border-emerald-400 transition-all group">
                                <input type="checkbox" name="has_whatsapp" id="has_whatsapp_checkbox" value="1" {{ old('has_whatsapp') ? 'checked' : '' }}
                                    class="mt-1 w-5 h-5 text-emerald-600 rounded-lg border-emerald-300 focus:ring-emerald-500" onchange="toggleWhatsAppConfirmation()">
                                <div class="ml-4">
                                    <span class="font-black text-emerald-900 text-sm uppercase">Send via WhatsApp</span>
                                    <p class="text-[11px] text-emerald-600 mt-1 font-bold uppercase tracking-tight">✅ Instant delivery & easy access</p>
                                </div>
                            </label>

                            <div id="whatsapp-confirmation" class="mt-4 hidden">
                                <div class="bg-emerald-600 text-white rounded-xl p-3 px-5 flex items-center shadow-lg shadow-emerald-200">
                                    <i class="fas fa-check-circle mr-3"></i>
                                    <p class="text-[10px] font-black uppercase tracking-widest">WhatsApp Enabled for +266 <span id="phone-display-confirm"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ── WORKSHOP FIELDS ───────────────────────────────── --}}
                    @if($event->event_type === 'workshop')
                    <div class="pt-6 border-t border-gray-50 space-y-6">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-[#1D4069]/10 rounded-lg flex items-center justify-center">
                                <i class="fas fa-chalkboard-teacher text-[#1D4069] text-sm"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-black text-gray-400 uppercase tracking-[0.3em]">Workshop Details</h3>
                                <p class="text-[10px] font-bold text-[#F07F22] uppercase mt-1">Required for workshop registration</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Position / Title <span class="text-rose-500">*</span></label>
                                <input type="text" name="position" value="{{ old('position') }}" required
                                    placeholder="e.g. Teacher, Principal, Officer"
                                    class="w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] transition-all outline-none font-bold text-gray-900">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Institution <span class="text-rose-500">*</span></label>
                                <input type="text" name="institution" value="{{ old('institution') }}" required
                                    placeholder="e.g. Maseru High School"
                                    class="w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] transition-all outline-none font-bold text-gray-900">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">District <span class="text-rose-500">*</span></label>
                            <select name="district" required
                                class="w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] transition-all outline-none font-bold text-gray-900 appearance-none cursor-pointer">
                                <option value="" disabled selected>Select your district...</option>
                                @foreach(config('constants.workshop_districts') as $key => $label)
                                    <option value="{{ $key }}" {{ old('district') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @endif

                    {{-- ── GROUP TICKET ─────────────────────────────────── --}}
                    {{-- One purchase is one ticket: the same QR admits every
                         person in the group, so no companion details are
                         collected. --}}
                    @if($selectedTier && $selectedTier->quantity_per_purchase > 1)
                    <div class="pt-6 border-t border-gray-50">
                        <div class="bg-amber-50 rounded-2xl p-4 border border-amber-100 flex items-start gap-3">
                            <i class="fas fa-users text-amber-500 mt-0.5"></i>
                            <p class="text-[11px] font-black text-amber-800 uppercase tracking-tight leading-relaxed">
                                Group ticket for {{ $selectedTier->quantity_per_purchase }} people. You'll get one QR code &mdash; each person in your group is scanned with the same code.
                            </p>
                        </div>
                    </div>
                    @endif

                    {{-- ── FREE TICKET BANNER ────────────────────────────── --}}
                    @if($selectedTier && $selectedTier->price == 0)
                    <div class="animate-in fade-in zoom-in duration-500">
                        <div class="bg-gradient-to-br from-emerald-50 to-teal-50 border-2 border-emerald-100 rounded-[2rem] p-8 text-center shadow-sm">
                            <div class="text-4xl mb-4">🎉</div>
                            <h3 class="text-xl font-black text-emerald-900 mb-2 uppercase tracking-tight">This is a Free Ticket!</h3>
                            <p class="text-sm font-bold text-emerald-700 uppercase tracking-widest opacity-80">No payment required. Just complete your registration.</p>
                        </div>
                    </div>
                    @endif

                    {{-- ── TERMS ─────────────────────────────────────────── --}}
                    <div class="pt-10 border-t border-gray-50 space-y-6">
                        <label class="flex items-start cursor-pointer group">
                            <input type="checkbox" name="terms" class="mt-1 w-5 h-5 text-[#F07F22] border-gray-300 rounded" required>
                            <span class="ml-4 text-[11px] font-bold text-gray-500 uppercase tracking-wide">I agree to the <a href="{{ route('terms') }}" target="_blank" rel="noopener" class="underline text-[#1D4069] hover:text-[#F07F22]">ticket terms</a>.</span>
                        </label>

                        <button type="submit" class="hidden lg:block w-full py-6 bg-[#F07F22] hover:bg-[#1D4069] text-white rounded-2xl font-black text-xs uppercase tracking-[0.4em] shadow-xl active:scale-[0.98] transition-all">
                            {{ $selectedTier && $selectedTier->price > 0 ? 'Continue to Payment' : 'Complete Registration' }}
                        </button>
                    </div>
                </form>
            </div>

            {{-- ── DESKTOP SIDEBAR (original gray-900 + emerald-flavored colors) ───────────────────────────────── --}}
            <div class="lg:col-span-5 hidden lg:block sticky top-20">
                <div class="bg-gray-900 rounded-ventiq shadow-2xl overflow-hidden">
                    <div class="p-8 text-white border-b border-white/5">
                        <span class="text-[9px] font-black uppercase tracking-[0.4em] text-[#F07F22]">Confirmed Selection</span>
                        <h2 class="text-2xl font-black tracking-tighter uppercase italic mt-1">{{ $selectedTier->tier_name }}</h2>
                    </div>
                    <div class="p-8 space-y-8">
                        <div class="flex justify-between items-end pb-6 border-b border-white/5">
                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Investment</span>
                            <span class="text-3xl font-black tracking-tighter text-white">
                                {{ $selectedTier->price > 0 ? 'M' . number_format($selectedTier->price) : 'Free' }}
                            </span>
                        </div>
                        <div class="space-y-4 text-white/60">
                            <div class="flex items-center gap-3"><i class="far fa-calendar-alt text-[#F07F22] text-xs"></i><span class="text-[10px] font-black uppercase tracking-widest">{{ $event->event_date->format('d M, Y') }}</span></div>
                            @if($event->venue)
                            <div class="flex items-center gap-3"><i class="fas fa-map-marker-alt text-[#F07F22] text-xs"></i><span class="text-[10px] font-black uppercase tracking-widest truncate">{{ $event->venue }}</span></div>
                            @endif
                            <div class="flex items-center gap-3"><i class="fas fa-tag text-[#F07F22] text-xs"></i><span class="text-[10px] font-black uppercase tracking-widest">{{ $event->organization->name }}</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- ── MOBILE STICKY CTA ─────────────────────────────────────────── --}}
    <div class="lg:hidden sticky-mobile-price bg-white border-t border-gray-100 px-6 py-5 shadow-[0_-15px_40px_rgba(0,0,0,0.08)]">
        <div class="flex items-center justify-between gap-6">
            <div class="flex flex-col">
                <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest leading-none mb-1">Payable</span>
                <span class="text-3xl font-black text-gray-900 tracking-tighter leading-none">
                    {{ isset($selectedTier) && $selectedTier->price > 0 ? 'M' . number_format($selectedTier->price) : 'Free' }}
                </span>
            </div>
            <button type="submit" form="regForm" class="flex-1 py-5 bg-[#F07F22] hover:bg-[#1D4069] text-white rounded-2xl font-black text-[11px] uppercase tracking-[0.3em] active:scale-95 shadow-lg transition-all">
                {{ isset($selectedTier) && $selectedTier->price > 0 ? 'Continue to Payment' : 'Register' }}
            </button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const phoneInput = document.getElementById('phone_input');

        // Auto-format main phone
        phoneInput?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 8) value = value.substring(0, 8);
            if (value.length > 4) value = value.substring(0, 4) + ' ' + value.substring(4);
            e.target.value = value;
            updateWhatsAppConfirmation();
        });

        // WhatsApp confirmation banner — shows/hides with the checkbox,
        // and keeps the displayed number in sync as the phone field changes.
        const whatsappCheckbox = document.getElementById('has_whatsapp_checkbox');
        const whatsappConfirmation = document.getElementById('whatsapp-confirmation');
        const phoneDisplayConfirm = document.getElementById('phone-display-confirm');

        function updateWhatsAppConfirmation() {
            if (!whatsappCheckbox || !whatsappConfirmation) return;
            if (whatsappCheckbox.checked) {
                whatsappConfirmation.classList.remove('hidden');
                if (phoneDisplayConfirm) phoneDisplayConfirm.textContent = phoneInput?.value || '';
            } else {
                whatsappConfirmation.classList.add('hidden');
            }
        }

        window.toggleWhatsAppConfirmation = updateWhatsAppConfirmation;

        // Form submit logic
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const emailInput = document.querySelector('input[name="email"]');
            if (emailInput && !emailInput.value.trim()) {
                emailInput.removeAttribute('name');
            }

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
@include('partials.cookie-notice')
</body>
</html>