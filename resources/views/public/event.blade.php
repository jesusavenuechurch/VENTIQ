<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $event->name }} - {{ $organization->name }}</title>
    
    <meta property="og:title" content="{{ $event->name }}">
    <meta property="og:description" content="{{ Str::limit($event->description, 150) }}">
    <meta property="og:image" content="{{ Storage::url($event->banner_image) }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">

    @vite('resources/css/app.css')
    <style>
        body { font-family: 'Inter', sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        
        .ticket-stub { position: relative; transition: all 0.2s ease-in-out; }
        @media (min-width: 1024px) {
            .ticket-stub::before, .ticket-stub::after {
                content: ''; position: absolute; top: 50%; width: 10px; height: 10px;
                background: #FBFBFC; border-radius: 50%; transform: translateY(-50%); z-index: 10;
                border: 1px solid #e5e7eb;
            }
            .ticket-stub::before { left: -6px; }
            .ticket-stub::after { right: -6px; }
        }
    </style>
</head>
<body class="bg-[#FBFBFC] text-[#1D4069] antialiased">

    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-gray-100">
        <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ url('/') }}" class="text-xl font-black tracking-tighter hover:text-[#F07F22] transition-colors cursor-pointer">V.</a>
                <div class="h-4 w-[1px] bg-gray-200"></div>
                <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">{{ $organization->name }}</span>
            </div>
            <a href="{{ route('public.events', $organization->slug) }}" class="text-[10px] font-bold uppercase tracking-widest text-gray-400 hover:text-[#1D4069]">Directory</a>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-6 py-10 pb-32 lg:pb-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
            
            <div class="lg:col-span-7 space-y-8">
                <button onclick="window.history.back()" class="group flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 hover:text-[#1D4069] transition-colors">
                    <i class="fas fa-arrow-left transition-transform group-hover:-translate-x-1"></i>
                    <span>Back</span>
                </button>

                <div class="space-y-6">
                    <h1 class="text-5xl font-black tracking-tighter uppercase italic leading-[0.85]">{{ $event->name }}</h1>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-5 bg-white border border-gray-100 rounded-2xl shadow-sm">
                            <div class="flex items-center gap-3 mb-2">
                                <i class="far fa-calendar-alt text-[#F07F22]"></i>
                                <span class="text-[9px] font-black uppercase tracking-widest text-gray-400">Schedule</span>
                            </div>
                            <p class="text-sm font-bold text-[#1D4069]">{{ $event->event_date->format('l, d M Y') }}</p>
                            <p class="text-[10px] text-gray-400 font-medium italic mt-1">Starting @ {{ $event->event_date->format('g:i A') }}</p>
                        </div>

                        <div class="p-5 bg-white border border-gray-100 rounded-2xl shadow-sm">
                            <div class="flex items-center gap-3 mb-2">
                                <i class="fas fa-map-marker-alt text-[#1D4069]"></i>
                                <span class="text-[9px] font-black uppercase tracking-widest text-gray-400">Venue</span>
                            </div>
                            <p class="text-sm font-bold text-[#1D4069] leading-tight">{{ $event->venue }}</p>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-100">
                        <h3 class="text-[9px] font-black uppercase tracking-[0.3em] text-gray-300 mb-4">Briefing</h3>
                        <div class="text-sm text-gray-500 leading-relaxed space-y-4">
                            {!! nl2br(e($event->description)) !!}
                        </div>
                    </div>

                    @if($event->banner_image)
                    <div class="lg:hidden pt-10 flex flex-col items-center gap-3">
                        <div class="bg-white p-1.5 rounded-2xl border border-gray-100 shadow-sm">
                            <img src="{{ Storage::url($event->banner_image) }}" class="h-40 w-auto rounded-xl object-contain">
                        </div>
                        <span class="text-[8px] font-black uppercase tracking-[0.4em] text-gray-300 italic">Official Event Graphic</span>
                    </div>
                    @endif
                </div>
            </div>

            <div class="hidden lg:block lg:col-span-5">
                <div class="sticky top-24 space-y-6">
                    
                    <div class="bg-white border border-gray-200 rounded-[2rem] p-8 shadow-sm">
                        <h3 class="text-[9px] font-black uppercase tracking-[0.3em] text-center text-gray-300 mb-8">Access Selection</h3>
                        
                        <div class="space-y-3">
                            @foreach($event->tiers as $tier)
                            @php
                                $availability = $tierAvailability[$tier->id] ?? ['is_sold_out' => false];
                                $isSoldOut = $availability['is_sold_out'];
                            @endphp
                            <button onclick="{{ $isSoldOut ? '' : 'selectTier(' . $tier->id . ')' }}" 
                                    {{ $isSoldOut ? 'disabled' : '' }}
                                    class="ticket-stub group block w-full bg-slate-50 border {{ $isSoldOut ? 'border-gray-100 opacity-60 cursor-not-allowed' : 'border-transparent hover:border-[#1D4069] hover:bg-white cursor-pointer' }} rounded-xl p-5 transition-all text-left">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="pr-4 flex-1">
                                        <span class="block text-[9px] font-black text-[#F07F22] uppercase tracking-[0.15em]">{{ $tier->tier_name }}</span>
                                        <span class="block text-[10px] text-gray-400 italic mt-0.5 line-clamp-1">{{ $tier->description ?? 'Secure Entry' }}</span>
                                    </div>
                                    <span class="text-lg font-black tracking-tighter whitespace-nowrap">M{{ number_format($tier->price) }}</span>
                                </div>

                                @if($event->allow_installments && $tier->price > 0 && !$isSoldOut)
                                    <div class="mt-3 p-2.5 bg-emerald-50 rounded-lg border border-emerald-100 flex items-center justify-between">
                                        <span class="text-[9px] font-black text-emerald-700 uppercase tracking-tight">Deposit Option</span>
                                        <span class="text-[9px] font-bold text-emerald-600 bg-white px-2 py-0.5 rounded shadow-sm">
                                            From M{{ number_format($tier->price * ($event->minimum_deposit_percentage / 100)) }}
                                        </span>
                                    </div>
                                @endif

                                @if($isSoldOut)
                                    <div class="mt-3 w-full py-2 bg-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest text-center rounded-lg italic">
                                        Sold Out
                                    </div>
                                @endif
                            </button>
                            @endforeach
                        </div>

                        <div class="mt-8 pt-6 border-t border-gray-50 space-y-3">
                            <a href="{{ route('installment.search') }}" class="block text-center px-5 py-3 bg-amber-50 hover:bg-amber-100 border border-amber-100 rounded-xl transition-all group">
                                <div class="flex items-center justify-center gap-2">
                                    <i class="fas fa-search-dollar text-[#F07F22] text-sm"></i>
                                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-700">Find My Ticket</span>
                                </div>
                                <p class="text-[9px] text-amber-600 mt-1 font-medium">Complete installment payment</p>
                            </a>
                        </div>
                    </div>

                    @if($event->banner_image)
                    <div class="flex flex-col items-center gap-3 px-8">
                        <div class="p-1.5 bg-white rounded-2xl border border-gray-100 shadow-sm transition-transform hover:scale-105 duration-300">
                            <img src="{{ Storage::url($event->banner_image) }}" alt="Event Badge" class="h-48 w-auto rounded-xl object-contain">
                        </div>
                        <span class="text-[8px] font-black uppercase tracking-[0.4em] text-gray-300 italic text-center">Official Event Identity</span>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </main>

    {{-- Mobile Drawer --}}
    <div class="lg:hidden fixed bottom-8 left-1/2 -translate-x-1/2 z-50 w-auto">
        <button onclick="toggleDrawer()" class="bg-[#1D4069] text-white px-10 py-5 rounded-full font-black text-[10px] uppercase tracking-[0.3em] shadow-2xl flex items-center gap-3 active:scale-95 transition-all">
            <i class="fas fa-ticket-alt text-[#F07F22]"></i>
            Get Access
        </button>
    </div>

    <div id="drawerOverlay" class="fixed inset-0 bg-[#1D4069]/40 backdrop-blur-sm z-[60] hidden opacity-0 transition-opacity duration-300" onclick="toggleDrawer()">
        <div id="drawerContent" class="absolute bottom-0 left-0 right-0 bg-white rounded-t-[2.5rem] p-8 translate-y-full transition-transform duration-300" onclick="event.stopPropagation()">
            <div class="w-10 h-1 bg-gray-100 rounded-full mx-auto mb-8"></div>
            <div class="space-y-3 max-h-[60vh] overflow-y-auto no-scrollbar pb-10">
                @foreach($event->tiers as $tier)
                @php
                    $availability = $tierAvailability[$tier->id] ?? ['is_sold_out' => false];
                    $isSoldOut = $availability['is_sold_out'];
                @endphp
                <button onclick="{{ $isSoldOut ? '' : 'selectTier(' . $tier->id . ')' }}" 
                        {{ $isSoldOut ? 'disabled' : '' }}
                        class="w-full p-5 rounded-xl bg-gray-50 border {{ $isSoldOut ? 'border-gray-100 opacity-60' : 'border-gray-100 active:bg-gray-200' }} transition-colors">
                    <div class="flex items-center justify-between {{ $isSoldOut ? '' : 'mb-3' }}">
                        <div class="text-left flex-1">
                            <span class="block text-[9px] font-black text-[#F07F22] uppercase tracking-[0.1em]">{{ $tier->tier_name }}</span>
                            <span class="block text-[10px] text-gray-400 italic">{{ $tier->description ?? 'Secure Entry' }}</span>
                        </div>
                        <span class="text-lg font-black tracking-tighter ml-4">M{{ number_format($tier->price) }}</span>
                    </div>

                    @if($event->allow_installments && $tier->price > 0 && !$isSoldOut)
                        <div class="mt-3 p-2 bg-emerald-50 rounded-lg border border-emerald-100 flex items-center justify-between">
                            <span class="text-[9px] font-black text-emerald-700 uppercase">Deposit</span>
                            <span class="text-[9px] font-bold text-emerald-600 bg-white px-2 py-0.5 rounded">
                                From M{{ number_format($tier->price * ($event->minimum_deposit_percentage / 100)) }}
                            </span>
                        </div>
                    @endif

                    @if($isSoldOut)
                        <div class="mt-3 w-full py-2 bg-gray-100 text-gray-400 font-black text-[10px] uppercase text-center rounded-lg italic">
                            Sold Out
                        </div>
                    @endif
                </button>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        function toggleDrawer() {
            const overlay = document.getElementById('drawerOverlay');
            const content = document.getElementById('drawerContent');
            if (overlay.classList.contains('hidden')) {
                overlay.classList.remove('hidden');
                setTimeout(() => { 
                    overlay.classList.add('opacity-100'); 
                    content.classList.remove('translate-y-full'); 
                }, 10);
                document.body.style.overflow = 'hidden';
            } else {
                overlay.classList.remove('opacity-100'); 
                content.classList.add('translate-y-full');
                setTimeout(() => overlay.classList.add('hidden'), 300);
                document.body.style.overflow = 'auto';
            }
        }
        
        function selectTier(tierId) {
            window.location.href = `/register/{{ $organization->slug }}/{{ $event->slug }}?tier=${tierId}`;
        }
    </script>
@include('partials.cookie-notice')
</body>
</html>