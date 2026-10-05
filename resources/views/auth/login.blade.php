<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in | VENTIQ</title>
    @vite(['resources/css/app.css', 'resources/js/standalone.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen selection:bg-[#F07F22]/30">
@php
    $input = 'w-full bg-gray-50 border-2 border-gray-50 rounded-2xl px-5 py-4 font-bold text-[#1D4069] focus:bg-white focus:border-[#F07F22] outline-none transition-all';
    $primary = 'w-full py-5 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white font-black text-[10px] uppercase tracking-[0.3em] shadow-lg flex items-center justify-center transition-colors disabled:opacity-60 disabled:hover:bg-[#1D4069]';
    $link = 'text-[11px] font-bold text-gray-400 hover:text-[#F07F22]';
@endphp

{{--
  Email first. People who sign in with a password are asked for it; Google
  sign-ups and new emails get a 6-digit code by email. A code can always be
  asked for instead of a password.
--}}
<div class="min-h-screen flex flex-col items-center justify-center p-4"
     x-data="{
        step: 'email',          // email → password | code
        loading: false,
        error: @js(session('status')),
        notice: '',
        email: @js(old('email', request('email', ''))),
        password: '',
        code: '',
        intent: @js($intent),

        async post(url, body) {
            const r = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                           'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ ...body, intent: this.intent }),
            });
            const data = await r.json().catch(() => ({}));
            if (!r.ok) {
                const first = data.errors ? Object.values(data.errors)[0][0] : null;
                throw new Error(first || data.message || 'Something went wrong. Please try again.');
            }
            return data;
        },

        async run(fn) {
            this.loading = true; this.error = '';
            try { await fn(); } catch (e) { this.error = e.message; }
            this.loading = false;
        },

        start() {
            this.run(async () => {
                const data = await this.post('{{ route('login.start') }}', { email: this.email });
                this.go(data.next);
            });
        },

        sendCode() {
            this.run(async () => {
                const data = await this.post('{{ route('login.code.send') }}', { email: this.email });
                this.go(data.next);
                this.notice = 'New code sent.';
            });
        },

        withPassword() {
            this.run(async () => {
                const data = await this.post('{{ route('login.submit') }}', { email: this.email, password: this.password });
                window.location.replace(data.redirect || '/');
            });
        },

        withCode() {
            this.run(async () => {
                const data = await this.post('{{ route('login.code.verify') }}', { email: this.email, code: this.code });
                window.location.replace(data.redirect || '/');
            });
        },

        go(next) {
            this.step = next;
            this.notice = '';
            this.$nextTick(() => this.$refs[next]?.focus());
        },

        back() { this.step = 'email'; this.password = ''; this.code = ''; this.error = ''; this.$nextTick(() => this.$refs.email.focus()); },
     }">

    <div class="text-center mb-8">
        <span class="inline-block px-3 py-1 rounded-full bg-action-soft text-action-ink text-[10px] font-black uppercase tracking-widest mb-4"
              x-text="intent === 'session' ? 'Continue to Sessions' : 'Continue to your dashboard'"></span>
        <h1 class="text-4xl font-black tracking-tighter text-[#1D4069] uppercase">VENTI<span class="text-[#F07F22]">Q.</span></h1>
    </div>

    <div class="w-full max-w-md bg-white rounded-[2.5rem] shadow-2xl border border-white p-8 md:p-12">

        <div x-show="error" x-transition x-cloak role="alert" class="mb-6 p-4 bg-rose-50 text-rose-700 rounded-2xl text-[12px] font-bold border border-rose-100">
            <i class="fas fa-circle-exclamation mr-1"></i> <span x-text="error"></span>
        </div>

        {{-- Step 1: who are you? --}}
        <div x-show="step === 'email'">
            <a :href="`{{ route('auth.google.redirect') }}?intent=${intent}`"
               class="w-full py-4 rounded-2xl border border-gray-200 flex items-center justify-center gap-3 font-black text-[10px] uppercase tracking-[0.2em] text-[#1D4069] hover:border-[#1D4069] transition-all">
                <i class="fab fa-google text-[#F07F22]"></i> Continue with Google
            </a>

            <div class="flex items-center gap-3 my-6">
                <div class="flex-1 h-px bg-gray-100"></div>
                <span class="text-[9px] font-bold text-gray-300 uppercase tracking-widest">or use your email</span>
                <div class="flex-1 h-px bg-gray-100"></div>
            </div>

            <form @submit.prevent="start()" class="space-y-4">
                <label for="email" class="sr-only">Email address</label>
                <input id="email" x-ref="email" type="email" x-model="email" required autocomplete="email" autofocus placeholder="Email address" class="{{ $input }}">
                <button :disabled="loading || !email" class="{{ $primary }}">
                    <span x-show="!loading">Continue</span>
                    <i x-show="loading" x-cloak class="fas fa-spinner animate-spin text-lg"></i>
                </button>
            </form>
        </div>

        {{-- Step 2a: password --}}
        <div x-show="step === 'password'" x-cloak>
            <button type="button" @click="back()" class="mb-5 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-50 text-[12px] font-bold text-[#1D4069] max-w-full">
                <i class="fas fa-arrow-left text-[10px] text-gray-400"></i><span class="truncate" x-text="email"></span>
            </button>
            <form @submit.prevent="withPassword()" class="space-y-4">
                <label for="password" class="sr-only">Password</label>
                <input id="password" x-ref="password" type="password" x-model="password" required autocomplete="current-password" placeholder="Password" class="{{ $input }}">
                <button :disabled="loading || !password" class="{{ $primary }}">
                    <span x-show="!loading">Sign in</span>
                    <i x-show="loading" x-cloak class="fas fa-spinner animate-spin text-lg"></i>
                </button>
            </form>
            <div class="mt-5 text-center">
                <button type="button" @click="sendCode()" :disabled="loading" class="{{ $link }}">
                    <i class="fas fa-envelope mr-1"></i>Forgot it? Email me a code instead
                </button>
            </div>
        </div>

        {{-- Step 2b: code from email --}}
        <div x-show="step === 'code'" x-cloak>
            <button type="button" @click="back()" class="mb-5 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-50 text-[12px] font-bold text-[#1D4069] max-w-full">
                <i class="fas fa-arrow-left text-[10px] text-gray-400"></i><span class="truncate" x-text="email"></span>
            </button>
            <div class="mb-5 p-4 rounded-2xl bg-mint text-mint-ink text-[12px] font-bold">
                <i class="fas fa-envelope-open-text mr-1"></i>
                If there's an account for this email, we've sent it a 6-digit code. It works for 10 minutes.
                <span x-show="notice" x-text="' ' + notice"></span>
            </div>
            <form @submit.prevent="withCode()" class="space-y-4">
                <label for="code" class="sr-only">6-digit code</label>
                <input id="code" x-ref="code" x-model="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="000000"
                       @input="code = code.replace(/\D/g, '').slice(0, 6); if (code.length === 6 && !loading) withCode()"
                       class="{{ $input }} text-center text-2xl tracking-[0.5em] font-mono">
                <button :disabled="loading || code.length !== 6" class="{{ $primary }}">
                    <span x-show="!loading">Sign in</span>
                    <i x-show="loading" x-cloak class="fas fa-spinner animate-spin text-lg"></i>
                </button>
            </form>
            <div class="mt-5 flex flex-wrap justify-between gap-3">
                <button type="button" @click="sendCode()" :disabled="loading" class="{{ $link }}">Send a new code</button>
                <button type="button" @click="go('password')" class="{{ $link }}">Use my password instead</button>
            </div>
            <p class="mt-4 text-[11px] text-gray-400 text-center">Can't find it? Check spam, or tap the button in the email to sign in on that device.</p>
        </div>

        <p class="text-center text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-8">
            New here?
            <a :href="`{{ route('org.register.direct') }}?intent=${intent}`" class="text-[#F07F22] hover:underline">Create an account</a>
        </p>
    </div>
</div>
</body>
</html>
