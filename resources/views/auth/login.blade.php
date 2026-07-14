<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Log In | VENTIQ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen selection:bg-[#F07F22]/30">

    <div class="min-h-screen flex flex-col items-center justify-center p-4"
         x-data="{
            loading: false,
            errorMessage: '',
            email: '',
            password: '',
            intent: '{{ $intent }}',

            async submit() {
                this.loading = true;
                this.errorMessage = '';

                try {
                    const response = await fetch('{{ route('login.submit') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                        },
                        body: JSON.stringify({ email: this.email, password: this.password, intent: this.intent })
                    });

                    if (response.ok) {
                        const data = await response.json();
                        window.location.replace(data.redirect || '/admin');
                    } else {
                        const data = await response.json().catch(() => ({}));
                        this.errorMessage = data.message || 'Those credentials don\'t match an account.';
                        this.loading = false;
                    }
                } catch (e) {
                    this.errorMessage = 'Network error. Please try again.';
                    this.loading = false;
                }
            }
         }">

        <div class="text-center mb-8">
            <span class="inline-block px-3 py-1 rounded-full bg-orange-50 text-[#F07F22] text-[10px] font-black uppercase tracking-widest mb-4">
                <span x-text="intent === 'session' ? 'Continue to Sessions' : 'Continue to your dashboard'"></span>
            </span>
            <h1 class="text-4xl font-black tracking-tighter text-[#1D4069] uppercase">
                VENTI<span class="text-[#F07F22]">Q.</span>
            </h1>
        </div>

        <div class="w-full max-w-md bg-white rounded-[2.5rem] shadow-2xl border border-white p-8 md:p-12 relative overflow-hidden">

            <div x-show="errorMessage" x-transition x-cloak class="mb-6 p-4 bg-red-50 text-red-600 rounded-2xl text-[10px] font-bold uppercase tracking-widest border border-red-100">
                <i class="fas fa-exclamation-triangle mr-2"></i> <span x-text="errorMessage"></span>
            </div>

            <a :href="`{{ route('auth.google.redirect') }}?intent=${intent}`"
               class="w-full py-4 rounded-2xl border border-gray-200 flex items-center justify-center gap-3 font-black text-[10px] uppercase tracking-[0.2em] text-[#1D4069] hover:border-[#1D4069] transition-all mb-6">
                <i class="fab fa-google text-[#F07F22]"></i>
                Continue with Google
            </a>

            <div class="flex items-center gap-3 mb-6">
                <div class="flex-1 h-px bg-gray-100"></div>
                <span class="text-[9px] font-bold text-gray-300 uppercase tracking-widest">or</span>
                <div class="flex-1 h-px bg-gray-100"></div>
            </div>

            <div class="space-y-4">
                <input type="email" x-model="email" @keydown.enter="submit()" placeholder="Email Address"
                       class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold focus:ring-2 focus:ring-[#F07F22]/20 outline-none">
                <input type="password" x-model="password" @keydown.enter="submit()" placeholder="Password"
                       class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold focus:ring-2 focus:ring-[#F07F22]/20 outline-none">
            </div>

            <button @click="submit()" :disabled="loading"
                    class="w-full mt-6 py-5 rounded-2xl bg-[#1D4069] text-white font-black text-[10px] uppercase tracking-[0.3em] shadow-lg flex items-center justify-center">
                <span x-show="!loading">Login</span>
                <i x-show="loading" x-cloak class="fas fa-spinner animate-spin text-lg"></i>
            </button>

            <p class="text-center text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-6">
                New here?
                <a :href="`{{ route('org.register.direct') }}?intent=${intent}`" class="text-[#F07F22] hover:underline">Register</a>
            </p>
        </div>
    </div>
</body>
</html>