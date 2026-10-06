@extends('layouts.attendee')

@section('title', "Pay for your ticket | {$event->name}")

@php
    $card  = 'rounded-[1.5rem] bg-white border border-gray-100 shadow-sm';
    $input = 'w-full bg-white border-2 border-gray-100 rounded-2xl px-4 py-3.5 text-[15px] font-bold text-gray-900 outline-none focus:border-[#F07F22] transition-colors';
    $label = 'block text-[12px] font-black text-gray-600 mb-1.5';
    $primary = 'w-full py-4 rounded-2xl bg-[#F07F22] hover:bg-[#1D4069] text-white text-[12px] font-black uppercase tracking-widest shadow-lg transition-colors';
    $dark = 'w-full py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[12px] font-black uppercase tracking-widest transition-colors';
    $first = \Illuminate\Support\Str::before($ticket->holder_name ?? '', ' ') ?: 'there';
@endphp

@section('content')
{{-- What's owed --}}
<div class="mb-4 p-5 rounded-[1.5rem] bg-[#1D4069] text-white">
    <p class="text-[13px] font-bold text-white/70">Hi {{ $first }}, almost there!</p>
    <div class="mt-2 flex items-end justify-between gap-4">
        <div class="min-w-0">
            <p class="text-[11px] font-bold text-white/60">{{ $owed < (float) $ticket->amount ? 'Balance to pay' : 'To pay' }}</p>
            <p class="text-[32px] font-black leading-none">M{{ number_format($owed, 2) }}</p>
            @if($owed < (float) $ticket->amount)
                <p class="text-[12px] text-white/70 mt-1">of M{{ number_format($ticket->amount, 2) }}</p>
            @endif
        </div>
        <div class="text-right min-w-0">
            <p class="text-[13px] font-black truncate">{{ $ticket->tier->tier_name }}@if(($ticket->admissions ?? 1) > 1) · admits {{ $ticket->admissions }}@endif</p>
            <p class="text-[11px] text-white/60 truncate">{{ $event->name }}</p>
        </div>
    </div>
    @if($ticket->payment_due_at)
        <p class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 text-[12px] font-bold">
            <i class="fas fa-hourglass-half text-[#F07F22]"></i>Your place is held until {{ $ticket->payment_due_at->format('j M, H:i') }}
        </p>
    @endif
</div>

@if (session('status'))
    <div class="mb-4 p-4 rounded-2xl bg-mint text-mint-ink text-[13px] font-bold"><i class="fas fa-hand mr-1"></i>{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div role="alert" class="mb-4 p-4 rounded-2xl bg-rose-50 border border-rose-100">
        <ul class="list-disc list-inside text-[13px] font-bold text-rose-600">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

{{-- ── Online (the default) ── --}}
@if($onlineEnabled)
<section class="{{ $card }} mb-4 overflow-hidden">
    <div class="p-5 sm:p-6 border-b border-gray-50 flex items-start gap-3">
        <span class="w-10 h-10 shrink-0 rounded-xl bg-action-soft text-action-ink flex items-center justify-center"><i class="fas fa-bolt"></i></span>
        <div>
            <h2 class="text-[18px] font-black leading-tight">Pay Online</h2>
            <p class="text-[12px] text-gray-500">Payment processed securely through VENTIQ. Your ticket activates as soon as it goes through.</p>
        </div>
    </div>

    <div class="p-5 sm:p-6">
        {{-- Provider + number --}}
        <div id="online-form-panel" class="space-y-5">
            <div>
                <p class="{{ $label }}">Pay with</p>
                <div class="grid grid-cols-2 gap-3">
                    @if(in_array('ecocash', $onlineMethods))
                        <button type="button" id="provider-ecocash" data-method="ecocash"
                            class="provider-btn p-4 rounded-2xl border-2 flex items-center justify-center gap-2 transition-all {{ $onlineMethods[0] === 'ecocash' ? 'border-[#F07F22] bg-[#F07F22]/5' : 'border-slate-100 bg-slate-50' }}">
                            <i class="fas fa-mobile-screen text-blue-600"></i><span class="text-[13px] font-black text-gray-900">EcoCash</span>
                        </button>
                    @endif
                    @if(in_array('mpesa', $onlineMethods))
                        <button type="button" id="provider-mpesa" data-method="mpesa"
                            class="provider-btn p-4 rounded-2xl border-2 flex items-center justify-center gap-2 transition-all {{ $onlineMethods[0] === 'mpesa' ? 'border-[#F07F22] bg-[#F07F22]/5' : 'border-slate-100 bg-slate-50' }}">
                            <i class="fas fa-mobile-screen text-red-600"></i><span class="text-[13px] font-black text-gray-900">M-Pesa</span>
                        </button>
                    @endif
                </div>
            </div>

            <div>
                <label for="online_phone_input" class="{{ $label }}">Number to pay from <span class="text-rose-500">*</span></label>
                <div class="flex">
                    <span class="inline-flex items-center px-3.5 rounded-l-2xl border-2 border-r-0 border-gray-100 bg-slate-50 text-[13px] font-black text-gray-500">+266</span>
                    @php $regDigits = preg_replace('/^266/', '', preg_replace('/\D/', '', (string) $ticket->client?->phone)); @endphp
                    <input type="tel" id="online_phone_input" value="{{ strlen($regDigits) === 8 ? substr($regDigits, 0, 4) . ' ' . substr($regDigits, 4) : '' }}"
                        class="{{ $input }} rounded-l-none" placeholder="5949 4756" inputmode="tel" autocomplete="tel-national">
                </div>
                <p id="online-error" class="hidden text-[12px] font-bold text-rose-600 mt-2"></p>
                <p class="mt-2 text-[12px] text-gray-500">You'll get a prompt on this phone to enter your PIN.</p>
            </div>

            <button type="button" id="send-payment-request" class="{{ $primary }}">Send payment request</button>
            <p id="attempts-note" class="text-center text-[12px] font-bold text-gray-400 {{ $attemptsLeft < config('gateways.paylesotho.max_attempts', 3) ? '' : 'hidden' }}">
                <span id="attempts-left">{{ $attemptsLeft }}</span> of {{ config('gateways.paylesotho.max_attempts', 3) }} tries left
            </p>
        </div>

        {{-- Waiting for the PIN --}}
        <div id="online-waiting-panel" class="hidden text-center py-4">
            <div class="relative mx-auto mb-5 w-20 h-20 flex items-center justify-center">
                <span class="soft-pulse absolute inset-0 rounded-full bg-[#F07F22]"></span>
                <span class="relative w-16 h-16 rounded-2xl bg-[#F07F22] text-white flex items-center justify-center shadow-xl"><i class="fas fa-mobile-screen text-2xl"></i></span>
            </div>
            <h3 class="text-[22px] font-black">Check your phone</h3>
            <p class="text-[13px] text-gray-500">A payment prompt was sent to</p>
            <p class="text-[17px] font-black mb-3" id="waiting-masked-number"></p>
            <p class="text-[13px] font-bold text-gray-600 mb-4">Enter your PIN to approve the payment.</p>
            <div class="max-w-xs mx-auto">
                <div class="h-2 rounded-full bg-slate-100 overflow-hidden"><div id="waiting-bar" class="h-full bg-[#F07F22] transition-all duration-1000" style="width:100%"></div></div>
                <p class="mt-2 text-[12px] font-black text-gray-400 tabular-nums" id="waiting-countdown">1:30</p>
            </div>
            <p class="text-[12px] text-gray-400 mt-1" id="waiting-status-text">Please keep this page open</p>
        </div>

        {{-- No answer in time --}}
        <div id="online-pin-panel" class="hidden text-center py-4">
            <span class="mx-auto mb-5 w-16 h-16 rounded-2xl bg-action-soft text-action-ink flex items-center justify-center"><i class="fas fa-mobile-screen text-2xl"></i></span>
            <h3 class="text-[22px] font-black">Did you enter your PIN?</h3>
            <p class="text-[13px] text-gray-500 mb-5">We haven't heard back from EcoCash yet.</p>
            <div class="space-y-3">
                <button type="button" id="pin-yes" class="{{ $dark }}">Yes, I approved it</button>
                <button type="button" id="pin-no" class="{{ $primary }}">No, send it again</button>
            </div>
        </div>

        {{-- Approved, still confirming --}}
        <div id="online-timeout-panel" class="hidden text-center py-4">
            <span class="mx-auto mb-5 w-16 h-16 rounded-2xl bg-mint text-mint-ink flex items-center justify-center"><i class="fas fa-clock text-2xl"></i></span>
            <h3 class="text-[22px] font-black">We're confirming it</h3>
            <p class="text-[13px] text-gray-500 mb-5">If you entered your PIN and approved the payment, you're done. Please don't pay again: we'll confirm it and send your ticket. Your place is held meanwhile.</p>
            <a href="{{ route('ticket.download', $ticket->qr_code) }}" class="block {{ $dark }}">View my ticket</a>
            <button type="button" id="timeout-retry" class="mt-4 text-[12px] font-bold text-gray-400 hover:text-[#1D4069]">I didn't pay, try again</button>
        </div>

        {{-- Declined --}}
        <div id="online-failed-panel" class="hidden text-center py-4">
            <span class="mx-auto mb-5 w-16 h-16 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center"><i class="fas fa-xmark text-2xl"></i></span>
            <h3 class="text-[22px] font-black">That didn't go through</h3>
            <p class="text-[13px] text-gray-500 mb-5" id="failed-reason-text">The payment wasn't completed. You can try again.</p>
            <button type="button" id="try-again-btn" class="{{ $primary }}">Try again</button>
        </div>

        {{-- Out of tries --}}
        <div id="online-fallback-panel" class="hidden py-2">
            <div class="text-center mb-5">
                <span class="mx-auto mb-4 w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center"><i class="fas fa-route text-2xl"></i></span>
                <h3 class="text-[22px] font-black">Let's try another way</h3>
                <p class="text-[13px] text-gray-500">The payment prompt didn't work after {{ config('gateways.paylesotho.max_attempts', 3) }} tries. Your place is still held.</p>
            </div>

            @if($paymentMethods->isNotEmpty())
                <button type="button" id="fallback-direct" class="{{ $dark }}">Pay the organizer directly</button>
            @elseif($merchant)
                <div class="rounded-2xl bg-slate-50 p-4 mb-4 text-[13px] text-gray-700 space-y-2">
                    <p class="font-black">On your phone, pay with EcoCash:</p>
                    <ol class="list-decimal list-inside space-y-1">
                        <li>Open the EcoCash menu and choose <strong>Pay merchant</strong></li>
                        <li>Merchant code <span class="font-mono font-black text-[#1D4069] text-[15px]">{{ $merchant['code'] }}</span> ({{ $merchant['name'] }})</li>
                        <li>Amount <span class="font-mono font-black text-[#1D4069]">M{{ number_format($owed, 2) }}</span></li>
                    </ol>
                    <p class="text-gray-500">Then tell us below, so we can match it and send your ticket.</p>
                </div>
                <form method="POST" action="{{ route('ticket.pay.merchant', $ticket->qr_code) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label for="merchant_phone" class="{{ $label }}">Number you paid from</label>
                        <input id="merchant_phone" name="merchant_phone" type="tel" required value="{{ old('merchant_phone') }}" placeholder="5949 4756" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="merchant_reference" class="{{ $label }}">Reference from the EcoCash message</label>
                        <input id="merchant_reference" name="merchant_reference" value="{{ old('merchant_reference') }}" placeholder="e.g. MP240101.1234.A12345" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="merchant_proof" class="{{ $label }}">Or a screenshot of it</label>
                        <input id="merchant_proof" name="proof" type="file" accept="image/*,application/pdf" class="w-full">
                    </div>
                    <button class="{{ $primary }}">I've paid</button>
                </form>
            @else
                <p class="text-center text-[13px] font-bold text-gray-500">Please contact {{ $organization->name }} to pay for your ticket.</p>
            @endif
        </div>

        {{-- Paid the merchant by hand --}}
        <div id="online-byhand-panel" class="hidden text-center py-4">
            <span class="mx-auto mb-5 w-16 h-16 rounded-2xl bg-mint text-mint-ink flex items-center justify-center"><i class="fas fa-magnifying-glass-dollar text-2xl"></i></span>
            <h3 class="text-[22px] font-black">We're checking your payment</h3>
            <p class="text-[13px] text-gray-500">Your ticket is sent as soon as we've matched it, usually within a few hours. No need to pay again.</p>
        </div>
    </div>
</section>
@endif

{{-- ── Paying the organizer directly ── --}}
<section class="{{ $card }} overflow-hidden">
    @if($onlineEnabled)
        <button type="button" id="manual-toggle" class="w-full flex items-center justify-between gap-3 p-5 sm:p-6 text-left">
            <span>
                <span class="block text-[15px] font-black">Or pay directly to the organizer</span>
                <span class="block text-[12px] text-gray-500 mt-0.5">Send it to their account, then tell us here. They confirm it, then your ticket activates.</span>
            </span>
            <i class="fas fa-chevron-down text-gray-400 transition-transform" id="manual-toggle-icon"></i>
        </button>
    @else
        <div class="p-5 sm:p-6 pb-0">
            <h2 class="text-[18px] font-black">Pay directly to the organizer</h2>
            <p class="text-[12px] text-gray-500 mt-0.5">Send it to their account, then tell us here. They confirm it, then your ticket activates.</p>
        </div>
    @endif

    <div id="manual-panel" class="accordion-panel {{ !$onlineEnabled || $errors->hasAny(['payment_method_id', 'payment_reference', 'proof', 'deposit_amount']) ? 'open' : '' }}">
        <form method="POST" action="{{ route('ticket.pay.manual', $ticket->qr_code) }}" enctype="multipart/form-data" class="p-5 sm:p-6 pt-4 space-y-5">
            @csrf

            @if($paymentMethods->isNotEmpty())
                @if($event->allow_installments && (float) $ticket->amount_paid <= 0)
                    @php
                        $minDeposit = $ticket->amount * ($event->minimum_deposit_percentage / 100);
                        $halfAmount = $ticket->amount / 2;
                        $fullAmount = $ticket->amount;
                    @endphp
                    <div>
                        <p class="{{ $label }}">How much now?</p>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_type" value="full" class="peer sr-only" checked required>
                                <span class="block h-full p-4 rounded-2xl border-2 border-gray-100 peer-checked:border-[#1D4069] peer-checked:bg-slate-50">
                                    <span class="block text-[13px] font-black">The full amount</span>
                                    <span class="block text-[18px] font-black text-[#F07F22]">M{{ number_format($owed, 2) }}</span>
                                </span>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_type" value="deposit" class="peer sr-only">
                                <span class="block h-full p-4 rounded-2xl border-2 border-gray-100 peer-checked:border-mint-ink peer-checked:bg-mint/50">
                                    <span class="block text-[13px] font-black">A deposit</span>
                                    <span class="block text-[18px] font-black text-mint-ink">From M{{ number_format($minDeposit, 2) }}</span>
                                    <span class="block text-[11px] text-gray-500">At least {{ number_format($event->minimum_deposit_percentage, 0) }}%, the rest later</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div id="deposit-amount-section" class="hidden p-4 rounded-2xl bg-mint/50">
                        <label for="deposit_amount" class="{{ $label }}">Deposit amount</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 font-black text-mint-ink">M</span>
                            <input type="number" name="deposit_amount" id="deposit_amount" step="0.01"
                                min="{{ $minDeposit }}" max="{{ $ticket->amount }}"
                                value="{{ old('deposit_amount', $minDeposit) }}"
                                class="{{ $input }} pl-9">
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <button type="button" onclick="setDepositAmount({{ $minDeposit }})" class="px-3 py-1.5 rounded-full bg-white text-[11px] font-black text-mint-ink">Minimum</button>
                            <button type="button" onclick="setDepositAmount({{ $halfAmount }})" class="px-3 py-1.5 rounded-full bg-white text-[11px] font-black text-mint-ink">Half</button>
                            <button type="button" onclick="setDepositAmount({{ $fullAmount }})" class="px-3 py-1.5 rounded-full bg-white text-[11px] font-black text-mint-ink">Full</button>
                        </div>
                    </div>
                @else
                    <input type="hidden" name="payment_type" value="full">
                @endif

                <div>
                    <p class="{{ $label }}">Which account did you pay?</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($paymentMethods as $method)
                            @php
                                $config = config('constants.payment_methods.' . $method->payment_method, []);
                                $icon = $config['icon'] ?? 'fa-money-bill';
                                $color = $config['color'] ?? 'text-gray-600';
                            @endphp
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_method_id" value="{{ $method->id }}" class="peer sr-only"
                                    data-instructions="{{ $method->instructions }}"
                                    data-is-cash="{{ $method->payment_method === 'cash' ? 'true' : 'false' }}"
                                    {{ (int) old('payment_method_id', $paymentMethods->first()?->id) === $method->id ? 'checked' : '' }} required>
                                <span class="flex h-full items-start gap-3 p-4 rounded-2xl border-2 border-gray-100 peer-checked:border-[#F07F22] peer-checked:bg-action-soft/40">
                                    <span class="w-9 h-9 shrink-0 rounded-xl bg-slate-50 flex items-center justify-center {{ $color }}"><i class="fas {{ $icon }}"></i></span>
                                    <span class="min-w-0">
                                        <span class="block text-[13px] font-black truncate">{{ $method->display_label }}</span>
                                        @if($method->payment_method !== 'cash' && $method->account_number)
                                            <span class="block text-[11px] text-gray-500">{{ $config['account_label'] ?? 'Send to' }}</span>
                                            <span class="block font-mono text-[13px] font-black text-[#1D4069] break-all">{{ $method->account_number }}</span>
                                        @else
                                            <span class="block text-[11px] text-gray-500">Pay in person</span>
                                        @endif
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div id="payment-instructions" class="hidden p-4 rounded-2xl bg-slate-50">
                    <p class="text-[11px] font-black text-gray-500 mb-1"><i class="fas fa-circle-info mr-1"></i>From the organizer</p>
                    <p class="text-[13px] font-bold text-gray-700 leading-relaxed" id="instruction-text"></p>
                </div>

                <div>
                    <label for="payment_reference" class="{{ $label }}">Reference <span class="font-medium text-gray-400">(not needed for cash)</span></label>
                    <input id="payment_reference" type="text" name="payment_reference" value="{{ old('payment_reference') }}" placeholder="From your payment message" class="{{ $input }}">
                    @error('payment_reference')<p class="text-[12px] font-bold text-rose-600 mt-1.5">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="manual_proof" class="{{ $label }}">Screenshot of the payment <span class="font-medium text-gray-400">(optional, or instead of the reference)</span></label>
                    <input type="file" id="manual_proof" name="proof" accept="image/*,application/pdf" class="w-full">
                    @error('proof')<p class="text-[12px] font-bold text-rose-600 mt-1.5">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="{{ $dark }}">I've paid, send it to the organizer</button>
            @else
                <p class="p-4 rounded-2xl bg-action-soft text-action-ink text-[13px] font-bold">The organizer hasn't added an account to pay into yet. Please contact {{ $organization->name }}.</p>
            @endif
        </form>
    </div>
</section>

@include('tickets.partials.save-link', ['ticket' => $ticket, 'class' => 'mt-4'])
@endsection

@push('scripts')
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
            const reset = () => { button.disabled = false; button.textContent = 'Send payment request'; };

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
        @elseif($onlineEnabled && $attemptsLeft === 0)
            showPanel('fallback');
        @endif
    });
    </script>

@endpush
