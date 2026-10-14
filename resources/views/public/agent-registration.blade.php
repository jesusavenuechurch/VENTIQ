<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="title" content="Register Your Organization | VENTIQ">
    <meta name="description" content="Create professional event registration links, track attendance, manage payments, and access structured dashboards — all in one platform with VENTIQ.">

    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="Register | VENTIQ">
    <meta property="og:description" content="Create professional event registration links, track attendance, manage payments, and access structured dashboards — all in one platform with VENTIQ.">
    <meta property="og:image" content="{{ asset('images/meta.jpg') }}">

    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ url()->current() }}">
    <meta property="twitter:title" content="Register | VENTIQ">
    <meta property="twitter:description" content="Create professional event registration links, track attendance, manage payments, and access structured dashboards — all in one platform with VENTIQ.">
    <meta property="twitter:image" content="{{ asset('images/meta.jpg') }}">
    <title>Organization Onboarding | VENTIQ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .ventiq-navy { background-color: #1D4069; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen selection:bg-[#F07F22]/30">

    <div class="min-h-screen flex flex-col items-center justify-center p-4" 
         x-data="{ 
            step: 1, 
            totalSteps: 3,
            loading: false,
            submitted: false,
            errorMessage: '',
            
            // Data
            userName: '', 
            userEmail: '',
            userPassword: '',
            userPasswordConfirmation: '',
            isOrg: false,
            orgName: '',
            orgDistrict: '',
            rawPhone: '',

            get displayPhone() {
                let v = this.rawPhone.replace(/\D/g, '');
                if (v.length > 4) return v.substring(0, 4) + ' ' + v.substring(4, 8);
                return v;
            },

            async submitForm() {
                this.loading = true;
                this.errorMessage = '';
                try {
                    const response = await fetch('{{ route('agent.registration.submit', $agent->referral_token) }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({
                            user_name: this.userName,
                            user_email: this.userEmail,
                            user_password: this.userPassword,
                            user_password_confirmation: this.userPasswordConfirmation,
                            has_org: this.isOrg,
                            org_name: this.isOrg ? this.orgName : this.userName,
                            org_phone: this.rawPhone,
                            org_district: this.orgDistrict
                        })
                    });

                    const data = await response.json();
                    if (response.ok) {
                        this.submitted = true;
                        setTimeout(() => window.location.href = '/admin', 1500);
                    } else {
                        this.errorMessage = data.errors ? Object.values(data.errors).flat().join(' ') : 'Initialization failed.';
                        this.loading = false;
                    }
                } catch (e) {
                    this.errorMessage = 'Connection protocol failure.';
                    this.loading = false;
                }
            }
         }">

        <div class="text-center mb-8" x-show="!submitted">
            <span class="inline-block px-3 py-1 rounded-full bg-[#1D4069]/5 text-[#1D4069] text-[10px] font-black uppercase tracking-[0.2em] mb-4">
                Referred by Agent: {{ $agent->name }}
            </span>
            <h1 class="text-4xl font-black tracking-tighter text-[#1D4069] uppercase italic">
                VENTI<span class="text-[#F07F22]">Q.</span>
            </h1>
        </div>

        <div class="w-full max-w-xl bg-white rounded-[2.5rem] shadow-2xl shadow-blue-900/10 border border-white p-8 md:p-12 relative overflow-hidden">
            
            <div class="absolute top-0 left-0 w-full h-1.5 bg-gray-50" x-show="!submitted">
                <div class="h-full bg-gradient-to-r from-[#1D4069] to-[#F07F22] transition-all duration-500" :style="`width: ${(step/3)*100}%`"></div>
            </div>

            <div x-show="errorMessage" x-transition class="mb-6 p-4 bg-red-50 text-red-600 rounded-2xl text-[10px] font-bold uppercase tracking-widest border border-red-100">
                <span x-text="errorMessage"></span>
            </div>

            <div x-show="step === 1 && !submitted" class="space-y-6">
                <div>
                    <h2 class="text-xl font-black text-[#1D4069] uppercase tracking-tight">01. Administrator</h2>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1">Credentials for console access</p>
                </div>

                <div class="space-y-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-[#1D4069] uppercase tracking-widest ml-1">Full Name</label>
                        <input type="text" x-model="userName" placeholder="e.g. Samuel Molefe" class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold text-gray-900 focus:ring-2 focus:ring-[#F07F22]/20">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-[#1D4069] uppercase tracking-widest ml-1">Login Email</label>
                        <input type="email" x-model="userEmail" placeholder="admin@ventiq.io" class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold text-gray-900 focus:ring-2 focus:ring-[#F07F22]/20">
                    </div>
                </div>

                <button @click="if(userName && userEmail) step = 2" class="w-full py-5 rounded-2xl bg-[#1D4069] text-white font-black text-[10px] uppercase tracking-[0.3em] shadow-lg hover:bg-[#1D4069]/90 transition-all">
                    Next: Security Protocol
                </button>
            </div>

            <div x-show="step === 2 && !submitted" x-cloak class="space-y-6">
                <div>
                    <h2 class="text-xl font-black text-[#1D4069] uppercase tracking-tight">02. Security</h2>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1">Protect your organization</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-[#1D4069] uppercase tracking-widest ml-1">Password</label>
                        <input type="password" x-model="userPassword" class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold text-gray-900 focus:ring-2 focus:ring-[#F07F22]/20">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-[#1D4069] uppercase tracking-widest ml-1">Confirm</label>
                        <input type="password" x-model="userPasswordConfirmation" class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold text-gray-900 focus:ring-2 focus:ring-[#F07F22]/20">
                    </div>
                </div>

                <div class="flex gap-3">
                    <button @click="step = 1" class="w-1/3 py-5 rounded-2xl bg-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest">Back</button>
                    <button @click="if(userPassword.length >= 8 && userPassword === userPasswordConfirmation) step = 3" class="w-2/3 py-5 rounded-2xl bg-[#1D4069] text-white font-black text-[10px] uppercase tracking-[0.3em] shadow-lg">
                        Next: Representation
                    </button>
                </div>
            </div>

            <div x-show="step === 3 && !submitted" x-cloak class="space-y-6">
                <div>
                    <h2 class="text-xl font-black text-[#1D4069] uppercase tracking-tight">03. Deployment</h2>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1">Finalizing entity details</p>
                </div>

                <div class="space-y-4">
                    <label class="flex items-center gap-4 p-5 bg-gray-50 rounded-2xl border-2 border-transparent transition-all cursor-pointer" :class="isOrg ? 'border-[#F07F22]/40 bg-white shadow-sm' : ''">
                        <input type="checkbox" x-model="isOrg" class="w-5 h-5 rounded text-[#1D4069] focus:ring-[#1D4069]">
                        <span class="text-[10px] font-black uppercase tracking-widest text-[#1D4069]">Register as Church, School or Business</span>
                    </label>

                    <div x-show="isOrg" x-transition class="space-y-4 pt-2">
                        <div class="space-y-1">
                            <label class="text-[9px] font-black text-[#1D4069] uppercase tracking-widest ml-1">Organization Name</label>
                            <input type="text" x-model="orgName" placeholder="e.g. St. Peters School" class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold text-gray-900 focus:ring-2 focus:ring-[#F07F22]/20 shadow-inner">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-[9px] font-black text-[#1D4069] uppercase tracking-widest ml-1">Official Line</label>
                                <div class="flex bg-gray-50 rounded-2xl overflow-hidden focus-within:ring-2 focus-within:ring-[#F07F22]/20">
                                    <span class="px-4 flex items-center bg-gray-100 text-[#1D4069] font-black text-[10px] border-r border-gray-200">+266</span>
                                    <input type="tel" @input="rawPhone = $event.target.value.replace(/\D/g, '').substring(0, 8)" :value="displayPhone" placeholder="5812 3456" class="flex-1 bg-transparent border-none px-4 py-4 font-bold text-gray-900 mono tracking-widest outline-none">
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[9px] font-black text-[#1D4069] uppercase tracking-widest ml-1">District</label>
                                <input type="text" x-model="orgDistrict" placeholder="Maseru" class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold text-gray-900 focus:ring-2 focus:ring-[#F07F22]/20 shadow-inner">
                            </div>
                        </div>
                    </div>

                    <div x-show="!isOrg" class="p-6 bg-[#1D4069]/5 rounded-2xl border border-[#1D4069]/10">
                        <p class="text-[10px] text-[#1D4069] font-bold uppercase tracking-widest leading-loose italic">
                            System will auto-generate a Personal Organizer Profile for: <span x-text="userName"></span>
                        </p>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button @click="step = 2" class="w-1/3 py-5 rounded-2xl bg-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest">Back</button>
                    <button @click="submitForm()" :disabled="loading" class="w-2/3 py-5 rounded-2xl bg-[#1D4069] text-white font-black text-[10px] uppercase tracking-[0.3em] shadow-2xl flex items-center justify-center">
                        <span x-show="!loading">Initialize Deployment</span>
                        <svg x-show="loading" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                </div>
            </div>

            <div x-show="submitted" class="py-12 text-center" x-cloak>
                <div class="w-20 h-20 bg-green-50 text-green-500 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h3 class="text-2xl font-black text-[#1D4069] uppercase tracking-tighter">Protocol Synced</h3>
                <p class="text-[10px] text-gray-400 mt-2 font-bold uppercase tracking-widest">Entering Ventiq Console...</p>
            </div>
        </div>
    </div>

@include('partials.cookie-notice')
</body>
</html>