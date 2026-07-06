@extends('layouts.app')

@section('content')

{{--
  ============================================================
  FIELD NAME NOTES — read before editing further
  Your Event model columns are: name, tagline, event_date, venue,
  location, city, category, banner_image, is_public, event_type,
  payment_mode. There is no title, cover_image, district, or
  formatted_time column — this file uses the real names throughout.
  ============================================================
--}}

<div class="w-full bg-white text-gray-900 font-sans selection:bg-[#F07F22]/10 selection:text-[#F07F22]"
     x-data="{ loaded: false }"
     x-init="setTimeout(() => loaded = true, 100)">

    {{-- =========================================================================
         SECTION 1: HERO
         ========================================================================= --}}
    <section class="min-h-[calc(100vh-5rem)] w-full flex flex-col items-center justify-center relative px-6 pt-16 pb-12 overflow-hidden">
        <div class="absolute inset-0 -z-10 overflow-hidden pointer-events-none">
            <div class="absolute top-1/3 left-1/2 -translate-x-1/2 w-[600px] h-[600px] bg-[#F07F22]/2 rounded-full blur-[140px]"></div>
        </div>

        <div class="w-full max-w-7xl flex-1 flex items-center transition-all duration-1000 transform"
             :class="loaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-4 items-center w-full relative">

                <div class="hidden lg:flex lg:col-span-3 flex-col items-center justify-center relative h-[450px] select-none pointer-events-none">
                    <div class="absolute w-52 h-64 rounded-[2.25rem] bg-gray-100 border border-gray-100 shadow-xl transform -rotate-8 -translate-y-6 opacity-60 overflow-hidden">
                        <div class="absolute inset-0 img-skeleton"></div>
                        <img src="{{ asset('images/hero/2.jpg') }}" alt="Moments of Worship"
                             class="relative w-full h-full object-cover grayscale-[20%] contrast-[1.05] opacity-0 img-fade"
                             loading="lazy"
                             onload="this.classList.remove('opacity-0'); this.previousElementSibling.remove();">
                    </div>
                    <div class="absolute w-52 h-64 rounded-[2.25rem] bg-gray-100 border border-white/80 shadow-2xl shadow-gray-200/80 transform -rotate-2 translate-x-3 translate-y-4 overflow-hidden">
                        <div class="absolute inset-0 img-skeleton"></div>
                        <img src="{{ asset('images/hero/sing.jpg') }}" alt="Maseru Market Gathering"
                             class="relative w-full h-full object-cover contrast-[1.05] opacity-0 img-fade"
                             loading="lazy"
                             onload="this.classList.remove('opacity-0'); this.previousElementSibling.remove();">
                    </div>
                </div>

                <div class="col-span-1 lg:col-span-6 flex flex-col items-center justify-center px-4 z-10">
                    <h1 class="text-4xl md:text-5xl lg:text-5xl font-black text-gray-900 tracking-tight leading-[1.1] mb-6 uppercase text-center">
                        Discover<br>What's<br>Happening<span class="text-[#F07F22] font-sans inline-block transform translate-x-0.5 font-black">.</span>
                    </h1>
                    <p class="text-sm md:text-base text-gray-500 font-medium tracking-tight mb-12 max-w-sm text-center italic">
                        Some moments deserve more than a calendar reminder.
                    </p>
                    <div class="flex justify-center mb-16">
                        <a href="{{ route('events.browse') }}"
                           class="inline-flex items-center justify-center gap-2.5 px-10 py-4 rounded-full bg-[#1D4069] hover:bg-[#F07F22] text-white font-bold text-[11px] uppercase tracking-[0.25em] shadow-xl shadow-blue-900/5 transition-all duration-300 transform active:scale-98">
                            <i class="far fa-compass text-xs tracking-normal"></i> Explore Events
                        </a>
                    </div>
                    <div class="w-full max-w-md mx-auto flex flex-wrap justify-center items-center gap-3 px-4 select-none">
                        <div class="px-5 py-2 rounded-full bg-rose-50 border border-rose-100/60 text-rose-700 text-[10px] font-black uppercase tracking-widest shadow-sm transform rotate-3 hover:rotate-0 hover:scale-105 transition-all duration-200 cursor-pointer flex items-center gap-2">
                            <i class="far fa-play-circle text-xs text-rose-600/70"></i> Music
                        </div>
                        <div class="px-5 py-2.5 rounded-[1.25rem] bg-amber-50 border border-amber-100/60 text-amber-800 text-[10px] font-black uppercase tracking-widest shadow-sm transform -rotate-3 hover:rotate-0 hover:scale-105 transition-all duration-200 cursor-pointer flex items-center gap-2">
                            <i class="far fa-map text-xs text-amber-700/70"></i> Markets
                        </div>
                        <div class="px-5 py-1.5 rounded-md bg-emerald-50 border border-emerald-100/60 text-emerald-700 text-[9px] font-black uppercase tracking-widest shadow-sm transform rotate-6 hover:rotate-0 hover:scale-105 transition-all duration-200 cursor-pointer flex items-center gap-2">
                            <i class="far fa-star text-xs text-emerald-600/70"></i> Sports
                        </div>
                        <div class="px-5 py-3 rounded-[1.5rem] bg-indigo-50 border border-indigo-100/40 text-indigo-700 text-[10px] font-black uppercase tracking-widest shadow-md transform -rotate-2 hover:rotate-0 hover:scale-105 transition-all duration-200 cursor-pointer flex items-center gap-2">
                            <i class="far fa-heart text-xs text-indigo-600/70"></i> Worship
                        </div>
                        <div class="px-5 py-2 rounded-tr-none rounded-2xl bg-sky-50 border border-sky-100/60 text-sky-700 text-[10px] font-black uppercase tracking-widest shadow-sm transform rotate-2 hover:rotate-0 hover:scale-105 transition-all duration-200 cursor-pointer flex items-center gap-2">
                            <i class="far fa-lightbulb text-xs text-sky-600/70"></i> Business
                        </div>
                        <div class="px-4 py-3.5 rounded-xl bg-purple-50 border border-purple-100/50 text-purple-700 text-[10px] font-black uppercase tracking-widest shadow-sm transform -rotate-4 hover:rotate-0 hover:scale-105 transition-all duration-200 cursor-pointer flex items-center gap-2">
                            <i class="far fa-eye text-xs text-purple-600/70"></i> Arts
                        </div>
                    </div>
                </div>

                <div class="hidden lg:flex lg:col-span-3 flex-col items-center justify-center relative h-[450px] select-none pointer-events-none">
                    <div class="absolute w-52 h-64 rounded-[2.25rem] bg-gray-100 border border-gray-100 shadow-xl transform rotate-6 -translate-x-4 -translate-y-4 opacity-70 overflow-hidden">
                        <div class="absolute inset-0 img-skeleton"></div>
                        <img src="{{ asset('images/hero/3.jpg') }}" alt="Finish Line Connection"
                             class="relative w-full h-full object-cover grayscale-[10%] contrast-[1.05] opacity-0 img-fade"
                             loading="lazy"
                             onload="this.classList.remove('opacity-0'); this.previousElementSibling.remove();">
                    </div>
                    <div class="absolute w-52 h-64 rounded-[2.25rem] bg-gray-100 border border-white/80 shadow-2xl shadow-gray-200/60 transform -rotate-3 translate-x-4 translate-y-8 overflow-hidden">
                        <div class="absolute inset-0 img-skeleton"></div>
                        <img src="{{ asset('images/hero/4.jpg') }}" alt="Shared Laughter"
                             class="relative w-full h-full object-cover contrast-[1.05] opacity-0 img-fade"
                             loading="lazy"
                             onload="this.classList.remove('opacity-0'); this.previousElementSibling.remove();">
                    </div>
                </div>

            </div>
        </div>

        <div class="w-full flex justify-center mt-8 pointer-events-none select-none">
            <div class="animate-bounce text-gray-300 text-base font-light tracking-widest">↓</div>
        </div>
    </section>


    {{-- =========================================================================
         SECTION 2: SPONSORED EVENTS
         ========================================================================= --}}
    @if(isset($sponsoredEvents) && $sponsoredEvents->isNotEmpty())
        <section class="w-full max-w-7xl mx-auto px-6 py-16 border-t border-gray-100" data-reveal>
            <div class="flex flex-col mb-10">
                <span class="text-[10px] font-black uppercase tracking-[0.3em] text-[#F07F22] mb-1">Featured</span>
                <h2 class="text-2xl font-black uppercase tracking-tight text-gray-950">Sponsored events<span class="text-[#F07F22]">.</span></h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($sponsoredEvents as $event)
                    <a href="{{ $event->public_url ?? '#' }}" class="group block relative bg-white border border-gray-100 rounded-[2rem] overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300">
                        <div class="aspect-[4/3] w-full bg-[#1D4069] relative overflow-hidden">
                            @if($event->banner_image)
                                <div class="absolute inset-0 img-skeleton"></div>
                                <img src="{{ asset('storage/' . $event->banner_image) }}" alt="{{ $event->name }}"
                                     class="relative w-full h-full object-cover group-hover:scale-102 transition-transform duration-500 opacity-0 img-fade"
                                     loading="lazy"
                                     onload="this.classList.remove('opacity-0'); this.previousElementSibling.remove();">
                            @else
                                <div class="absolute inset-0 flex items-center justify-center overflow-hidden select-none pointer-events-none">
                                    <span class="text-white/10 font-black uppercase tracking-tight text-[64px] leading-none whitespace-nowrap transform -rotate-6">VENTIQ</span>
                                </div>
                            @endif
                            <div class="absolute top-4 right-4 px-3 py-1 rounded-full bg-white/90 backdrop-blur-sm text-[9px] font-black uppercase tracking-wider text-[#F07F22] shadow-sm">
                                Premium pass
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center gap-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">
                                <span>{{ $event->event_date?->format('M d, Y') ?? 'TBA' }}</span>
                                <span>•</span>
                                <span>{{ $event->venue ?? $event->city }}</span>
                            </div>
                            <h3 class="text-lg font-black text-gray-900 group-hover:text-[#F07F22] transition-colors duration-200 line-clamp-1 uppercase">
                                {{ $event->name }}
                            </h3>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
        {{-- =========================================================================
         SECTION 3: Discover events
         ========================================================================= --}}
    <section class="w-full py-20 px-6 overflow-hidden" data-reveal
        x-data="{
            collections: [],
            active: 0,
            loaded: false,
            autoplayTimer: null,
            async load(district) {
                try {
                    const params = new URLSearchParams({ district: district || '' });
                    const res = await fetch(`{{ route('events.discover') }}?${params}`);
                    this.collections = await res.json();
                } catch (e) { this.collections = []; }
                this.loaded = true;
                this.restartAutoplay();
            },
            offset(i) {
                let diff = i - this.active;
                const n = this.collections.length;
                if (diff > n / 2) diff -= n;
                if (diff < -n / 2) diff += n;
                return diff;
            },
            goTo(i) {
                this.active = i;
                this.restartAutoplay();
            },
            next() { this.goTo((this.active + 1) % this.collections.length); },
            prev() { this.goTo((this.active - 1 + this.collections.length) % this.collections.length); },
            restartAutoplay() {
                clearInterval(this.autoplayTimer);
                if (this.collections.length > 1) {
                    this.autoplayTimer = setInterval(() => {
                        this.active = (this.active + 1) % this.collections.length;
                    }, 5000);
                }
            }
        }"
        x-init="load(detectedDistrict)"
        @district-changed.window="load($event.detail); active = 0"
        x-show="!loaded || collections.length > 0"
        x-cloak>
    
        <div class="max-w-5xl mx-auto flex items-end justify-between mb-10 px-2">
            <div>
                <span class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-400 mb-1 block">Right now</span>
                <h2 class="text-2xl md:text-3xl font-black uppercase tracking-tight text-gray-950">Discover<span class="text-[#F07F22]">.</span></h2>
            </div>
            <div class="text-xs font-black text-gray-300 tabular-nums" x-show="collections.length > 0">
                <span x-text="String(active + 1).padStart(2, '0')"></span>/<span x-text="String(collections.length).padStart(2, '0')"></span>
            </div>
        </div>
    
        <div class="relative h-72 md:h-80 max-w-5xl mx-auto">
    
            <template x-for="(col, i) in collections" :key="col.key">
                <a :href="col.href"
                x-data="{ imgLoaded: false }"
                class="absolute top-0 left-1/2 w-80 md:w-[26rem] h-full rounded-[1.75rem] shadow-2xl overflow-hidden transition-all duration-500 ease-out"
                :style="`
                        transform: translateX(calc(-50% + ${offset(i) * 56}%)) scale(${offset(i) === 0 ? 1 : (Math.abs(offset(i)) === 1 ? 0.78 : 0.6)});
                        z-index: ${10 - Math.abs(offset(i))};
                        opacity: ${Math.abs(offset(i)) > 2 ? 0 : (offset(i) === 0 ? 1 : (Math.abs(offset(i)) === 1 ? 0.55 : 0.3))};
                        pointer-events: ${Math.abs(offset(i)) > 2 ? 'none' : 'auto'};
                `">
    
                    <div x-show="col.image && !imgLoaded" class="absolute inset-0 img-skeleton"></div>
                    <img x-show="col.image" @load="imgLoaded = true" :src="col.image"
                         class="absolute inset-0 w-full h-full object-cover img-fade"
                         :class="imgLoaded ? 'opacity-100' : 'opacity-0'">
                    <div x-show="!col.image" class="absolute inset-0" :style="`background: linear-gradient(160deg, ${col.color}, ${col.color}cc)`"></div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/15 to-transparent"></div>
    
                    {{-- Active-only: external-link affordance, top-right --}}
                    <div x-show="offset(i) === 0"
                        class="absolute top-4 right-4 w-8 h-8 rounded-full bg-white/15 backdrop-blur-sm flex items-center justify-center">
                        <i class="fas fa-arrow-up-right-from-square text-white text-[10px]"></i>
                    </div>
    
                    <div class="absolute inset-0 flex flex-col justify-end p-6">
                        <p class="text-white font-black uppercase tracking-tight leading-[0.95] whitespace-pre-line drop-shadow-sm transition-all duration-300"
                        :class="offset(i) === 0 ? 'text-2xl md:text-3xl mb-3' : 'text-base md:text-lg mb-0'"
                        x-text="col.label"></p>
    
                        {{-- Detail block: ONLY rendered on the active card.
                            Side cards get nothing past the title — this is
                            what actually solves the overlap, not blur. --}}
                        <div x-show="offset(i) === 0" x-transition:enter="transition ease-out duration-300 delay-100" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                            <p class="text-white/60 text-[11px] font-bold uppercase tracking-widest mb-2.5">
                                <span x-text="col.count"></span> event<span x-show="col.count !== 1">s</span>
                            </p>
                            <ul class="space-y-1 mb-1">
                                <template x-for="name in col.events" :key="name">
                                    <li class="text-white/85 text-[12px] font-semibold truncate" x-text="name"></li>
                                </template>
                            </ul>
                            <p x-show="col.remaining > 0" class="text-white/50 text-[11px] font-bold uppercase tracking-wide mt-1">
                                +<span x-text="col.remaining"></span> more
                            </p>
                        </div>
                    </div>
                </a>
            </template>
    
            <button @click="prev()" x-show="collections.length > 1"
                    class="absolute left-0 md:-left-8 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-white shadow-md flex items-center justify-center text-[#1D4069] hover:bg-[#1D4069] hover:text-white transition-colors">
                <i class="fas fa-chevron-left text-xs"></i>
            </button>
            <button @click="next()" x-show="collections.length > 1"
                    class="absolute right-0 md:-right-8 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-white shadow-md flex items-center justify-center text-[#1D4069] hover:bg-[#1D4069] hover:text-white transition-colors">
                <i class="fas fa-chevron-right text-xs"></i>
            </button>
        </div>
    
        <div class="flex justify-center gap-2 mt-8" x-show="collections.length > 1">
            <template x-for="(col, i) in collections" :key="'dot-'+col.key">
                <button @click="goTo(i)" class="h-1.5 rounded-full transition-all duration-300"
                        :class="active === i ? 'w-6 bg-[#1D4069]' : 'w-1.5 bg-gray-200'"></button>
            </template>
        </div>
    
    </section>
    

    {{-- =========================================================================
         SECTION 3: UPCOMING EVENTS
         Alpine-driven so it can re-filter live when the nav district
         changes, without a full page reload. Starts with whatever the
         server already rendered (no flicker), then refetches from
         /events/upcoming on mount (once geolocation resolves) and on
         every 'district-changed' event dispatched by app.blade.php.
         ========================================================================= --}}
    <section class="w-full max-w-7xl mx-auto px-6 py-16 border-t border-gray-100"
        data-reveal
        x-data="{
            upcoming: {{ \Illuminate\Support\Js::from($upcomingEvents->map(fn ($e) => [
                'name' => $e->name,
                'venue' => $e->venue,
                'city' => $e->city,
                'category_label' => $e->category_label,
                'category_color' => $e->category_color,
                'organizer' => $e->organization->name ?? null,
                'date' => $e->event_date?->format('M d'),
                'time' => $e->event_date?->format('g:i A'),
                'image' => $e->banner_image ? asset('storage/' . $e->banner_image) : null,
                'url' => $e->public_url,
            ])) }},
            loadingUpcoming: false,
            currentDistrict: null,

            async loadUpcoming(district) {
                if (district === this.currentDistrict) return;
                this.currentDistrict = district;
                this.loadingUpcoming = true;
                try {
                    const params = new URLSearchParams({ district: district || '' });
                    const res = await fetch(`{{ route('events.upcoming') }}?${params}`);
                    this.upcoming = await res.json();
                } catch (e) {
                    // Network hiccup — keep whatever was already showing
                } finally {
                    this.loadingUpcoming = false;
                }
            }
        }"
        x-init="loadUpcoming(detectedDistrict)"
        @district-changed.window="loadUpcoming($event.detail)">

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-6 mb-12">
            <div>
                <span class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-400 mb-1">Live feed</span>
                <h2 class="text-2xl md:text-3xl font-black uppercase tracking-tight text-gray-950">
                    Happening in <span x-text="currentDistrict || detectedDistrict"></span><span class="text-[#F07F22]">.</span>
                </h2>
            </div>
            <a href="{{ route('events.browse') }}" class="inline-flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-[#1D4069] hover:text-[#F07F22] transition-colors duration-200 group">
                Explore all events <span class="transform group-hover:translate-x-1 transition-transform duration-200">→</span>
            </a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-5"
             :class="loadingUpcoming ? 'opacity-50 pointer-events-none' : ''"
             style="transition: opacity 0.2s ease;">

            <template x-if="upcoming.length === 0 && !loadingUpcoming">
                <div class="col-span-full py-16 text-center border border-dashed border-gray-200 rounded-[2rem]">
                    <p class="text-sm font-medium text-gray-400 italic">No scheduled calendar moments right now. Check back shortly.</p>
                </div>
            </template>

            <template x-for="event in upcoming" :key="event.url + event.name">
                <a :href="event.url" x-data="{ imgLoaded: false }" class="group block bg-white rounded-[1.5rem] border border-gray-100 overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300">
                    <div class="aspect-[4/3] w-full bg-gray-50 overflow-hidden relative">
                        <div x-show="event.image && !imgLoaded" class="absolute inset-0 img-skeleton"></div>
                        <img x-show="event.image" @load="imgLoaded = true" :src="event.image"
                             class="w-full h-full object-cover group-hover:scale-102 transition-transform duration-500 img-fade"
                             :class="imgLoaded ? 'opacity-100' : 'opacity-0'">
                        <div x-show="!event.image" class="w-full h-full bg-[#1D4069] relative overflow-hidden flex items-center justify-center">
                            <span class="text-white/10 font-black uppercase tracking-tight text-[32px] leading-none whitespace-nowrap transform -rotate-6 select-none pointer-events-none">VENTIQ</span>
                        </div>

                        <span class="absolute top-2.5 left-2.5 px-2.5 py-1 rounded-full text-[8px] font-black uppercase tracking-wider text-white shadow-sm"
                              :style="`background-color: ${event.category_color || '#1D4069'}`"
                              x-text="event.category_label"
                              x-show="event.category_label"></span>
                    </div>
                    <div class="p-3.5">
                        <div class="flex items-center gap-1.5 text-[9px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">
                            <span class="text-[#F07F22]" x-text="event.date || 'TBA'"></span>
                            <template x-if="event.time"><span>•</span></template>
                            <span x-show="event.time" x-text="event.time"></span>
                        </div>
                        <h3 class="text-[13px] font-black text-gray-900 uppercase tracking-tight line-clamp-2 leading-snug group-hover:text-[#1D4069] transition-colors duration-200 mb-1.5"
                            x-text="event.name"></h3>
                        <p class="text-[10px] text-gray-400 font-medium truncate" x-show="event.venue || event.city">
                            <span x-text="[event.venue, event.city].filter(Boolean).join(' · ')"></span>
                        </p>
                        <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wide truncate mt-1" x-show="event.organizer">
                            By <span x-text="event.organizer" class="text-gray-500"></span>
                        </p>
                    </div>
                </a>
            </template>
        </div>
    </section>


    {{-- =========================================================================
         SECTION 4: MONTHLY RHYTHM METRICS
         ========================================================================= --}}
    <section class="w-full bg-gray-50/60 border-y border-gray-100 py-12 my-8" data-reveal>
        <div class="w-full max-w-7xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-8 text-center md:text-left">
            <div class="flex flex-col justify-center items-center md:items-start border-r border-gray-200/60 last:border-none">
                <span class="text-2xl font-black text-gray-900 uppercase tracking-tight">{{ now()->format('F') }}</span>
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">Current rhythm</span>
            </div>
            <div class="flex flex-col justify-center items-center md:items-start border-r border-gray-200/60 last:border-none">
                <span class="text-2xl font-black text-[#1D4069]">{{ $metrics['total_events'] ?? 0 }}</span>
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">Live gatherings</span>
            </div>
            <div class="flex flex-col justify-center items-center md:items-start border-r border-gray-200/60 last:border-none">
                <span class="text-2xl font-black text-[#F07F22]">{{ $metrics['total_cities'] ?? 0 }}</span>
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">Active districts</span>
            </div>
            <div class="flex flex-col justify-center items-center md:items-start">
                <span class="text-2xl font-black text-emerald-700">{{ $metrics['total_categories'] ?? 0 }}</span>
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">Bespoke spaces</span>
            </div>
        </div>
    </section>


    {{-- =========================================================================
         SECTION 5: HOW IT WORKS
         ========================================================================= --}}
    <section class="w-full bg-[#1D4069] text-white py-24 px-6 relative overflow-hidden" data-reveal>
        <div class="absolute inset-0 opacity-10 pointer-events-none">
            <div class="absolute -top-1/4 -right-1/4 w-96 h-96 bg-[#F07F22] rounded-full blur-3xl"></div>
        </div>

        <div class="w-full max-w-5xl mx-auto relative z-10">
            <div class="flex flex-col mb-20 items-center text-center">
                <span class="text-[10px] font-black uppercase tracking-[0.3em] text-[#F07F22] mb-2">The flow</span>
                <h2 class="text-2xl md:text-3xl font-black uppercase tracking-tight text-white">How VENTIQ works<span class="text-[#F07F22]">.</span></h2>
            </div>

            <div class="relative flex flex-col md:flex-row items-center justify-center gap-10 md:gap-4">

                <div class="w-40 h-52 shrink-0 bg-[#1D4069] rounded-r-lg rounded-l-sm shadow-xl transform -rotate-6 relative flex flex-col justify-end p-4 border-l-4 border-[#0F2A47]">
                    <i class="far fa-compass text-xl text-[#F07F22] mb-3"></i>
                    <p class="text-xs font-black uppercase tracking-widest text-white leading-snug">Find an event</p>
                </div>

                <svg class="hidden md:block w-16 h-10 text-white/25 shrink-0" viewBox="0 0 64 40" fill="none">
                    <path d="M2 10 C 20 -4, 40 32, 60 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M52 14 L60 18 L54 26" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                </svg>

                <div class="w-40 h-60 shrink-0 bg-[#F07F22] rounded-r-lg rounded-l-sm shadow-2xl transform rotate-3 -translate-y-3 relative flex flex-col justify-end p-4 border-l-4 border-[#B85C12]">
                    <i class="far fa-calendar-check text-xl text-white mb-3"></i>
                    <p class="text-xs font-black uppercase tracking-widest text-white leading-snug">Reserve your space</p>
                </div>

                <svg class="hidden md:block w-16 h-10 text-white/25 shrink-0" viewBox="0 0 64 40" fill="none">
                    <path d="M2 24 C 22 38, 42 2, 60 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M50 6 L60 12 L52 20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                </svg>

                <div class="w-40 h-52 shrink-0 bg-white/10 border border-white/15 rounded-r-lg rounded-l-sm shadow-xl transform rotate-6 relative flex flex-col justify-end p-4 border-l-4 border-l-white/25">
                    <i class="far fa-images text-xl text-[#F07F22] mb-3"></i>
                    <p class="text-xs font-black uppercase tracking-widest text-white leading-snug">Keep the memory</p>
                </div>

            </div>

            <div class="mt-20 flex justify-center">
                <a href="{{ route('about') }}" class="px-8 py-3 rounded-full border border-white/20 hover:border-white text-white font-bold text-[10px] uppercase tracking-widest transition-all duration-300">
                    Our philosophy
                </a>
            </div>
        </div>
    </section>


    {{-- =========================================================================
         SECTION 6: COMMUNITY MEMORIES
         ========================================================================= --}}
    <section class="w-full py-20 overflow-hidden bg-white" data-reveal>
        <div class="w-full max-w-7xl mx-auto px-6 flex flex-col mb-12 items-center text-center">
            <span class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-400 mb-1">Coming Soon...</span>
            <h2 class="text-2xl font-black uppercase tracking-tight text-gray-950">Moments from the community<span class="text-[#F07F22]">.</span></h2>
        </div>

        <div class="w-full max-w-7xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 select-none pointer-events-none">
            <div class="aspect-[3/4] rounded-[2rem] bg-gray-50 overflow-hidden transform rotate-2 border border-gray-100 shadow-sm transition-transform duration-300 hover:rotate-0 relative">
                <div class="absolute inset-0 img-skeleton"></div>
                <img src="{{ asset('images/hero/3.jpg') }}" alt=""
                     class="relative w-full h-full object-cover grayscale-[10%] opacity-0 img-fade"
                     loading="lazy"
                     onload="this.classList.remove('opacity-0'); this.previousElementSibling.remove();">
            </div>
            <div class="aspect-[3/4] rounded-[2rem] bg-gray-50 overflow-hidden transform -rotate-3 translate-y-4 border border-gray-100 shadow-sm transition-transform duration-300 hover:rotate-0 relative">
                <div class="absolute inset-0 img-skeleton"></div>
                <img src="{{ asset('images/hero/4.jpg') }}" alt=""
                     class="relative w-full h-full object-cover opacity-0 img-fade"
                     loading="lazy"
                     onload="this.classList.remove('opacity-0'); this.previousElementSibling.remove();">
            </div>
            <div class="aspect-[3/4] rounded-[2rem] bg-gray-50 overflow-hidden transform rotate-1 border border-gray-100 shadow-sm transition-transform duration-300 hover:rotate-0 relative">
                <div class="absolute inset-0 img-skeleton"></div>
                <img src="{{ asset('images/hero/sing.jpg') }}" alt=""
                     class="relative w-full h-full object-cover contrast-[1.05] opacity-0 img-fade"
                     loading="lazy"
                     onload="this.classList.remove('opacity-0'); this.previousElementSibling.remove();">
            </div>
            <div class="aspect-[3/4] rounded-[2rem] bg-gray-50 overflow-hidden transform -rotate-2 translate-y-2 border border-gray-100 shadow-sm transition-transform duration-300 hover:rotate-0 relative">
                <div class="absolute inset-0 img-skeleton"></div>
                <img src="{{ asset('images/hero/2.jpg') }}" alt=""
                     class="relative w-full h-full object-cover grayscale-[15%] opacity-0 img-fade"
                     loading="lazy"
                     onload="this.classList.remove('opacity-0'); this.previousElementSibling.remove();">
            </div>
        </div>
    </section>


    {{-- =========================================================================
         SECTION 7: ORGANIZER CTA
         ========================================================================= --}}
    <section class="w-full max-w-5xl mx-auto px-6 py-16 mb-8 border-t border-gray-100" data-reveal>
        <div class="rounded-[2.5rem] bg-gray-50 border border-gray-200/60 p-8 md:p-12 flex flex-col md:flex-row items-center justify-between gap-8">
            <div class="max-w-md text-center md:text-left">
                <h3 class="text-lg font-black uppercase tracking-tight text-gray-950 mb-2">Running an event?</h3>
                <p class="text-xs md:text-sm text-gray-500 font-medium tracking-tight leading-relaxed">
                    Everything you need to organize registrations, tickets, secure check-ins and reports.
                </p>
            </div>
            <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center px-8 py-3.5 rounded-full bg-[#1D4069] hover:bg-[#F07F22] text-white font-bold text-[10px] uppercase tracking-widest shadow-md transition-all duration-300 transform active:scale-98 whitespace-nowrap">
                Learn More
            </a>
        </div>
    </section>

</div>

{{-- ============================================================
     SCROLL REVEAL INITIALIZER
============================================================ --}}
<script>
  (function () {
    function initReveal() {
      const els = document.querySelectorAll('[data-reveal]');
      const io = new IntersectionObserver((entries) => {
        entries.forEach(e => {
          if (e.isIntersecting) {
            e.target.classList.add('revealed');
            io.unobserve(e.target);
          }
        });
      }, { threshold: 0.12 });
      els.forEach(el => io.observe(el));
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initReveal);
    } else {
      initReveal();
    }
  })();
</script>

<style>
  [data-reveal] {
    opacity: 0;
    transform: translateY(28px);
    transition: opacity 0.65s cubic-bezier(.22,1,.36,1),
                transform 0.65s cubic-bezier(.22,1,.36,1);
  }
  [data-reveal].revealed {
    opacity: 1;
    transform: none;
  }

  /* ============================================================
     IMAGE LOADING — skeleton + fade-in.
     Every <img> that could be slow (uploaded banners) or is just
     loading normally stays fully transparent until its onload/@load
     fires, so the browser's progressive JPEG paint is never visible.
     A shimmering skeleton sits underneath in the meantime and is
     removed once the real image is ready.
     ============================================================ */
  .img-skeleton {
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 37%, #f1f5f9 63%);
    background-size: 400% 100%;
    animation: img-shimmer 1.4s ease infinite;
  }
  @keyframes img-shimmer {
    0% { background-position: 100% 50%; }
    100% { background-position: 0 50%; }
  }
  .img-fade {
    transition: opacity 0.4s ease;
  }
</style>

@endsection