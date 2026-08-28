<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'VENTIQ | Event Operations & Access Management')</title>
    <meta name="description" content="@yield('meta_description', 'The modern gateway for workshops, events, and seamless registrations in Lesotho. Simply Connected.')">
    
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->full() }}">
    <meta property="og:title" content="@yield('title', 'VENTIQ | Event Operations & Access Management')">
    <meta property="og:description" content="@yield('meta_description', 'The modern gateway for workshops, events, and seamless registrations in Lesotho. Simply Connected.')">
    <meta property="og:image" content="{{ asset('images/meta.jpeg') }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ url()->full() }}">
    <meta property="twitter:title" content="@yield('title', 'VENTIQ | Event Operations & Access Management')">
    <meta property="twitter:description" content="@yield('meta_description', 'The modern gateway for workshops, events, and seamless registrations. Simply Connected.')">
    <meta property="twitter:image" content="{{ asset('images/meta.jpeg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.tailwindcss.com"></script>
    {{-- No separate Alpine script here — Livewire 3 bundles and boots its
         own Alpine instance. Loading a second copy (as this page did) is a
         documented Livewire footgun: pure-Alpine toggles like x-show can
         appear to work off whichever instance wins, while wire:submit and
         other Livewire-Alpine integration points silently stop binding —
         exactly the "click/Enter do nothing, form falls back to a native
         submit" symptom this caused for the Ask Ventiq widget. --}}
    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
    
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .custom-blur { backdrop-filter: blur(12px); background-color: rgba(255, 255, 255, 0.9); }
        #page-loader {
            transition: opacity 0.7s cubic-bezier(0.4, 0, 0.2, 1),
            visibility 0.7s cubic-bezier(0.4, 0, 0.2, 1);
        }
        #page-loader.done {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
    </style>
</head>

{{--
  ============================================================
  ONE Alpine scope for the whole layout (body tag). Search,
  location detection, contact modal, and terms modal all live
  in this single x-data — no nested/duplicate scopes per page.
  Districts list mirrors config('constants.districts') — keep
  the JS array below in sync if you ever rename a district there.
  ============================================================
--}}
<body class="h-full overflow-hidden flex flex-col bg-[#F8FAFC] text-[#1D4069]"
      x-data="{
        // ── Contact modal ──────────────────────────────────────
        showChat: false,
        showTerms: false,
        // ── Ask Ventiq — docked panel, not a page. Stays open across
        //    navigation only within this single page load; a fresh load
        //    re-mounts the component, which picks the user's most recent
        //    conversation back up (see ChatPage::mount()).
        showAssist: false,
        submitted: false,
        loading: false,
        name: '',
        phone: '',
        email: '',
        subject: 'Select Inquiry Type',
        message: '',
        errorMessage: '',

        // ── Search ──────────────────────────────────────────────
        searchOpen: false,
        searchQuery: '',
        searchResults: [],
        searchLoading: false,
        searchDebounce: null,

        // ── Location / district ─────────────────────────────────
        detectedDistrict: 'Maseru',
        districtPickerOpen: false,
        districts: {{ \Illuminate\Support\Js::from(config('constants.districts')) }},

        get displayPhone() {
            let v = this.phone.replace(/\\D/g, '');
            if (v.length > 4) return v.substring(0, 4) + ' ' + v.substring(4, 8);
            return v;
        },

        async sendInquiry() {
            if(!this.name || this.phone.length < 8 || !this.message || this.subject === 'Select Inquiry Type') {
                this.errorMessage = 'Please complete all required fields.';
                return;
            }

            this.loading = true;
            this.errorMessage = '';

            try {
                const response = await fetch('/contact', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({
                        name: this.name,
                        phone: '+266 ' + this.displayPhone,
                        email: this.email,
                        subject: this.subject,
                        message: this.message
                    })
                });

                if (response.ok) {
                    this.submitted = true;
                } else {
                    const data = await response.json();
                    this.errorMessage = data.message || 'Transmission failed.';
                }
            } catch (e) {
                this.errorMessage = 'Network protocol error.';
            } finally {
                this.loading = false;
            }
        },

        openSearch() {
            this.searchOpen = true;
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },

        // Debounced so we don't fire a request on every keystroke.
        // District is passed through so results from the current
        // district rank first without excluding everywhere else.
        runSearch() {
            clearTimeout(this.searchDebounce);
            if (this.searchQuery.length < 2) {
                this.searchResults = [];
                this.searchLoading = false;
                return;
            }
            this.searchLoading = true;
            this.searchDebounce = setTimeout(() => {
                const params = new URLSearchParams({
                    q: this.searchQuery,
                    district: this.detectedDistrict || ''
                });
                fetch(`{{ route('events.search') }}?${params}`)
                    .then(r => r.json())
                    .then(d => { this.searchResults = d; this.searchLoading = false; })
                    .catch(() => { this.searchResults = []; this.searchLoading = false; });
            }, 300);
        },

        selectDistrict(d) {
            this.detectedDistrict = d;
            this.districtPickerOpen = false;
            if (this.searchQuery.length >= 2) this.runSearch();
            window.dispatchEvent(new CustomEvent('district-changed', { detail: d }));
        },

        // One-line geolocation ask — browser handles the native
        // permission prompt itself, no custom UI needed for that.
        // Denied/unavailable just silently keeps the 'Maseru' default.
        detectDistrict() {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const centroids = {
                        'Maseru': [-29.31, 27.48], 'Leribe': [-28.87, 28.05], 'Mafeteng': [-29.82, 27.25],
                        'Mohale\'s Hoek': [-30.15, 27.47], 'Butha-Buthe': [-28.75, 28.25],
                        'Qacha\'s Nek': [-30.11, 28.68], 'Mokhotlong': [-29.29, 29.07],
                        'Quthing': [-30.40, 27.72], 'Berea': [-29.13, 27.73], 'Thaba-Tseka': [-29.52, 28.61]
                    };
                    const R = 6371, toRad = deg => deg * Math.PI / 180;
                    let best = null, bestDist = Infinity;
                    for (const [name, [lat, lon]] of Object.entries(centroids)) {
                        const dLat = toRad(lat - pos.coords.latitude);
                        const dLon = toRad(lon - pos.coords.longitude);
                        const a = Math.sin(dLat / 2) ** 2
                            + Math.cos(toRad(pos.coords.latitude)) * Math.cos(toRad(lat)) * Math.sin(dLon / 2) ** 2;
                        const dist = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                        if (dist < bestDist) { bestDist = dist; best = name; }
                    }
                    if (best) {
                        this.detectedDistrict = best;
                        window.dispatchEvent(new CustomEvent('district-changed', { detail: best }));
                    }
                },
                () => { /* denied or unavailable — keep default, no nag */ }
            );
        }
      }"
      x-init="detectDistrict()"
      @contact-open.window="showChat = true"
      @keydown.window="if (($event.metaKey || $event.ctrlKey) && $event.key === 'k') { $event.preventDefault(); openSearch(); }">

<div id="page-loader"
     class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-[#1D4069]">

    <div class="flex flex-col items-center gap-4">

        <dotlottie-player
            id="loader-lottie"
            src="{{ asset('animation/loader.json') }}"
            background="transparent"
            speed="0.6"
            style="width: 220px; height: 220px;"
            autoplay
            loop>
        </dotlottie-player>

        <div class="flex flex-col items-center gap-1">
            <span class="text-2xl font-black tracking-tighter uppercase text-white">
                VENTI<span class="text-[#F07F22]">Q</span>
            </span>
            <span class="text-[9px] font-bold tracking-[0.3em] text-white/40 uppercase">
                Intelligence
            </span>
        </div>
    </div>

    <p class="absolute bottom-10 text-[9px] font-bold tracking-[0.3em] text-white/20 uppercase">
        Simply Connected
    </p>
</div>

    <nav class="flex-none border-b border-gray-100 bg-white/80 backdrop-blur-md z-50 sticky top-0">
        <div class="max-w-7xl mx-auto px-4 h-16 md:h-20 flex items-center justify-between gap-6">

            <!-- Left Group: Brand & Search Capsule -->
            <div class="flex items-center gap-8 md:gap-10 flex-1">

                <!-- Brand Block -->
                <a href="/" class="flex items-center gap-3 shrink-0 transition-transform active:scale-95">
                    <img src="{{ asset('images/ventiq-noback.png') }}" alt="VENTIQ" class="h-6 md:h-7 w-auto object-contain">
                    <div class="hidden sm:flex flex-col leading-none">
                        <span class="text-sm font-black tracking-tighter uppercase text-[#1D4069]">
                            VENTI<span class="text-[#F07F22]">Q</span>
                        </span>
                        <span class="text-[6.5px] font-bold tracking-[0.2em] text-gray-400 uppercase">Intelligence</span>
                    </div>
                </a>

                <!-- Search + District Capsule -->
                <div class="hidden md:flex h-12 bg-white rounded-full border border-gray-200 hover:border-gray-300 focus-within:border-gray-300 hover:shadow-md focus-within:shadow-md transition-all items-center px-2 shadow-sm relative">

                    <!-- Query -->
                    <input type="text"
                        @focus="openSearch()"
                        placeholder="Search event"
                        readonly
                        class="w-40 bg-transparent border-none text-[13px] font-bold text-[#1D4069] placeholder-gray-400 outline-none h-full py-2 pl-4 cursor-pointer">

                    <div class="h-5 w-[1px] bg-gray-200 shrink-0 mx-2"></div>

                    <!-- District picker -->
                    <div class="relative">
                        <button type="button" @click="districtPickerOpen = !districtPickerOpen"
                            class="w-36 px-2 text-left flex items-center gap-1.5 cursor-pointer">
                            <i class="fas fa-location-dot text-[10px] text-[#F07F22]"></i>
                            <span class="text-[13px] font-bold text-[#1D4069] truncate" x-text="detectedDistrict"></span>
                        </button>

                        <div x-show="districtPickerOpen"
                             x-cloak
                             @click.away="districtPickerOpen = false"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="absolute z-20 mt-3 left-0 w-48 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden max-h-64 overflow-y-auto no-scrollbar">
                            <template x-for="d in districts" :key="d">
                                <button type="button" @click="selectDistrict(d)"
                                    class="w-full text-left px-4 py-2.5 text-[11px] font-bold uppercase tracking-wide hover:bg-gray-50 transition-colors flex items-center justify-between"
                                    :class="d === detectedDistrict ? 'text-[#F07F22]' : 'text-gray-600'">
                                    <span x-text="d"></span>
                                    <i class="fas fa-check text-[9px]" x-show="d === detectedDistrict"></i>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Execution -->
                    <button @click="openSearch()" class="w-9 h-9 shrink-0 bg-[#1D4069]/5 hover:bg-[#1D4069] group rounded-full flex items-center justify-center transition-all duration-200 cursor-pointer active:scale-95">
                        <i class="fas fa-search text-[#1D4069] group-hover:text-white text-xs transition-colors"></i>
                    </button>
                </div>

                <!-- Mobile search trigger -->
                <button @click="openSearch()" class="md:hidden w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-[#1D4069]">
                    <i class="fas fa-search text-xs"></i>
                </button>

            </div>

            <!-- Right: System Access Controls -->
            <div class="flex items-center gap-4 md:gap-6 shrink-0">
                <button @click="showChat = true" class="text-[10px] font-bold uppercase tracking-widest text-gray-400 hover:text-[#1D4069] transition-colors">
                    Support
                </button>

@auth
                    <div class="relative" x-data="{ accountOpen: false }">
                        <button @click="accountOpen = !accountOpen" type="button"
                                class="flex items-center gap-2 pl-1.5 pr-4 py-1.5 rounded-full bg-[#1D4069] text-white text-[10px] font-black uppercase tracking-widest hover:bg-[#F07F22] transition-all duration-300 active:scale-95">
                            <span class="w-6 h-6 rounded-full bg-white/20 flex items-center justify-center text-[9px] font-black shrink-0">
                                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                            </span>
                            {{ Str::before(auth()->user()->name ?? 'Account', ' ') }}
                            <i class="fas fa-chevron-down text-[8px] transition-transform" :class="accountOpen ? 'rotate-180' : ''"></i>
                        </button>

                       <div x-show="accountOpen"
                            x-cloak
                            @click.away="accountOpen = false"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="absolute right-0 mt-3 w-44 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden z-20">
                            <a href="{{ route('sessions.index') }}" class="block px-5 py-3.5 text-[11px] font-bold uppercase tracking-wide text-gray-600 hover:bg-gray-50 hover:text-[#F07F22] transition-colors">Sessions</a>
                            <a href="{{ route('programmes.index') }}" class="block px-5 py-3.5 text-[11px] font-bold uppercase tracking-wide text-gray-600 hover:bg-gray-50 hover:text-[#F07F22] transition-colors">Programmes</a>
                            <button type="button" @click="accountOpen = false; showAssist = true" class="block w-full text-left px-5 py-3.5 text-[11px] font-bold uppercase tracking-wide text-gray-600 hover:bg-gray-50 hover:text-[#F07F22] transition-colors">Ask Ventiq</button>
                            <a href="{{ route('organization.members') }}" class="block px-5 py-3.5 text-[11px] font-bold uppercase tracking-wide text-gray-600 hover:bg-gray-50 hover:text-[#F07F22] transition-colors">Team</a>
                            <div class="border-t border-gray-100"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                        class="w-full text-left px-5 py-3.5 text-[11px] font-bold uppercase tracking-wide text-gray-600 hover:bg-gray-50 hover:text-[#F07F22] transition-colors">
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}"
                    class="px-5 py-2 rounded-full border border-[#1D4069]/10 text-[#1D4069] text-[10px] font-black uppercase tracking-widest hover:border-[#1D4069] hover:bg-[#1D4069] hover:text-white transition-all duration-300 active:scale-95">
                        Login
                    </a>
                @endauth
            </div>

        </div>
    </nav>

    <main class="flex-grow relative overflow-y-auto no-scrollbar">
        @yield('content')
    </main>

    <footer class="flex-none bg-white border-t border-gray-100 py-3 px-6">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">© {{ date('Y') }} VENTI<span class="text-[#F07F22]">Q</span> LESOTHO</p>
            <div class="flex gap-4 text-[10px] font-bold uppercase tracking-tight text-gray-500">
                <button @click="showTerms = true" class="hover:text-[#F07F22]">Terms</button>
                <button @click="showChat = true" class="hover:text-[#F07F22]">Support</button>
            </div>
        </div>
    </footer>

    {{-- =========================================================
         SEARCH MODAL — ⌘K / click / mobile trigger all open this.
         ========================================================= --}}
    <div x-show="searchOpen"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         @keydown.escape.window="searchOpen = false"
         class="fixed inset-0 z-[80] flex items-start justify-center pt-24 px-4"
         style="display:none;">
        <div class="absolute inset-0 bg-[#1D4069]/40 backdrop-blur-sm" @click="searchOpen = false"></div>

        <div class="relative w-full max-w-xl bg-white rounded-[2rem] shadow-2xl overflow-hidden border border-white/20"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0">

            <div class="h-1.5 w-full bg-gradient-to-r from-[#1D4069] via-[#F07F22] to-[#1D4069]"></div>

            <div class="flex items-center gap-3 px-6 py-5 border-b border-gray-100">
                <i class="fas fa-search text-gray-300"></i>
                <input x-ref="searchInput"
                    x-model="searchQuery"
                    @input="runSearch()"
                    type="text"
                    placeholder="Search events, venues, districts..."
                    class="flex-1 outline-none text-sm font-bold text-gray-800 placeholder-gray-300 placeholder:font-medium">
                <span class="text-[9px] font-black uppercase tracking-widest text-gray-300 flex items-center gap-1">
                    <i class="fas fa-location-dot text-[#F07F22]"></i>
                    <span x-text="detectedDistrict"></span>
                </span>
                <button @click="searchOpen = false" class="text-gray-300 hover:text-gray-500 text-[10px] font-black uppercase tracking-widest">ESC</button>
            </div>

            <div class="max-h-96 overflow-y-auto no-scrollbar">
                <template x-if="searchLoading">
                    <div class="p-8 text-center text-[10px] font-bold text-gray-400 uppercase tracking-widest">Searching…</div>
                </template>

                <template x-if="!searchLoading && searchQuery.length >= 2 && searchResults.length === 0">
                    <div class="p-8 text-center text-[10px] font-bold text-gray-400 uppercase tracking-widest">No events found</div>
                </template>

                <template x-if="!searchLoading && searchQuery.length < 2">
                    <div class="p-8 text-center text-[10px] font-bold text-gray-300 uppercase tracking-widest">Type at least 2 characters</div>
                </template>

                <template x-for="event in searchResults" :key="event.url">
                    <a :href="event.url" class="flex items-center gap-4 px-6 py-3.5 hover:bg-gray-50 border-b border-gray-50 last:border-none transition-colors">
                        <div class="w-12 h-12 rounded-xl bg-[#1D4069] flex-shrink-0 overflow-hidden flex items-center justify-center">
                            <img x-show="event.image" :src="event.image" class="w-full h-full object-cover">
                            <span x-show="!event.image" class="text-white/20 text-[8px] font-black uppercase">VQ</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-black text-gray-900 truncate" x-text="event.name"></p>
                            <p class="text-[11px] text-gray-400 font-medium truncate" x-text="[event.subtitle, event.date].filter(Boolean).join(' · ')"></p>
                        </div>
                        <span x-show="event.is_local === false"
                              class="text-[8px] font-black uppercase tracking-widest text-gray-300 border border-gray-200 rounded-full px-2 py-1 shrink-0">
                            Other district
                        </span>
                    </a>
                </template>
            </div>
        </div>
    </div>

    {{-- =========================================================
         CONTACT / SUPPORT MODAL
         ========================================================= --}}
    <div x-show="showChat"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-[#1D4069]/40 backdrop-blur-sm"
         @click.self="showChat = false" x-cloak>

        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md overflow-hidden border border-white/20 transform transition-all">
            <div class="h-1.5 w-full bg-gradient-to-r from-[#1D4069] via-[#F07F22] to-[#1D4069]"></div>

            <div class="p-8">
                <div x-show="!submitted">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h3 class="text-2xl font-black text-[#1D4069] tracking-tight uppercase italic">Support<span class="text-[#F07F22]">.</span></h3>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mt-1">Direct Access Console</p>
                        </div>
                        <button @click="showChat = false" class="p-2 hover:bg-gray-50 rounded-full transition-colors text-gray-300">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-1" x-data="{ open: false }">
                            <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1">Inquiry Type</label>
                            <div class="relative">
                                <button @click="open = !open" type="button"
                                    :class="subject === 'Select Inquiry Type' ? 'text-gray-400 italic' : 'text-gray-900 font-bold'"
                                    class="w-full bg-gray-50 rounded-2xl px-5 py-4 text-xs text-left flex justify-between items-center outline-none ring-[#F07F22]/20 focus:ring-2 transition-all">
                                    <span x-text="subject"></span>
                                    <i class="fas fa-chevron-down text-[#F07F22] text-[10px] transition-transform" :class="open ? 'rotate-180' : ''"></i>
                                </button>
                                <div x-show="open" @click.away="open = false" class="absolute z-10 mt-2 w-full bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                                    <template x-for="option in ['Host an Event (Free Trial)', 'Ticket Support', 'Partnership Inquiry', 'General Support']">
                                        <button @click="subject = option; open = false" type="button" class="w-full text-left px-5 py-3 text-[11px] font-bold uppercase hover:bg-gray-50 hover:text-[#F07F22] transition-colors text-gray-600" x-text="option"></button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1">Name</label>
                                <input type="text" x-model="name" class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 text-sm font-bold focus:ring-2 focus:ring-[#F07F22]/20 outline-none">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1">WhatsApp (+266)</label>
                                <input type="tel"
                                    @input="phone = $event.target.value.replace(/\D/g, '').substring(0, 8)"
                                    :value="displayPhone"
                                    placeholder="5... ...."
                                    class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 text-sm font-bold mono tracking-widest focus:ring-2 focus:ring-[#F07F22]/20 outline-none">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1">Message</label>
                            <textarea x-model="message" rows="3" class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 text-sm font-medium focus:ring-2 focus:ring-[#F07F22]/20 outline-none resize-none"></textarea>
                        </div>

                        <div x-show="errorMessage" class="text-[10px] font-bold text-red-500 uppercase tracking-tighter ml-1" x-text="errorMessage"></div>

                        <button @click="sendInquiry()" :disabled="loading"
                            class="w-full py-5 rounded-2xl bg-[#1D4069] text-white font-black text-[10px] uppercase tracking-[0.3em] shadow-xl hover:bg-[#F07F22] transition-all flex items-center justify-center">
                            <span x-show="!loading">Send Inquiry</span>
                            <svg x-show="loading" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        </button>
                    </div>
                </div>

                <div x-show="submitted" class="py-12 text-center" x-cloak>
                    <div class="w-20 h-20 bg-green-50 text-green-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
                        <i class="fas fa-check text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-black text-[#1D4069] tracking-tighter uppercase italic">Message Sent</h3>
                    <p class="text-[10px] text-gray-400 mt-2 font-bold uppercase tracking-[0.2em]">Our core team will respond shortly.</p>
                    <button @click="showChat = false; submitted = false; name=''; phone=''; message=''; subject='Select Inquiry Type';" class="mt-8 text-[10px] font-black text-[#F07F22] uppercase tracking-[0.3em] border-b border-[#F07F22]/20 pb-1">Exit Console</button>
                </div>
            </div>
        </div>
    </div>

    {{-- =========================================================
         ASK VENTIQ — floating, docked panel. Deliberately not a page:
         a quick lookup shouldn't cost someone their place in whatever
         they were doing. Org-scoped, so gated the same way the Sessions
         desk itself is (superadmins have no organization_id).
         ========================================================= --}}
    @auth
        @if(auth()->user()->organization_id)
            <button @click="showAssist = !showAssist"
                    class="fixed bottom-6 right-6 z-[55] w-14 h-14 rounded-full bg-[#1D4069] hover:bg-[#F07F22] text-white shadow-xl flex items-center justify-center transition-all active:scale-95">
                <i class="fas fa-wand-magic-sparkles text-lg" x-show="!showAssist"></i>
                <i class="fas fa-times text-lg" x-show="showAssist" x-cloak></i>
            </button>

            <div x-show="showAssist"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-3"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 class="fixed bottom-24 right-6 z-[55] w-[calc(100vw-3rem)] max-w-sm h-[520px] max-h-[70vh] bg-white rounded-[2rem] shadow-2xl border border-gray-100 overflow-hidden flex flex-col"
                 x-cloak>
                <div class="flex items-center justify-between px-5 py-4 border-b shrink-0">
                    <div>
                        <p class="text-[13px] font-black text-[#1D4069] uppercase tracking-tight">Ask Ventiq</p>
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Search your sessions</p>
                    </div>
                    <button @click="showAssist = false" class="p-2 hover:bg-gray-50 rounded-full transition-colors text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="flex-1 min-h-0">
                    @livewire('assist.chat-page')
                </div>
            </div>
        @endif
    @endauth

    {{-- =========================================================
         TERMS MODAL
         ========================================================= --}}
    <div x-show="showTerms"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-8"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-[#1D4069]/40 backdrop-blur-sm"
         @click.self="showTerms = false" x-cloak>

        <div class="bg-white rounded-t-[2.5rem] sm:rounded-[2.5rem] shadow-2xl w-full max-w-2xl max-h-[85vh] overflow-hidden border border-white/20 flex flex-col transform transition-all">
            <div class="h-1.5 w-full bg-gradient-to-r from-[#F07F22] via-[#1D4069] to-[#F07F22]"></div>

            <div class="p-8 pb-4 flex justify-between items-start">
                <div>
                    <h3 class="text-2xl font-black text-[#1D4069] tracking-tight uppercase italic">Terms<span class="text-[#F07F22]">.</span></h3>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mt-1">Version 2.0 | Feb 2026</p>
                </div>
                <button @click="showTerms = false" class="p-2 hover:bg-gray-50 rounded-full transition-colors text-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-8 pt-2 overflow-y-auto space-y-6 text-sm text-gray-600 leading-relaxed no-scrollbar">
                <div class="space-y-4">
                    <section>
                        <h4 class="text-[10px] font-black text-[#1D4069] uppercase tracking-[0.1em] mb-1">1. Scope of Service</h4>
                        <p>VENTIQ provides infrastructure for event registration, ticketing, and analytics. We act as a technology facilitator, not an event organizer.</p>
                    </section>
                    <section>
                        <h4 class="text-[10px] font-black text-[#1D4069] uppercase tracking-[0.1em] mb-1">2. Organizer Obligations</h4>
                        <p>Organizers are solely responsible for the accuracy of event details, pricing, and the fulfillment of services promised to ticket holders.</p>
                    </section>
                    <section>
                        <h4 class="text-[10px] font-black text-[#1D4069] uppercase tracking-[0.1em] mb-1">3. Financial Protocols</h4>
                        <p>All subscription fees are final. VENTI<span class="text-[#F07F22]">Q</span> is not responsible for refunding ticket purchases; these must be handled between the attendee and organizer.</p>
                    </section>
                    <section>
                        <h4 class="text-[10px] font-black text-[#1D4069] uppercase tracking-[0.1em] mb-1">4. Data Integrity</h4>
                        <p>User data is handled according to our privacy standards. Misuse of the platform for fraudulent activities will result in immediate termination.</p>
                    </section>
                    <section class="bg-gray-50 p-6 rounded-[2rem] border border-gray-100 italic font-medium">
                        "Simply Connected" isn't just a tagline; it's our technical standard. By using VENTIQ, you agree to maintain the integrity of the network.
                    </section>
                </div>
            </div>

            <div class="p-6 bg-gray-50/50 border-t border-gray-100">
                <button @click="showTerms = false" class="w-full py-4 rounded-2xl bg-[#1D4069] text-white font-black text-[10px] uppercase tracking-[0.3em] shadow-lg hover:bg-[#F07F22] transition-all">
                    Acknowledge & Close
                </button>
            </div>
        </div>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const loader = document.getElementById('page-loader');
        const player = document.getElementById('loader-lottie');
        let dismissed = false;

        // Has this browser tab already seen VENTIQ boot up once this session?
        const isWarmNavigation = sessionStorage.getItem('ventiq_booted') === '1';

        function dismissLoader(fast = false) {
            if (dismissed) return;
            dismissed = true;
            loader.classList.add('done');
            if (fast) loader.style.transitionDuration = '150ms';
            setTimeout(() => { document.body.style.overflow = ''; }, fast ? 150 : 300);
        }

        if (isWarmNavigation) {
            // Internal navigation — user already knows the app is fast.
            // Skip the theatrics, dismiss almost immediately.
            dismissLoader(true);
        } else {
            // Cold entry — first load this session. Let the lottie play properly.
            sessionStorage.setItem('ventiq_booted', '1');

            if (document.readyState === 'complete') {
                requestAnimationFrame(() => setTimeout(() => dismissLoader(false), 150));
            } else {
                window.addEventListener('load', () => setTimeout(() => dismissLoader(false), 150));
            }

            // Safety net for cold loads only
            setTimeout(() => dismissLoader(false), 900);
        }
    });
</script>
@livewire('upgrade-package-modal')
</body>
</html>