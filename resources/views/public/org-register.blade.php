<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="title" content="Register | VENTIQ">
    <meta name="description" content="Join the modern gateway for workshops, events, and seamless registrations in Lesotho — structured, professional, and simply connected.">

    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="Register | VENTIQ">
    <meta property="og:description" content="Join the calibrated middle-layer of Lesotho's event economy. Initialize your institutional gateway with VENTIQ.">
    <meta property="og:image" content="{{ asset('meta.jpg') }}">

    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ url()->current() }}">
    <meta property="twitter:title" content="Register | VENTIQ">
    <meta property="twitter:description" content="Join the calibrated middle-layer of Lesotho's event economy. Initialize your institutional gateway with VENTIQ.">
    <meta property="twitter:image" content="{{ asset('meta.jpeg') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; }
        @keyframes progress-loading { 0% { width: 0%; } 100% { width: 100%; } }
        .animate-fill { animation: progress-loading 4s linear forwards; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #E5E7EB; border-radius: 10px; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen selection:bg-[#F07F22]/30">

    <div class="min-h-screen flex flex-col items-center justify-center p-4" 
         x-data="{ 
            step: 1, 
            isOrg: true, 
            loading: false,
            submitted: false,
            errorMessage: '',
            setupStatus: 'Preparing your account...',
            
            userName: '', 
            userEmail: '',
            userPassword: '',
            userPasswordConfirmation: '',
            orgName: '',
            orgDistrict: 'Maseru',
            rawPhone: '',
            orgTagline: '', orgDescription: '', orgEmail: '', contactEmail: '',

            isValidEmail(email) {
                return String(email).toLowerCase().match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/);
            },

            isValidPassword(pass) {
                return /^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/.test(pass);
            },

            goToStep(nextStep) {
                this.errorMessage = '';
                this.step = nextStep;
            },

            get displayPhone() {
                let v = this.rawPhone.replace(/\D/g, '');
                return v.length > 4 ? v.substring(0, 4) + ' ' + v.substring(4, 8) : v;
            },

            async submitForm() {
                this.loading = true;
                this.errorMessage = '';
                if(!this.isOrg) this.orgName = this.userName;

                try {
                    const url = '{{ isset($agent) ? route('agent.registration.submit', $agent->referral_token) : route('org.register.submit') }}';
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json', 
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            user_name: this.userName,
                            user_email: this.userEmail,
                            user_password: this.userPassword,
                            user_password_confirmation: this.userPasswordConfirmation,
                            org_name: this.orgName,
                            org_phone: '+266 ' + this.displayPhone,
                            org_district: this.orgDistrict,
                            tagline: this.orgTagline,
                            description: this.orgDescription,
                            email: this.orgEmail || this.userEmail,
                            contact_email: this.contactEmail || this.userEmail,
                        })
                    });

                    const data = await response.json();

                    if (response.ok) {
                        this.submitted = true;
                        setTimeout(() => this.setupStatus = 'Syncing security protocols...', 1200);
                        setTimeout(() => this.setupStatus = 'Preparing your dashboard...', 2400);
                        setTimeout(() => window.location.replace(data.redirect || '/admin'), 4000);
                    } else {
                        this.errorMessage = data.message || 'Validation failed.';
                        this.loading = false;
                    }
                } catch (e) {
                    this.errorMessage = 'Network error. Please try again.';
                    this.loading = false;
                }
            }
         }">

        <div class="text-center mb-8" x-show="!submitted">
            @if(isset($agent))
                <span class="inline-block px-3 py-1 rounded-full bg-blue-50 text-[#1D4069] text-[10px] font-black uppercase tracking-widest mb-4">
                    <i class="fas fa-certificate mr-1 text-[#F07F22]"></i> Invited by {{ $agent->name }}
                </span>
            @else
                <span class="inline-block px-3 py-1 rounded-full bg-orange-50 text-[#F07F22] text-[10px] font-black uppercase tracking-widest mb-4">
                    🎁 Free Trial Included
                </span>
            @endif
            <h1 class="text-4xl font-black tracking-tighter text-[#1D4069] uppercase">
                VENTI<span class="text-[#F07F22]">Q.</span>
            </h1>
        </div>

        <div class="w-full max-w-xl bg-white rounded-[2.5rem] shadow-2xl border border-white p-8 md:p-12 relative overflow-hidden">
            
            <div class="absolute top-0 left-0 w-full h-1.5 bg-gray-50" x-show="!submitted">
                <div class="h-full bg-gradient-to-r from-[#1D4069] to-[#F07F22] transition-all duration-500" :style="`width: ${(step/3)*100}%`"></div>
            </div>

            <div x-show="errorMessage" x-transition class="mb-6 p-4 bg-red-50 text-red-600 rounded-2xl text-[10px] font-bold uppercase tracking-widest border border-red-100">
                <i class="fas fa-exclamation-triangle mr-2"></i> <span x-text="errorMessage"></span>
            </div>
                <a href="{{ route('auth.google.redirect') }}"
                class="w-full py-4 rounded-2xl border border-gray-200 flex items-center justify-center gap-3 font-black text-[10px] uppercase tracking-[0.2em] text-[#1D4069] hover:border-[#1D4069] transition-all mb-6">
                    <i class="fab fa-google text-[#F07F22]"></i>
                    Continue with Google
                </a>
                <div class="flex items-center gap-3 mb-6">
                    <div class="flex-1 h-px bg-gray-100"></div>
                    <span class="text-[9px] font-bold text-gray-300 uppercase tracking-widest">or</span>
                    <div class="flex-1 h-px bg-gray-100"></div>
                </div>
            <div x-show="step === 1 && !submitted" class="space-y-6">
                <h2 class="text-xl font-black text-[#1D4069] uppercase">01. Account Owner</h2>
                <div class="space-y-4">
                    <input type="text" x-model="userName" placeholder="Full Name" class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold focus:ring-2 focus:ring-[#F07F22]/20 outline-none">
                    <input type="email" x-model="userEmail" @input="errorMessage = ''" placeholder="Email Address" class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold focus:ring-2 focus:ring-[#F07F22]/20 outline-none">
                </div>
                <button @click="if(userName && isValidEmail(userEmail)) { goToStep(2) } else { errorMessage = 'Please enter a valid email address' }" 
                    class="w-full py-5 rounded-2xl bg-[#1D4069] text-white font-black text-[10px] uppercase tracking-[0.3em] shadow-lg">
                    Next: Security
                </button>
            </div>

            <div x-show="step === 2 && !submitted" x-cloak class="space-y-6">
                <h2 class="text-xl font-black text-[#1D4069] uppercase">02. Security</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="password" x-model="userPassword" placeholder="Password" class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold focus:ring-2 focus:ring-[#F07F22]/20 outline-none">
                    <input type="password" x-model="userPasswordConfirmation" placeholder="Confirm Password" class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold focus:ring-2 focus:ring-[#F07F22]/20 outline-none">
                </div>
                <div class="flex gap-3">
                    <button @click="goToStep(1)" class="w-1/3 py-5 rounded-2xl bg-gray-100 text-gray-400 font-black text-[10px] uppercase">Back</button>
                    <button @click="
                        if (!isValidPassword(userPassword)) {
                            errorMessage = 'Use 8+ characters with a number and symbol';
                        } else if (userPassword !== userPasswordConfirmation) {
                            errorMessage = 'Passwords do not match';
                        } else {
                            goToStep(3);
                        }
                    " class="w-2/3 py-5 rounded-2xl bg-[#1D4069] text-white font-black text-[10px] uppercase tracking-[0.3em] shadow-lg">
                        Next: Identity
                    </button>
                </div>
            </div>

            <div x-show="step === 3 && !submitted" x-cloak class="space-y-6" x-data="{ showAdvanced: false }">
    <div class="flex justify-between items-end mb-2">
        <h2 class="text-xl font-black text-[#1D4069] uppercase">03. Profile Type</h2>
        <span class="text-[9px] font-bold text-[#F07F22] uppercase tracking-widest mb-1">Recommended for Hosts</span>
    </div>

    <div class="space-y-4">
        <div class="flex p-1.5 bg-gray-100 rounded-[1.5rem]">
            <button @click="isOrg = true" type="button" :class="isOrg ? 'bg-white text-[#1D4069] shadow-sm' : 'text-gray-400'" class="flex-1 py-3 text-[10px] font-black uppercase rounded-2xl transition-all">Organization</button>
            <button @click="isOrg = false" type="button" :class="!isOrg ? 'bg-white text-[#1D4069] shadow-sm' : 'text-gray-400'" class="flex-1 py-3 text-[10px] font-black uppercase rounded-2xl transition-all">Individual</button>
        </div>

        <div x-show="isOrg" x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0 -translate-y-2">
            <input type="text" x-model="orgName" placeholder="Organization Name" class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
            
            <div class="mt-3">
                <button @click="showAdvanced = !showAdvanced" type="button" 
                        class="w-full p-4 rounded-2xl border border-emerald-100 transition-all text-left flex items-center justify-between group"
                        :class="showAdvanced ? 'bg-emerald-500 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center bg-white/20 shadow-inner">
                            <i class="fas fa-magic text-xs" :class="showAdvanced ? 'text-white' : 'text-emerald-500'"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wider leading-none">Give your brand some life</p>
                            <p class="text-[9px] font-bold opacity-70 mt-1" x-text="showAdvanced ? 'Tap to hide extra details' : 'Add tagline, description & contact emails'"></p>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right text-xs transition-transform duration-300" :class="showAdvanced ? 'rotate-90 text-white' : 'text-emerald-300 group-hover:translate-x-1'"></i>
                </button>
            </div>

            <div x-show="showAdvanced" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-4" class="mt-4 space-y-4 p-4 bg-gray-50 rounded-[2rem] border border-gray-100">
                <div class="space-y-4">
                    <div>
                        <label class="text-[9px] font-black text-gray-400 uppercase ml-1 mb-1 block">Tagline</label>
                        <input type="text" x-model="orgTagline" placeholder="e.g. Innovating Lesotho's Event Scene" class="w-full bg-white border-none rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500/20">
                    </div>
                    
                    <div>
                        <label class="text-[9px] font-black text-gray-400 uppercase ml-1 mb-1 block">Description</label>
                        <textarea x-model="orgDescription" placeholder="Tell us about your organization..." class="w-full bg-white border-none rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500/20 h-24 resize-none"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-1 mb-1 block">Public Email</label>
                            <input type="email" x-model="orgEmail" placeholder="info@company.com" class="w-full bg-white border-none rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500/20">
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-1 mb-1 block">Contact Email</label>
                            <input type="email" x-model="contactEmail" placeholder="support@company.com" class="w-full bg-white border-none rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500/20">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="!isOrg" class="p-4 bg-[#1D4069] rounded-2xl text-white font-bold text-[11px] italic" x-text="'Registering as: ' + userName"></div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex bg-gray-50 rounded-2xl overflow-hidden focus-within:ring-2 focus-within:ring-[#F07F22]/20">
                <span class="px-4 flex items-center bg-gray-100 text-[#1D4069] font-black text-[10px]">+266</span>
                <input type="tel" @input="rawPhone = $event.target.value.replace(/\D/g, '').substring(0, 8)" :value="displayPhone" placeholder="5812 3456" class="flex-1 bg-transparent border-none px-4 py-4 font-bold outline-none">
            </div>

            <div class="relative">
                <div class="hidden md:block">
                    <select x-model="orgDistrict" class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20 appearance-none cursor-pointer">
                        <template x-for="dist in ['Maseru', 'Leribe', 'Berea', 'Mafeteng', 'Mohale\'s Hoek', 'Quthing', 'Qacha\'s Nek', 'Mokhotlong', 'Thaba-Tseka', 'Butha-Buthe']">
                            <option :value="dist" x-text="dist"></option>
                        </template>
                    </select>
                </div>
                </div>
        </div>
    </div>

    <div class="flex gap-3 pt-4">
        <button @click="goToStep(2)" class="w-1/3 py-5 rounded-2xl bg-gray-100 text-gray-400 font-black text-[10px] uppercase">Back</button>
        <button @click="submitForm()" :disabled="loading" class="w-2/3 py-5 rounded-2xl bg-[#1D4069] text-white font-black text-[10px] uppercase tracking-[0.3em] shadow-2xl flex items-center justify-center">
            <span x-show="!loading">Launch Ventiq 🚀</span>
            <i x-show="loading" class="fas fa-spinner animate-spin text-lg"></i>
        </button>
    </div>
</div>

            <div x-show="submitted" x-cloak class="py-12 text-center">
                <div class="relative w-24 h-24 mx-auto mb-8">
                    <div class="absolute inset-0 border-4 border-gray-100 border-t-[#F07F22] rounded-full animate-spin"></div>
                    <div class="absolute inset-0 flex items-center justify-center"><i class="fas fa-rocket text-[#1D4069] text-2xl"></i></div>
                </div>
                <h3 class="text-2xl font-black text-[#1D4069] uppercase italic">Initializing...</h3>
                <div class="w-48 h-1 bg-gray-100 mx-auto rounded-full overflow-hidden mt-4"><div class="h-full bg-[#1D4069] animate-fill"></div></div>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-4" x-text="setupStatus"></p>
            </div>
        </div>
    </div>
</body>
</html>