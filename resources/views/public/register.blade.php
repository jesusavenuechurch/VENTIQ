<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register for {{ $event->name }} - {{ $organization->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');
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
                    <p class="text-gray-500 font-medium">Securing spot for <span class="text-[#F07F22] font-bold">{{ $event->name }}</span></p>
                </div>

                <form id="regForm" method="POST" action="{{ route('registration.submit', ['orgSlug' => $organization->slug, 'eventSlug' => $event->slug]) }}" class="p-8 sm:p-10 space-y-10">
                    @csrf
                    <input type="hidden" name="tier_id" value="{{ $selectedTier->id ?? '' }}">

                    {{-- Personal Info --}}
                    <div class="space-y-6">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Full Name <span class="text-rose-500">*</span></label>
                            <input type="text" name="full_name" value="{{ old('full_name') }}" required
                                placeholder="e.g. Lerato Molapo"
                                class="w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] transition-all outline-none font-bold text-gray-900">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Email Address</label>
                                <input type="email" name="email" value="{{ old('email') }}"
                                    placeholder="lerato@example.com"
                                    class="w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] transition-all outline-none font-bold text-gray-900">
                                <p class="text-[10px] font-bold text-[#F07F22]/60 uppercase mt-2 ml-1 tracking-wider">
                                    <i class="fas fa-envelope mr-1"></i> Tickets sent here
                                </p>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Phone Number <span class="text-rose-500">*</span></label>
                                <div class="flex">
                                    <span class="inline-flex items-center px-4 bg-slate-100 border-2 border-r-0 border-slate-100 rounded-l-2xl font-black text-gray-400 text-xs">+++266</span>
                                    <input type="tel" name="phone" id="phone_input" value="{{ old('phone') }}" required
                                        class="flex-1 bg-slate-50 border-2 border-slate-50 rounded-r-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] transition-all outline-none font-bold text-gray-900"
                                        placeholder="5949 4756" maxlength="10">
                                </div>
                            </div>
                        </div>

                        {{-- WhatsApp Toggle --}}
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
                        <input type="hidden" name="preferred_delivery" id="preferred_delivery_input" value="{{ old('has_whatsapp') ? 'both' : 'email' }}">
                    </div>

                    {{-- Additional Attendees --}}
                    @if($selectedTier && $selectedTier->quantity_per_purchase > 1)
                    <div class="pt-6 border-t border-gray-50 space-y-6">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-[#1D4069]/10 rounded-lg flex items-center justify-center text-[#1D4069]">
                                <i class="fas fa-users text-sm"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-black text-gray-400 uppercase tracking-[0.3em]">Additional Attendees</h3>
                                <p class="text-[10px] font-bold text-[#F07F22] uppercase mt-1">This ticket covers {{ $selectedTier->quantity_per_purchase }} guests</p>
                            </div>
                        </div>

                        @for($i = 2; $i <= $selectedTier->quantity_per_purchase; $i++)
                        <div class="bg-slate-50 border-2 border-slate-50 rounded-[2rem] p-6 sm:p-8 relative group hover:border-[#1D4069]/20 hover:bg-white transition-all">
                            <div class="absolute -top-3 left-8 px-4 py-1 bg-[#1D4069] text-white text-[10px] font-black rounded-full uppercase tracking-widest shadow-lg">
                                Guest #{{ $i }}
                            </div>

                            <div class="space-y-4 mt-2">
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Full Name <span class="text-rose-500">*</span></label>
                                    <input type="text" name="companion_{{ $i }}_name" value="{{ old('companion_' . $i . '_name') }}" required
                                        class="w-full bg-white border border-gray-100 rounded-xl px-5 py-3 font-bold text-gray-900 focus:border-[#F07F22] outline-none transition-all"
                                        placeholder="e.g., Jane Smith">
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Phone <span class="lowercase text-gray-300">(optional)</span></label>
                                        <div class="flex">
                                            <span class="inline-flex items-center px-4 bg-gray-50 border border-r-0 border-gray-100 rounded-l-xl font-bold text-gray-400 text-xs">+266</span>
                                            <input type="tel" name="companion_{{ $i }}_phone" value="{{ old('companion_' . $i . '_phone') }}"
                                                class="flex-1 bg-white border border-gray-100 rounded-r-xl px-4 py-3 text-sm font-bold focus:border-[#F07F22] outline-none companion-phone"
                                                placeholder="5949 4756" maxlength="9">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Email <span class="lowercase text-gray-300">(optional)</span></label>
                                        <input type="email" name="companion_{{ $i }}_email" value="{{ old('companion_' . $i . '_email') }}"
                                            class="w-full bg-white border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold focus:border-[#F07F22] outline-none"
                                            placeholder="jane@example.com">
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endfor

                        <div class="bg-amber-50 rounded-2xl p-4 border border-amber-100 flex items-start">
                            <i class="fas fa-info-circle text-amber-500 mt-1 mr-3"></i>
                            <p class="text-[11px] font-black text-amber-800 uppercase tracking-tight leading-relaxed">
                                Each person will receive their own ticket with a unique QR code for event entry.
                            </p>
                        </div>
                    </div>
                    @endif

                    {{-- Payment Section --}}
                    @if($selectedTier && $selectedTier->price > 0)
                    <div class="pt-6 border-t border-gray-50 space-y-6">
                        <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-[0.3em]">Payment Setup</h3>
                        
                        @if($paymentMethods->isNotEmpty())

                            {{-- Payment Plan (Full vs Installments) --}}
                            @if($event->allow_installments)
                            <div class="space-y-4">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Payment Plan</label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <label class="relative cursor-pointer group">
                                        <input type="radio" name="payment_type" value="full" class="peer sr-only" {{ old('payment_type', 'full') == 'full' ? 'checked' : '' }} required>
                                        <div class="h-full p-6 bg-slate-50 border-2 border-slate-50 rounded-[2rem] transition-all peer-checked:border-[#1D4069] peer-checked:bg-white peer-checked:shadow-xl">
                                            <div class="flex items-center justify-between mb-4">
                                                <div class="w-12 h-12 bg-[#1D4069]/10 rounded-2xl flex items-center justify-center text-[#1D4069]">
                                                    <i class="fas fa-money-bill-wave text-xl"></i>
                                                </div>
                                            </div>
                                            <h4 class="text-lg font-black text-gray-900 uppercase tracking-tight">Full Amount</h4>
                                            <p class="text-2xl font-black text-[#F07F22] mt-1">M{{ number_format($selectedTier->price) }}</p>
                                            <div class="mt-4 pt-4 border-t border-gray-100 space-y-2">
                                                <p class="text-[10px] font-bold text-gray-500 uppercase"><i class="fas fa-check text-emerald-500 mr-2"></i>Instant Activation</p>
                                                <p class="text-[10px] font-bold text-gray-500 uppercase"><i class="fas fa-check text-emerald-500 mr-2"></i>Full Access</p>
                                            </div>
                                        </div>
                                    </label>

                                    <label class="relative cursor-pointer group">
                                        <input type="radio" name="payment_type" value="deposit" class="peer sr-only" {{ old('payment_type') == 'deposit' ? 'checked' : '' }}>
                                        <div class="h-full p-6 bg-slate-50 border-2 border-slate-50 rounded-[2rem] transition-all peer-checked:border-emerald-600 peer-checked:bg-white peer-checked:shadow-xl">
                                            <div class="flex items-center justify-between mb-4">
                                                <div class="w-12 h-12 bg-emerald-100 rounded-2xl flex items-center justify-center text-emerald-600">
                                                    <i class="fas fa-calendar-check text-xl"></i>
                                                </div>
                                            </div>
                                            <h4 class="text-lg font-black text-gray-900 uppercase tracking-tight">Installments</h4>
                                            <p class="text-2xl font-black text-emerald-600 mt-1">M{{ number_format($selectedTier->price * ($event->minimum_deposit_percentage / 100)) }}+</p>
                                            <div class="mt-4 pt-4 border-t border-gray-100 space-y-2">
                                                <p class="text-[10px] font-bold text-gray-500 uppercase"><i class="fas fa-percent text-emerald-500 mr-2"></i>{{ number_format($event->minimum_deposit_percentage, 0) }}% Min Deposit</p>
                                                <p class="text-[10px] font-bold text-gray-500 uppercase"><i class="fas fa-calendar-alt text-emerald-500 mr-2"></i>Flexible Schedule</p>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            {{-- Deposit Amount Input --}}
                            <div id="deposit-amount-section" class="hidden">
                                <div class="bg-emerald-50 border border-emerald-100 rounded-[2rem] p-8">
                                    <label class="block text-[10px] font-black text-emerald-800 uppercase tracking-[0.2em] mb-4 text-center">Initial Payment Amount</label>
                                    
                                    <div class="relative max-w-xs mx-auto">
                                        <span class="absolute left-6 top-1/2 -translate-y-1/2 text-emerald-400 font-black">M</span>
                                        <input type="number" name="deposit_amount" id="deposit_amount" step="0.01"
                                            min="{{ $selectedTier->price * ($event->minimum_deposit_percentage / 100) }}"
                                            max="{{ $selectedTier->price }}"
                                            value="{{ old('deposit_amount', $selectedTier->price * ($event->minimum_deposit_percentage / 100)) }}"
                                            class="w-full pl-12 pr-6 py-5 bg-white border-2 border-emerald-200 rounded-2xl focus:border-emerald-500 outline-none text-2xl font-black text-emerald-900 shadow-inner">
                                    </div>

                                    <div class="flex flex-wrap justify-center gap-2 mt-6">
                                        @php
                                            $minDeposit = $selectedTier->price * ($event->minimum_deposit_percentage / 100);
                                            $halfAmount = $selectedTier->price / 2;
                                            $fullAmount = $selectedTier->price;
                                        @endphp
                                        <button type="button" onclick="setDepositAmount({{ $minDeposit }})" class="text-[10px] font-black uppercase tracking-widest px-4 py-2 bg-white text-emerald-700 border border-emerald-200 rounded-full hover:bg-emerald-600 hover:text-white transition-all">Min</button>
                                        <button type="button" onclick="setDepositAmount({{ $halfAmount }})" class="text-[10px] font-black uppercase tracking-widest px-4 py-2 bg-white text-emerald-700 border border-emerald-200 rounded-full hover:bg-emerald-600 hover:text-white transition-all">Half</button>
                                        <button type="button" onclick="setDepositAmount({{ $fullAmount }})" class="text-[10px] font-black uppercase tracking-widest px-4 py-2 bg-white text-emerald-700 border border-emerald-200 rounded-full hover:bg-emerald-600 hover:text-white transition-all">Full</button>
                                    </div>
                                </div>
                            </div>
                            @else
                                <input type="hidden" name="payment_type" value="full">
                            @endif

                            {{-- Payment Methods --}}
                            <div class="space-y-4">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Payment Provider</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    @foreach($paymentMethods as $method)
                                        @php
                                            $config = config('constants.payment_methods.' . $method->payment_method, []);
                                            $icon = $config['icon'] ?? 'fa-money-bill';
                                            $color = $config['color'] ?? 'text-gray-600';
                                            $label = $config['label'] ?? ucfirst($method->payment_method);
                                        @endphp

                                        <label class="relative cursor-pointer group">
                                            <input type="radio" name="payment_method_id" value="{{ $method->id }}" class="peer sr-only"
                                                data-instructions="{{ $method->instructions }}"
                                                data-is-cash="{{ $method->payment_method === 'cash' ? 'true' : 'false' }}"
                                                {{ old('payment_method_id') == $method->id ? 'checked' : '' }} required>
                                            
                                            <div class="p-4 border-2 border-slate-50 bg-slate-50 rounded-2xl transition-all peer-checked:border-[#F07F22] peer-checked:bg-white peer-checked:shadow-lg h-full flex flex-col">
                                                <div class="flex items-center mb-3">
                                                    <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center mr-3 shadow-sm {{ $color }}">
                                                        <i class="fas {{ $icon }} text-lg"></i>
                                                    </div>
                                                    <span class="text-xs font-black text-gray-900 uppercase tracking-tight truncate">{{ $label }}</span>
                                                </div>

                                                @if($method->payment_method !== 'cash' && $method->account_number)
                                                    <div class="mt-auto bg-gray-50 rounded-lg p-2 border border-gray-100">
                                                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-tighter mb-1">{{ $config['account_label'] ?? 'Send to' }}</p>
                                                        <p class="text-[11px] font-mono font-bold text-gray-900 break-all leading-none">{{ $method->account_number }}</p>
                                                    </div>
                                                @else
                                                    <div class="mt-auto py-2">
                                                        <p class="text-[10px] font-bold text-gray-400 uppercase text-center italic tracking-wider">Pay in person</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Payment Instructions --}}
                            <div id="payment-instructions" class="hidden bg-[#1D4069] border border-[#1D4069] rounded-2xl p-5">
                                <div class="flex items-start">
                                    <i class="fas fa-info-circle text-white text-lg mr-4 mt-0.5"></i>
                                    <div class="flex-1">
                                        <p class="text-[10px] font-black text-blue-200 uppercase tracking-[0.2em] mb-1">Payment Instructions</p>
                                        <p class="text-sm font-bold text-white leading-relaxed" id="instruction-text"></p>
                                    </div>
                                </div>
                            </div>

                            {{-- Payment Reference --}}
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Reference <span class="lowercase text-gray-300">(optional)</span></label>
                                <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" placeholder="Enter transaction reference"
                                    class="w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] transition-all outline-none font-bold text-gray-900">
                            </div>

                        @else
                            <div class="bg-amber-50 border-2 border-amber-100 rounded-3xl p-6 text-center">
                                <i class="fas fa-exclamation-triangle text-amber-500 mb-2"></i>
                                <h4 class="text-[10px] font-black text-amber-900 uppercase tracking-widest leading-none">Payments Not Configured</h4>
                            </div>
                        @endif
                    </div>
                    @else
                        <input type="hidden" name="payment_method_id" value="">
                        <input type="hidden" name="payment_type" value="full">
                    @endif

                    {{-- Free Ticket Banner --}}
                    @if($selectedTier && $selectedTier->price == 0)
                    <div class="animate-in fade-in zoom-in duration-500">
                        <div class="bg-gradient-to-br from-emerald-50 to-teal-50 border-2 border-emerald-100 rounded-[2rem] p-8 text-center shadow-sm">
                            <div class="text-4xl mb-4">🎉</div>
                            <h3 class="text-xl font-black text-emerald-900 mb-2 uppercase tracking-tight">This is a Free Ticket!</h3>
                            <p class="text-sm font-bold text-emerald-700 uppercase tracking-widest opacity-80">No payment required. Just complete your registration.</p>
                        </div>
                    </div>
                    @endif

                    {{-- Terms --}}
                    <div class="pt-10 border-t border-gray-50 space-y-6">
                        <label class="flex items-start cursor-pointer group">
                            <input type="checkbox" name="terms" class="mt-1 w-5 h-5 text-[#F07F22] border-gray-300 rounded" required>
                            <span class="ml-4 text-[11px] font-bold text-gray-500 uppercase tracking-wide">I agree to the terms and payment protocols.</span>
                        </label>

                        <button type="submit" class="hidden lg:block w-full py-6 bg-[#F07F22] hover:bg-[#1D4069] text-white rounded-2xl font-black text-xs uppercase tracking-[0.4em] shadow-xl active:scale-[0.98] transition-all">
                            Complete Registration
                        </button>
                    </div>
                </form>
            </div>

            {{-- Desktop Summary Sidebar --}}
            <div class="lg:col-span-5 hidden lg:block sticky top-20">
                <div class="bg-gray-900 rounded-ventiq shadow-2xl overflow-hidden">
                    <div class="p-8 text-white border-b border-white/5">
                        <span class="text-[9px] font-black uppercase tracking-[0.4em] text-[#F07F22]">Confirmed Selection</span>
                        <h2 class="text-2xl font-black tracking-tighter uppercase italic mt-1">{{ $selectedTier->tier_name }}</h2>
                    </div>
                    <div class="p-8 space-y-8">
                        <div class="flex justify-between items-end pb-6 border-b border-white/5">
                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Investment</span>
                            <span class="text-3xl font-black tracking-tighter text-white">M{{ number_format($selectedTier->price ?? 0) }}</span>
                        </div>
                        <div class="space-y-4 text-white/60">
                            <div class="flex items-center gap-3"><i class="far fa-calendar-alt text-[#F07F22] text-xs"></i><span class="text-[10px] font-black uppercase tracking-widest">{{ $event->event_date->format('d M, Y') }}</span></div>
                            <div class="flex items-center gap-3"><i class="fas fa-map-marker-alt text-[#F07F22] text-xs"></i><span class="text-[10px] font-black uppercase tracking-widest truncate">{{ $event->venue }}</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Mobile Sticky CTA --}}
    <div class="lg:hidden sticky-mobile-price bg-white border-t border-gray-100 px-6 py-5 shadow-[0_-15px_40px_rgba(0,0,0,0.08)]">
        <div class="flex items-center justify-between gap-6">
            <div class="flex flex-col">
                <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest leading-none mb-1">Payable</span>
                <span class="text-3xl font-black text-gray-900 tracking-tighter leading-none">M{{ number_format($selectedTier->price) }}</span>
            </div>
            <button onclick="document.getElementById('regForm').submit()" class="flex-1 py-5 bg-[#F07F22] hover:bg-[#1D4069] text-white rounded-2xl font-black text-[11px] uppercase tracking-[0.3em] active:scale-95 shadow-lg transition-all">
                Register
            </button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const phoneInput = document.getElementById('phone_input');
        const phoneDisplays = document.querySelectorAll('#phone-display-confirm');
        
        // WhatsApp confirmation toggle
        window.toggleWhatsAppConfirmation = function() {
            const checkbox = document.getElementById('has_whatsapp_checkbox');
            const confirmation = document.getElementById('whatsapp-confirmation');
            const hiddenInput = document.getElementById('preferred_delivery_input');
            
            if (checkbox && checkbox.checked) {
                confirmation?.classList.remove('hidden');
                if (hiddenInput) hiddenInput.value = 'both';
            } else {
                confirmation?.classList.add('hidden');
                if (hiddenInput) hiddenInput.value = 'email';
            }
        };
        toggleWhatsAppConfirmation();

        // Update phone display dynamically
        phoneInput?.addEventListener('input', function(e) {
            phoneDisplays.forEach(display => {
                const value = e.target.value.trim();
                display.textContent = value ? value : '';
            });
        });

        // Auto-format main phone
        phoneInput?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 8) value = value.substring(0, 8);
            if (value.length > 4) value = value.substring(0, 4) + ' ' + value.substring(4);
            e.target.value = value;
        });

        // Auto-format companion phones
        document.querySelectorAll('.companion-phone').forEach(input => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 8) value = value.substring(0, 8);
                if (value.length > 4) value = value.substring(0, 4) + ' ' + value.substring(4);
                e.target.value = value;
            });
        });

        // Form submit logic
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const emailInput = document.querySelector('input[name="email"]');
            if (emailInput && !emailInput.value.trim()) {
                emailInput.removeAttribute('name');
            }
            
            if (phoneInput) {
                let cleanPhone = phoneInput.value.replace(/\D/g, '');
                phoneInput.value = '+266' + cleanPhone;
            }

            // Format companion phones
            document.querySelectorAll('.companion-phone').forEach(input => {
                if (input.value.trim()) {
                    let cleanPhone = input.value.replace(/\D/g, '');
                    input.value = '+266' + cleanPhone;
                }
            });
        });

        // Payment type toggle (full vs deposit)
        const paymentTypeRadios = document.querySelectorAll('input[name="payment_type"]');
        const depositSection = document.getElementById('deposit-amount-section');
        const depositInput = document.getElementById('deposit_amount');

        function updateDepositSection() {
            const selectedType = document.querySelector('input[name="payment_type"]:checked')?.value;
            if (selectedType === 'deposit') {
                depositSection?.classList.remove('hidden');
                if (depositInput) depositInput.required = true;
            } else {
                depositSection?.classList.add('hidden');
                if (depositInput) depositInput.required = false;
            }
        }

        paymentTypeRadios.forEach(radio => {
            radio.addEventListener('change', updateDepositSection);
        });
        updateDepositSection();

        // Payment method instructions
        const methodRadios = document.querySelectorAll('input[name="payment_method_id"]');
        const instructionsBox = document.getElementById('payment-instructions');
        const instructionText = document.getElementById('instruction-text');

        methodRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                const instructions = this.dataset.instructions;
                const isCash = this.dataset.isCash === 'true';

                if (instructions && instructions !== 'null') {
                    instructionText.textContent = instructions;
                    instructionsBox.classList.remove('hidden');
                } else if (isCash) {
                    instructionText.textContent = 'Pay in person at the venue or designated location. Your ticket will be activated upon payment confirmation.';
                    instructionsBox.classList.remove('hidden');
                } else {
                    instructionsBox.classList.add('hidden');
                }
            });
        });

        const checkedMethod = document.querySelector('input[name="payment_method_id"]:checked');
        if (checkedMethod) {
            checkedMethod.dispatchEvent(new Event('change'));
        }

        // Deposit amount helper
        window.setDepositAmount = function(amount) {
            const input = document.getElementById('deposit_amount');
            if (input) input.value = amount.toFixed(2);
        };
    });
    </script>
</body>
</html>