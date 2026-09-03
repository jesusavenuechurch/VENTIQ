<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Payment | {{ $organization->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .rounded-ventiq { border-radius: 2.5rem; }
        @keyframes protocol-pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.4); opacity: 0.3; }
            100% { transform: scale(1); opacity: 1; }
        }
        .status-pulse { animation: protocol-pulse 2s infinite ease-in-out; }
    </style>
</head>
<body class="bg-[#FBFBFC] text-[#1D4069] antialiased">

    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-gray-100">
        <div class="max-w-3xl mx-auto px-6 h-14 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ \App\Filament\Resources\SessionPackageResource::getUrl('index') }}" class="text-xl font-black tracking-tighter hover:text-[#F07F22]">V.</a>
                <div class="h-4 w-[1px] bg-gray-200"></div>
                <span class="text-[9px] font-bold uppercase tracking-widest text-gray-400 truncate max-w-[160px]">{{ $organization->name }}</span>
            </div>
            <span class="text-[9px] font-black text-[#F07F22] uppercase tracking-[0.2em]">Payment</span>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 lg:px-6 py-8 pb-20">

        {{-- ── SUMMARY CARD ───────────────────────────────────────── --}}
        <div class="bg-gray-900 rounded-ventiq shadow-2xl overflow-hidden mb-8">
            <div class="p-8 text-white">
                <span class="text-[9px] font-black uppercase tracking-[0.4em] text-[#F07F22]">Amount Due</span>
                <h2 class="text-4xl font-black tracking-tighter mt-1">M{{ number_format($amount, 2) }}</h2>
                <p class="text-[10px] font-bold text-white/50 uppercase tracking-widest mt-2">{{ $label }}</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-8 p-6 bg-rose-50 border-2 border-rose-100 rounded-3xl">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li class="text-xs font-bold text-rose-600">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ── ONLINE PAYMENT ─────────────────────────────────────── --}}
        @if($onlineEnabled)
        <div class="bg-white rounded-ventiq shadow-2xl shadow-gray-200/50 overflow-hidden border border-gray-100 mb-6">
            <div class="p-8 sm:p-10 border-b border-gray-50">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 bg-[#F07F22]/10 rounded-xl flex items-center justify-center text-[#F07F22]">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h2 class="text-2xl font-black text-gray-900 tracking-tighter uppercase italic leading-none">Pay Online</h2>
                </div>
                <p class="text-gray-500 font-medium text-sm">Instant activation via M-Pesa or EcoCash.</p>
            </div>

            <div class="p-8 sm:p-10">

                {{-- Provider + mobile number form --}}
                <div id="online-form-panel">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-1">Choose Provider</label>
                            <div class="grid grid-cols-2 gap-4">
                                <button type="button" id="provider-mpesa" data-method="mpesa"
                                    class="provider-btn p-5 rounded-2xl border-2 transition-all flex flex-col items-center gap-2 border-[#F07F22] bg-[#F07F22]/5">
                                    <i class="fas fa-mobile-alt text-xl text-red-600"></i>
                                    <span class="text-xs font-black uppercase tracking-tight text-gray-900">M-Pesa</span>
                                </button>
                                <button type="button" id="provider-ecocash" data-method="ecocash"
                                    class="provider-btn p-5 rounded-2xl border-2 transition-all flex flex-col items-center gap-2 border-slate-100 bg-slate-50">
                                    <i class="fas fa-mobile-alt text-xl text-blue-600"></i>
                                    <span class="text-xs font-black uppercase tracking-tight text-gray-900">EcoCash</span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">
                                Mobile Number <span class="text-rose-500">*</span>
                            </label>
                            <div class="flex">
                                <span class="inline-flex items-center px-4 bg-slate-100 border-2 border-r-0 border-slate-100 rounded-l-2xl font-black text-gray-400 text-xs">+266</span>
                                <input type="tel" id="online_phone_input"
                                    class="flex-1 bg-slate-50 border-2 border-slate-50 rounded-r-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] transition-all outline-none font-bold text-gray-900"
                                    placeholder="5949 4756" maxlength="10">
                            </div>
                            <p id="online-error" class="hidden text-[10px] font-bold text-rose-500 uppercase mt-2 ml-1"></p>
                        </div>

                        <button type="button" id="send-payment-request"
                            class="w-full py-6 bg-[#F07F22] hover:bg-[#1D4069] text-white rounded-2xl font-black text-xs uppercase tracking-[0.4em] shadow-xl active:scale-[0.98] transition-all">
                            Send Payment Request
                        </button>
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
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest" id="waiting-status-text">Waiting for confirmation&hellip;</p>
                </div>

                {{-- Timed-out state --}}
                <div id="online-timeout-panel" class="hidden text-center py-6">
                    <div class="w-16 h-16 bg-amber-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-clock text-amber-500 text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 uppercase tracking-tight mb-2">Still Pending</h3>
                    <p class="text-sm font-bold text-gray-500 mb-6">This is taking longer than usual. Check back shortly — we'll keep the payment open.</p>
                    <button type="button" onclick="window.location.reload()"
                        class="w-full py-5 bg-slate-900 hover:bg-[#1D4069] text-white rounded-2xl font-black text-xs uppercase tracking-[0.3em] transition-all">
                        Refresh Status
                    </button>
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

            </div>
        </div>
        @else
        <div class="bg-white rounded-ventiq shadow-2xl shadow-gray-200/50 overflow-hidden border border-gray-100 mb-6 p-8 sm:p-10 text-center">
            <div class="w-14 h-14 bg-amber-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                <i class="fas fa-tools text-amber-500 text-xl"></i>
            </div>
            <h2 class="text-xl font-black text-gray-900 tracking-tighter uppercase mb-2">Online Payment Unavailable</h2>
            <p class="text-gray-500 font-medium text-sm mb-6">
                We're temporarily unable to process online payments for plan upgrades. Reach out and we'll get you sorted directly.
            </p>
            <a href="mailto:support@ventiq.co.ls?subject={{ urlencode('Session Plan Upgrade — ' . $label) }}"
               class="inline-flex items-center gap-2 px-8 py-4 bg-[#1D4069] hover:bg-[#F07F22] text-white rounded-2xl font-black text-xs uppercase tracking-[0.3em] transition-all">
                <i class="fas fa-envelope"></i> Contact Us
            </a>
        </div>
        @endif

        <a href="{{ \App\Filament\Resources\SessionPackageResource::getUrl('index') }}"
           class="block text-center text-[10px] font-black text-gray-400 hover:text-[#1D4069] uppercase tracking-widest">
            &larr; Back to Session Plan
        </a>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // ── Provider selection ────────────────────────────────────
        let selectedMethod = 'mpesa';
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

        // ── Phone formatting (same pattern as the ticket payment screen) ──
        const onlinePhoneInput = document.getElementById('online_phone_input');
        onlinePhoneInput?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 8) value = value.substring(0, 8);
            if (value.length > 4) value = value.substring(0, 4) + ' ' + value.substring(4);
            e.target.value = value;
        });

        // ── Panels ─────────────────────────────────────────────────
        const formPanel = document.getElementById('online-form-panel');
        const waitingPanel = document.getElementById('online-waiting-panel');
        const timeoutPanel = document.getElementById('online-timeout-panel');
        const failedPanel = document.getElementById('online-failed-panel');
        const onlineError = document.getElementById('online-error');

        function showPanel(panel) {
            [formPanel, waitingPanel, timeoutPanel, failedPanel].forEach(p => p?.classList.add('hidden'));
            panel?.classList.remove('hidden');
        }

        let pollTimer = null;
        let pollElapsedMs = 0;
        const POLL_INTERVAL_MS = 2500;
        const POLL_TIMEOUT_MS = 3 * 60 * 1000;

        function stopPolling() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        function startPolling(sessionId) {
            pollElapsedMs = 0;
            stopPolling();

            const statusUrl = @json(route('paylesotho.status', ['session' => '__SESSION__'])).replace('__SESSION__', sessionId);

            pollTimer = setInterval(function() {
                pollElapsedMs += POLL_INTERVAL_MS;

                fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'completed') {
                            stopPolling();
                            window.location.href = @json(\App\Filament\Resources\SessionPackageResource::getUrl('index'));
                        } else if (data.status === 'failed') {
                            stopPolling();
                            showPanel(failedPanel);
                        } else if (pollElapsedMs >= POLL_TIMEOUT_MS) {
                            stopPolling();
                            showPanel(timeoutPanel);
                        }
                    })
                    .catch(function() {
                        if (pollElapsedMs >= POLL_TIMEOUT_MS) {
                            stopPolling();
                            showPanel(timeoutPanel);
                        }
                    });
            }, POLL_INTERVAL_MS);
        }

        document.getElementById('send-payment-request')?.addEventListener('click', function() {
            const digits = (onlinePhoneInput?.value || '').replace(/\D/g, '');

            if (digits.length !== 8) {
                onlineError.textContent = 'Enter a valid 8-digit mobile number.';
                onlineError.classList.remove('hidden');
                return;
            }
            onlineError.classList.add('hidden');

            const mobileNumber = '+266' + digits;
            const button = this;
            button.disabled = true;
            button.textContent = 'Sending…';

            fetch(@json(route('paylesotho.session-package.initiate')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    type: @json($type),
                    tier: @json($tier),
                    quantity: @json($quantity),
                    method: selectedMethod,
                    mobile_number: mobileNumber,
                }),
            })
                .then(res => res.json().then(data => ({ ok: res.ok, data })))
                .then(function({ ok, data }) {
                    button.disabled = false;
                    button.textContent = 'Send Payment Request';

                    if (!ok || !data.session_id) {
                        onlineError.textContent = 'Could not start payment. Please try again.';
                        onlineError.classList.remove('hidden');
                        return;
                    }

                    if (data.status === 'failed') {
                        showPanel(failedPanel);
                        return;
                    }

                    document.getElementById('waiting-masked-number').textContent =
                        '+266 •••• ' + digits.slice(-4);

                    showPanel(waitingPanel);
                    startPolling(data.session_id);
                })
                .catch(function() {
                    button.disabled = false;
                    button.textContent = 'Send Payment Request';
                    onlineError.textContent = 'Network error. Please try again.';
                    onlineError.classList.remove('hidden');
                });
        });

        document.getElementById('try-again-btn')?.addEventListener('click', function() {
            showPanel(formPanel);
        });
    });
    </script>
</body>
</html>
