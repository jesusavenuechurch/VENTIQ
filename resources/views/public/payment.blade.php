<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Payment | {{ $event->name }} - {{ $organization->name }}</title>
    @vite('resources/css/app.css')
    <style>
        body { font-family: 'Inter', sans-serif; }
        .rounded-ventiq { border-radius: 2.5rem; }
        @keyframes protocol-pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.4); opacity: 0.3; }
            100% { transform: scale(1); opacity: 1; }
        }
        .status-pulse { animation: protocol-pulse 2s infinite ease-in-out; }
        .accordion-panel { max-height: 0; overflow: hidden; transition: max-height 0.35s ease; }
        .accordion-panel.open { max-height: 3000px; }
    </style>
</head>
<body class="bg-[#FBFBFC] text-[#1D4069] antialiased">

    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-gray-100">
        <div class="max-w-3xl mx-auto px-6 h-14 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ url('/') }}" class="text-xl font-black tracking-tighter hover:text-[#F07F22]">V.</a>
                <div class="h-4 w-[1px] bg-gray-200"></div>
                <span class="text-[9px] font-bold uppercase tracking-widest text-gray-400 truncate max-w-[160px]">{{ $organization->name }}</span>
            </div>
            <span class="text-[9px] font-black text-[#F07F22] uppercase tracking-[0.2em]">Payment</span>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 lg:px-6 py-8 pb-20">

        {{-- ── SUMMARY CARD ───────────────────────────────────────── --}}
        <div class="bg-gray-900 rounded-ventiq shadow-2xl overflow-hidden mb-8">
            <div class="p-8 text-white flex items-center justify-between">
                <div>
                    <span class="text-[9px] font-black uppercase tracking-[0.4em] text-[#F07F22]">Amount Due</span>
                    <h2 class="text-4xl font-black tracking-tighter mt-1">M{{ number_format($owed, 2) }}</h2>
                    @if($owed < (float) $ticket->amount)
                        <p class="text-[11px] font-bold text-white/70 mt-1">Balance left of M{{ number_format($ticket->amount, 2) }}</p>
                    @endif
                    <p class="text-[10px] font-bold text-white/50 uppercase tracking-widest mt-2">{{ $event->name }} &middot; {{ $ticket->tier->tier_name }}</p>
                    @if($ticket->payment_due_at)
                        <p class="text-[11px] font-bold text-[#F07F22] mt-2"><i class="fas fa-hourglass-half mr-1"></i>Your place is held until {{ $ticket->payment_due_at->format('j M, H:i') }}</p>
                    @endif
                </div>
                <div class="text-right">
                    <span class="text-[9px] font-black text-white/40 uppercase tracking-widest">Ref</span>
                    <p class="text-sm font-mono font-black">{{ $ticket->ticket_number }}</p>
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="mb-6 p-5 bg-mint text-mint-ink rounded-3xl text-[13px] font-bold"><i class="fas fa-hand mr-1"></i>{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="mb-8 p-6 bg-rose-50 border-2 border-rose-100 rounded-3xl">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li class="text-xs font-bold text-rose-600">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ── ONLINE PAYMENT (DEFAULT) ───────────────────────────── --}}
        @if($onlineEnabled)
        <div class="bg-white rounded-ventiq shadow-2xl shadow-gray-200/50 overflow-hidden border border-gray-100 mb-6">
            <div class="p-8 sm:p-10 border-b border-gray-50">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 bg-[#F07F22]/10 rounded-xl flex items-center justify-center text-[#F07F22]">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h2 class="text-2xl font-black text-gray-900 tracking-tighter uppercase italic leading-none">Pay Online</h2>
                </div>
                <p class="text-gray-500 font-medium text-sm">Payment processed securely through VENTIQ. Your ticket activates as soon as the payment goes through.</p>
            </div>

            <div class="p-8 sm:p-10">

                {{-- Provider + mobile number form --}}
                <div id="online-form-panel">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-1">Choose Provider</label>
                            <div class="grid grid-cols-2 gap-4">
                                @if(in_array('mpesa', $onlineMethods))
                                <button type="button" id="provider-mpesa" data-method="mpesa"
                                    class="provider-btn p-5 rounded-2xl border-2 transition-all flex flex-col items-center gap-2 {{ $onlineMethods[0] === 'mpesa' ? 'border-[#F07F22] bg-[#F07F22]/5' : 'border-slate-100 bg-slate-50' }}">
                                    <i class="fas fa-mobile-alt text-xl text-red-600"></i>
                                    <span class="text-xs font-black uppercase tracking-tight text-gray-900">M-Pesa</span>
                                </button>
                                @endif
                                @if(in_array('ecocash', $onlineMethods))
                                <button type="button" id="provider-ecocash" data-method="ecocash"
                                    class="provider-btn p-5 rounded-2xl border-2 transition-all flex flex-col items-center gap-2 {{ $onlineMethods[0] === 'ecocash' ? 'border-[#F07F22] bg-[#F07F22]/5' : 'border-slate-100 bg-slate-50' }}">
                                    <i class="fas fa-mobile-alt text-xl text-blue-600"></i>
                                    <span class="text-xs font-black uppercase tracking-tight text-gray-900">EcoCash</span>
                                </button>
                                @endif
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">
                                Mobile Number <span class="text-rose-500">*</span>
                            </label>
                            <div class="flex">
                                <span class="inline-flex items-center px-4 bg-slate-100 border-2 border-r-0 border-slate-100 rounded-l-2xl font-black text-gray-400 text-xs">+266</span>
                                @php $regDigits = preg_replace('/^266/', '', preg_replace('/\D/', '', (string) $ticket->client?->phone)); @endphp
                                <input type="tel" id="online_phone_input" value="{{ strlen($regDigits) === 8 ? substr($regDigits, 0, 4) . ' ' . substr($regDigits, 4) : '' }}"
                                    class="flex-1 bg-slate-50 border-2 border-slate-50 rounded-r-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] transition-all outline-none font-bold text-gray-900"
                                    placeholder="5949 4756" inputmode="tel" autocomplete="tel-national">
                            </div>
                            <p id="online-error" class="hidden text-[10px] font-bold text-rose-500 uppercase mt-2 ml-1"></p>
                        </div>

                        <button type="button" id="send-payment-request"
                            class="w-full py-6 bg-[#F07F22] hover:bg-[#1D4069] text-white rounded-2xl font-black text-xs uppercase tracking-[0.4em] shadow-xl active:scale-[0.98] transition-all">
                            Send Payment Request
                        </button>
                        <p id="attempts-note" class="text-center text-[11px] font-bold text-gray-400 {{ $attemptsLeft < config('gateways.paylesotho.max_attempts', 3) ? '' : 'hidden' }}">
                            <span id="attempts-left">{{ $attemptsLeft }}</span> of {{ config('gateways.paylesotho.max_attempts', 3) }} tries left
                        </p>
                    </div>
                </div>

                {{-- Waiting state --}}
                <div id="online-waiting-panel" class="hidden text-center py-6">
                    <div class="relative flex items-center justify-center mx-auto mb-6" style="width: 80px; height: 80px;">
                        <span class="status-pulse absolute inline-flex h-full w-full rounded-full bg-[#F07F22] opacity-20"></span>
                        <div class="relative w-16 h-16 bg-[#F07F22] rounded-2xl flex items-center justify-center shadow-xl">
                            <i class="fas fa-mobile-alt text-white text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 uppercase tracking-tight mb-2">Check Your Phone</h3>
                    <p class="text-sm font-bold text-gray-500 mb-1">A payment prompt was sent to</p>
                    <p class="text-lg font-black text-[#1D4069] mb-6" id="waiting-masked-number"></p>
                    <p class="text-[13px] font-bold text-gray-600 mb-4">Enter your EcoCash PIN on your phone to approve the payment.</p>
                    <div class="max-w-xs mx-auto">
                        <div class="h-2 rounded-full bg-slate-100 overflow-hidden"><div id="waiting-bar" class="h-full bg-[#F07F22] transition-all duration-1000" style="width:100%"></div></div>
                        <p class="mt-2 text-[11px] font-black text-gray-400 tabular-nums" id="waiting-countdown">1:30</p>
                    </div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-2" id="waiting-status-text">Please keep this page open</p>
                </div>

                {{-- The wait ran out without an answer --}}
                <div id="online-pin-panel" class="hidden text-center py-6">
                    <div class="w-16 h-16 bg-amber-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-mobile-screen text-amber-500 text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 uppercase tracking-tight mb-2">Did you enter your PIN?</h3>
                    <p class="text-sm font-bold text-gray-500 mb-6">We haven't heard back from EcoCash yet.</p>
                    <div class="space-y-3">
                        <button type="button" id="pin-yes" class="w-full py-5 bg-slate-900 hover:bg-[#1D4069] text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all">Yes, I approved it</button>
                        <button type="button" id="pin-no" class="w-full py-5 bg-[#F07F22] hover:bg-[#1D4069] text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all">No, send it again</button>
                    </div>
                </div>

                {{-- Timed-out state --}}
                <div id="online-timeout-panel" class="hidden text-center py-6">
                    <div class="w-16 h-16 bg-amber-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-clock text-amber-500 text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 uppercase tracking-tight mb-2">We're confirming it</h3>
                    <p class="text-sm font-bold text-gray-500 mb-6">If you entered your PIN and approved the payment, you're done. Please don't pay again: we'll confirm it and send your ticket. Your place is held meanwhile.</p>
                    <a href="{{ route('ticket.download', $ticket->qr_code) }}"
                        class="block w-full py-5 bg-slate-900 hover:bg-[#1D4069] text-white rounded-2xl font-black text-xs uppercase tracking-[0.3em] transition-all">
                        View my ticket
                    </a>
                    <button type="button" id="timeout-retry" class="mt-4 text-[11px] font-bold text-gray-400 hover:text-[#1D4069]">I didn't pay, try again</button>
                </div>

                {{-- Failed state --}}
                <div id="online-failed-panel" class="hidden text-center py-6">
                    <div class="w-16 h-16 bg-rose-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-times text-rose-500 text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 uppercase tracking-tight mb-2">Payment Failed</h3>
                    <p class="text-sm font-bold text-gray-500 mb-6" id="failed-reason-text">The payment wasn't completed. You can try again.</p>
                    <button type="button" id="try-again-btn"
                        class="w-full py-5 bg-[#F07F22] hover:bg-[#1D4069] text-white rounded-2xl font-black text-xs uppercase tracking-[0.3em] transition-all">
                        Try Again
                    </button>
                </div>

                {{-- Out of tries: pay another way --}}
                <div id="online-fallback-panel" class="hidden py-2">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-route text-[#1D4069] text-2xl"></i>
                        </div>
                        <h3 class="text-2xl font-black text-gray-900 uppercase tracking-tight mb-2">Let's try another way</h3>
                        <p class="text-sm font-bold text-gray-500">
                            <span id="fallback-some-left" class="{{ $attemptsLeft > 0 ? '' : 'hidden' }}">The payment prompt didn't go through. Pay directly instead and send us the proof.</span>
                            <span id="fallback-none-left" class="{{ $attemptsLeft > 0 ? 'hidden' : '' }}">The payment prompt didn't work after {{ config('gateways.paylesotho.max_attempts', 3) }} tries.</span>
                            Your place is still held.
                        </p>
                    </div>

                    @if($paymentMethods->isNotEmpty())
                        <button type="button" id="fallback-direct" class="w-full py-5 bg-slate-900 hover:bg-[#1D4069] text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all">
                            Pay the organizer directly
                        </button>
                    @elseif($merchant)
                        <div class="rounded-2xl bg-slate-50 border border-slate-100 p-5 mb-5 text-[13px] font-bold text-gray-700 space-y-2">
                            <p>On your phone, pay with EcoCash:</p>
                            <ol class="list-decimal list-inside space-y-1 text-gray-600">
                                <li>Open the EcoCash menu and choose <strong>Pay merchant</strong></li>
                                <li>Merchant code <span class="font-mono text-[#1D4069] text-[15px]">{{ $merchant['code'] }}</span> ({{ $merchant['name'] }})</li>
                                <li>Amount <span class="font-mono text-[#1D4069]">M{{ number_format($owed, 2) }}</span></li>
                            </ol>
                            <p class="text-gray-500 font-medium">Then tell us below, so we can match it and send your ticket.</p>
                        </div>
                        <form method="POST" action="{{ route('ticket.pay.merchant', $ticket->qr_code) }}" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <div>
                                <label for="merchant_phone" class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Number you paid from</label>
                                <input id="merchant_phone" name="merchant_phone" type="tel" required value="{{ old('merchant_phone') }}" placeholder="5949 4756"
                                    class="w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] outline-none font-bold text-gray-900">
                            </div>
                            <div>
                                <label for="merchant_reference" class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Reference from the EcoCash message</label>
                                <input id="merchant_reference" name="merchant_reference" value="{{ old('merchant_reference') }}" placeholder="e.g. MP240101.1234.A12345"
                                    class="w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] outline-none font-bold text-gray-900">
                            </div>
                            <div>
                                <label for="merchant_proof" class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Or a screenshot of it</label>
                                <input id="merchant_proof" name="proof" type="file" accept="image/*,application/pdf" class="w-full text-[12px] text-gray-500">
                            </div>
                            <button class="w-full py-5 bg-[#F07F22] hover:bg-[#1D4069] text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all">I've paid</button>
                        </form>
                    @else
                        <p class="text-center text-[13px] font-bold text-gray-500">Please contact {{ $organization->name }} to pay for your ticket.</p>
                    @endif

                    @if($attemptsLeft > 0)
                        <button type="button" id="fallback-retry" class="mt-4 w-full py-3 text-[11px] font-black uppercase tracking-widest text-gray-400 hover:text-[#1D4069]">
                            Or try the payment prompt again
                        </button>
                    @endif
                </div>

                {{-- Paid the merchant by hand, waiting for VENTIQ --}}
                <div id="online-byhand-panel" class="hidden text-center py-6">
                    <div class="w-16 h-16 bg-mint rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-magnifying-glass-dollar text-mint-ink text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 uppercase tracking-tight mb-2">We're checking your payment</h3>
                    <p class="text-sm font-bold text-gray-500">Your ticket is sent as soon as we've matched it, usually within a few hours. No need to pay again.</p>
                </div>

            </div>
        </div>
        @endif

        {{-- ── OR PAY ANOTHER WAY (COLLAPSED — expanded by default when online isn't offered) ── --}}
        <div class="bg-white rounded-ventiq shadow-lg shadow-gray-200/40 overflow-hidden border border-gray-100">
            @if($onlineEnabled)
                <button type="button" id="manual-toggle" class="w-full flex items-center justify-between p-6 sm:p-8 text-left">
                    <span>
                        <span class="block text-xs font-black text-gray-500 uppercase tracking-widest">Or pay directly to the organizer</span>
                        <span class="block text-[11px] font-medium text-gray-400 mt-1 normal-case">Payment is made directly to the event organizer, who confirms it before your ticket activates.</span>
                    </span>
                    <i class="fas fa-chevron-down text-gray-400 text-sm transition-transform" id="manual-toggle-icon"></i>
                </button>
            @else
                <div class="p-6 sm:p-8 pb-0">
                    <span class="block text-xs font-black text-gray-500 uppercase tracking-widest">Pay directly to the organizer</span>
                    <span class="block text-[11px] font-medium text-gray-400 mt-1">Payment is made directly to the event organizer, who confirms it before your ticket activates.</span>
                </div>
            @endif

            <div id="manual-panel" class="accordion-panel {{ !$onlineEnabled || $errors->hasAny(['payment_method_id', 'payment_reference', 'proof', 'deposit_amount']) ? 'open' : '' }}">
                <form method="POST" action="{{ route('ticket.pay.manual', $ticket->qr_code) }}" enctype="multipart/form-data" class="p-6 sm:p-8 pt-0 space-y-6">
                    @csrf

                    @if($paymentMethods->isNotEmpty())

                        {{-- Payment Plan (Full vs Installments) --}}
                        @if($event->allow_installments && (float) $ticket->amount_paid <= 0)
                        <div class="space-y-4">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Payment Plan</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="relative cursor-pointer group">
                                    <input type="radio" name="payment_type" value="full" class="peer sr-only" checked required>
                                    <div class="h-full p-6 bg-slate-50 border-2 border-slate-50 rounded-[2rem] transition-all peer-checked:border-[#1D4069] peer-checked:bg-white peer-checked:shadow-xl">
                                        <h4 class="text-lg font-black text-gray-900 uppercase tracking-tight">Full Amount</h4>
                                        <p class="text-2xl font-black text-[#F07F22] mt-1">M{{ number_format($owed) }}</p>
                                    </div>
                                </label>
                                <label class="relative cursor-pointer group">
                                    <input type="radio" name="payment_type" value="deposit" class="peer sr-only">
                                    <div class="h-full p-6 bg-slate-50 border-2 border-slate-50 rounded-[2rem] transition-all peer-checked:border-emerald-600 peer-checked:bg-white peer-checked:shadow-xl">
                                        <h4 class="text-lg font-black text-gray-900 uppercase tracking-tight">Installments</h4>
                                        <p class="text-2xl font-black text-emerald-600 mt-1">M{{ number_format($ticket->amount * ($event->minimum_deposit_percentage / 100)) }}+</p>
                                        <p class="text-[10px] font-bold text-gray-500 uppercase mt-2">{{ number_format($event->minimum_deposit_percentage, 0) }}% Min Deposit</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div id="deposit-amount-section" class="hidden">
                            <div class="bg-emerald-50 border border-emerald-100 rounded-[2rem] p-8">
                                <label class="block text-[10px] font-black text-emerald-800 uppercase tracking-[0.2em] mb-4 text-center">Initial Payment Amount</label>
                                <div class="relative max-w-xs mx-auto">
                                    <span class="absolute left-6 top-1/2 -translate-y-1/2 text-emerald-400 font-black">M</span>
                                    <input type="number" name="deposit_amount" id="deposit_amount" step="0.01"
                                        min="{{ $ticket->amount * ($event->minimum_deposit_percentage / 100) }}"
                                        max="{{ $ticket->amount }}"
                                        value="{{ old('deposit_amount', $ticket->amount * ($event->minimum_deposit_percentage / 100)) }}"
                                        class="w-full pl-12 pr-6 py-5 bg-white border-2 border-emerald-200 rounded-2xl focus:border-emerald-500 outline-none text-2xl font-black text-emerald-900 shadow-inner">
                                </div>
                                @php
                                    $minDeposit = $ticket->amount * ($event->minimum_deposit_percentage / 100);
                                    $halfAmount = $ticket->amount / 2;
                                    $fullAmount = $ticket->amount;
                                @endphp
                                <div class="flex flex-wrap justify-center gap-2 mt-6">
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
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Payment Method</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($paymentMethods as $method)
                                @php
                                    $config = config('constants.payment_methods.' . $method->payment_method, []);
                                    $icon = $config['icon'] ?? 'fa-money-bill';
                                    $color = $config['color'] ?? 'text-gray-600';
                                    $label = $method->display_label;
                                @endphp
                                <label class="relative cursor-pointer group">
                                    <input type="radio" name="payment_method_id" value="{{ $method->id }}" class="peer sr-only"
                                        data-instructions="{{ $method->instructions }}"
                                        data-is-cash="{{ $method->payment_method === 'cash' ? 'true' : 'false' }}"
                                        {{ (int) old('payment_method_id', $paymentMethods->first()?->id) === $method->id ? 'checked' : '' }} required>

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
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Reference <span class="lowercase text-gray-300">(not needed for cash)</span></label>
                            <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" placeholder="Enter transaction reference"
                                class="w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] transition-all outline-none font-bold text-gray-900">
                        </div>

                        <div>
                            <label for="manual_proof" class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Screenshot of the payment <span class="lowercase text-gray-300">(optional, or instead of the reference)</span></label>
                            <input type="file" id="manual_proof" name="proof" accept="image/*,application/pdf" class="w-full text-[12px] text-gray-500">
                            @error('proof')<p class="text-[10px] font-bold text-rose-500 mt-2 ml-1">{{ $message }}</p>@enderror
                            @error('payment_reference')<p class="text-[10px] font-bold text-rose-500 mt-2 ml-1">{{ $message }}</p>@enderror
                        </div>

                        <button type="submit" class="w-full py-5 bg-slate-900 hover:bg-[#1D4069] text-white rounded-2xl font-black text-[11px] uppercase tracking-[0.3em] active:scale-[0.98] transition-all">
                            Confirm Payment Details
                        </button>

                    @else
                        <div class="bg-amber-50 border-2 border-amber-100 rounded-3xl p-6 text-center">
                            <i class="fas fa-exclamation-triangle text-amber-500 mb-2"></i>
                            <h4 class="text-[10px] font-black text-amber-900 uppercase tracking-widest leading-none">No Other Payment Methods Configured</h4>
                        </div>
                    @endif
                </form>
            </div>
        </div>

        @include('tickets.partials.save-link', ['ticket' => $ticket, 'class' => 'mt-6'])
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // ── Manual accordion toggle ──────────────────────────────
        const manualToggle = document.getElementById('manual-toggle');
        const manualPanel = document.getElementById('manual-panel');
        const manualToggleIcon = document.getElementById('manual-toggle-icon');
        manualToggle?.addEventListener('click', function() {
            manualPanel.classList.toggle('open');
            manualToggleIcon.classList.toggle('rotate-180');
        });

        // ── Manual: payment plan toggle ──────────────────────────
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
        paymentTypeRadios.forEach(radio => radio.addEventListener('change', updateDepositSection));
        updateDepositSection();

        window.setDepositAmount = function(amount) {
            if (depositInput) depositInput.value = amount.toFixed(2);
        };

        // ── Manual: payment method instructions ──────────────────
        const methodRadios = document.querySelectorAll('input[name="payment_method_id"]');
        const instructionsBox = document.getElementById('payment-instructions');
        const instructionText = document.getElementById('instruction-text');

        methodRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                const instructions = this.dataset.instructions;
                const isCash = this.dataset.isCash === 'true';

                if (instructions && instructions !== 'null' && instructions.trim() !== '') {
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
        document.querySelector('input[name="payment_method_id"]:checked')?.dispatchEvent(new Event('change'));

        // ── Online: provider selection ───────────────────────────
        let selectedMethod = @json($onlineMethods[0] ?? null);
        const providerButtons = document.querySelectorAll('.provider-btn');
        providerButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                selectedMethod = this.dataset.method;
                providerButtons.forEach(b => {
                    b.classList.remove('border-[#F07F22]', 'bg-[#F07F22]/5');
                    b.classList.add('border-slate-100', 'bg-slate-50');
                });
                this.classList.remove('border-slate-100', 'bg-slate-50');
                this.classList.add('border-[#F07F22]', 'bg-[#F07F22]/5');
            });
        });

        // ── Online: phone formatting (same pattern as registration form) ──
        const onlinePhoneInput = document.getElementById('online_phone_input');
        onlinePhoneInput?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            // "+266 5949 4756" pasted whole: drop the country code, not the end.
            if (value.length > 8 && value.startsWith('266')) value = value.substring(3);
            if (value.length > 8) value = value.substring(0, 8);
            if (value.length > 4) value = value.substring(0, 4) + ' ' + value.substring(4);
            e.target.value = value;
        });

        // ── Online: panels ────────────────────────────────────────
        // Send → wait up to WAIT_S for EcoCash's answer (PayLesotho holds
        // the request until the PIN is entered) → if no answer, ask "Did you
        // enter your PIN?" → yes: keep checking; no: send again. The server
        // counts the tries; when they run out, the fallback panel shows.
        const panels = {
            form: document.getElementById('online-form-panel'),
            waiting: document.getElementById('online-waiting-panel'),
            pin: document.getElementById('online-pin-panel'),
            timeout: document.getElementById('online-timeout-panel'),
            failed: document.getElementById('online-failed-panel'),
            fallback: document.getElementById('online-fallback-panel'),
            byhand: document.getElementById('online-byhand-panel'),
        };
        const onlineError = document.getElementById('online-error');
        const ticketUrl = @json(route('ticket.download', ['qr_code' => $ticket->qr_code]));
        const statusUrlFor = id => @json(route('ticket.pay.status', ['code' => $ticket->qr_code, 'session' => '__SESSION__'])).replace('__SESSION__', id);
        const MAX_TRIES = {{ (int) config('gateways.paylesotho.max_attempts', 3) }};
        let WAIT_S = {{ (int) config('gateways.paylesotho.page_wait_seconds', 90) }};
        let attemptsLeft = {{ (int) $attemptsLeft }};
        let sessionId = null;
        let deadline = 0;
        let pollTimer = null, tickTimer = null;

        function showPanel(name) {
            Object.values(panels).forEach(p => p?.classList.add('hidden'));
            panels[name]?.classList.remove('hidden');
        }

        function setAttempts(n) {
            if (typeof n !== 'number') return;
            attemptsLeft = n;
            document.getElementById('attempts-left').textContent = n;
            document.getElementById('attempts-note')?.classList.toggle('hidden', n >= MAX_TRIES);
            document.getElementById('fallback-retry')?.classList.toggle('hidden', n === 0);
            document.getElementById('fallback-some-left')?.classList.toggle('hidden', n === 0);
            document.getElementById('fallback-none-left')?.classList.toggle('hidden', n > 0);
        }

        // Out of tries → another way; else back to the form to send again.
        function retryOrFallback() {
            stopAll();
            showPanel(attemptsLeft > 0 ? 'form' : 'fallback');
        }

        function stopAll() {
            clearInterval(pollTimer); clearInterval(tickTimer);
            pollTimer = tickTimer = null;
        }

        function check(onPending) {
            if (!sessionId) return;
            fetch(statusUrlFor(sessionId), { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => {
                    setAttempts(data.attempts_left);
                    if (data.status === 'completed') {
                        stopAll();
                        window.location.href = ticketUrl;
                    } else if (data.status === 'failed') {
                        stopAll();
                        document.getElementById('failed-reason-text').textContent = (data.message || "The payment wasn't completed.")
                            + (attemptsLeft > 0 ? ` You have ${attemptsLeft} ${attemptsLeft === 1 ? 'try' : 'tries'} left.` : '');
                        const again = document.getElementById('try-again-btn');
                        again.textContent = attemptsLeft > 0 ? 'Try again' : 'Pay another way';
                        showPanel('failed');
                    } else if (onPending) {
                        onPending();
                    }
                })
                .catch(() => onPending && onPending());
        }

        function wait(id, secondsLeft) {
            sessionId = id;
            stopAll();
            deadline = Date.now() + secondsLeft * 1000;
            showPanel('waiting');

            const bar = document.getElementById('waiting-bar');
            const clock = document.getElementById('waiting-countdown');
            const tick = () => {
                const left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
                clock.textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');
                bar.style.width = (100 * left / WAIT_S) + '%';
                if (left === 0) {
                    stopAll();
                    // One last look before asking.
                    check(() => showPanel('pin'));
                }
            };
            tick();
            tickTimer = setInterval(tick, 1000);
            pollTimer = setInterval(() => check(), 3000);
        }

        // "Yes, I approved it": keep checking quietly; the answer can still come.
        document.getElementById('pin-yes')?.addEventListener('click', function() {
            showPanel('timeout');
            stopAll();
            let checks = 0;
            pollTimer = setInterval(() => { if (++checks > 40) stopAll(); check(); }, 5000);
        });
        document.getElementById('pin-no')?.addEventListener('click', retryOrFallback);
        document.getElementById('timeout-retry')?.addEventListener('click', retryOrFallback);
        document.getElementById('try-again-btn')?.addEventListener('click', retryOrFallback);

        document.getElementById('fallback-retry')?.addEventListener('click', () => showPanel('form'));

        document.getElementById('fallback-direct')?.addEventListener('click', function() {
            manualPanel?.classList.add('open');
            manualToggleIcon?.classList.add('rotate-180');
            manualPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        document.getElementById('send-payment-request')?.addEventListener('click', function() {
            const digits = (onlinePhoneInput?.value || '').replace(/\D/g, '');

            if (digits.length !== 8) {
                onlineError.textContent = 'Enter a valid 8-digit mobile number.';
                onlineError.classList.remove('hidden');
                return;
            }
            onlineError.classList.add('hidden');

            const button = this;
            button.disabled = true;
            button.textContent = 'Sending…';
            const reset = () => { button.disabled = false; button.textContent = 'Send Payment Request'; };

            fetch(@json(route('ticket.pay.online', $ticket->qr_code)), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ method: selectedMethod, mobile_number: '+266' + digits }),
            })
                .then(res => res.json().then(data => ({ ok: res.ok, data })))
                .then(function({ ok, data }) {
                    reset();
                    setAttempts(data.attempts_left);

                    if (!ok || !data.session_id) {
                        if (data.attempts_left === 0) { showPanel('fallback'); return; }
                        onlineError.textContent = data.message || 'Could not start payment. Please try again.';
                        onlineError.classList.remove('hidden');
                        return;
                    }
                    if (data.wait_seconds) WAIT_S = data.wait_seconds;
                    document.getElementById('waiting-masked-number').textContent = '+266 •••• ' + digits.slice(-4);

                    if (data.status === 'failed') { sessionId = data.session_id; check(); return; }
                    wait(data.session_id, WAIT_S);
                })
                .catch(function() {
                    reset();
                    onlineError.textContent = 'Network error. Please try again.';
                    onlineError.classList.remove('hidden');
                });
        });

        // Where to start after a reload.
        @if($byHand)
            showPanel('byhand');
        @elseif($inFlight)
            document.getElementById('waiting-masked-number').textContent = 'your phone';
            wait({{ $inFlight->id }}, Math.max(5, WAIT_S - {{ (int) $inFlight->created_at->diffInSeconds(now()) }}));
        @elseif($onlineEnabled && ($attemptsLeft === 0 || $anotherWay))
            showPanel('fallback');
        @endif
    });
    </script>
@include('partials.cookie-notice')
</body>
</html>
