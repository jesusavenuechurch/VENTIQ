@extends('layouts.app')

@section('content')

<div class="min-h-full w-full flex items-center justify-center relative px-4 py-8"
     x-data="{
         loaded: false,
         showHostModal: false,
         showAgentModal: false,
     }"
     x-init="setTimeout(() => loaded = true, 100)">

    {{-- Background atmosphere --}}
    <div class="absolute inset-0 -z-10 overflow-hidden pointer-events-none">
        <div class="absolute top-[-10%] left-[-5%] w-[500px] h-[500px] bg-[#1D4069]/8 rounded-full blur-[100px]"></div>
        <div class="absolute bottom-[0%] right-[-5%] w-[400px] h-[400px] bg-[#F07F22]/8 rounded-full blur-[100px]"></div>
        <div class="absolute top-[40%] left-[30%] w-[300px] h-[300px] bg-[#1D4069]/4 rounded-full blur-[80px]"></div>
    </div>

    <div class="w-full max-w-5xl transition-all duration-700 transform"
         :class="loaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'">

        <div class="grid lg:grid-cols-2 gap-12 lg:gap-20 items-center">

            {{-- ===================== LEFT: Hero ===================== --}}
            <div class="text-center lg:text-left">

                <span class="inline-block px-3 py-1 rounded-full bg-[#1D4069]/5 text-[#1D4069] text-[10px] font-black uppercase tracking-widest mb-5">
                    Event Intelligence
                </span>

                <h1 class="text-5xl md:text-6xl lg:text-7xl font-black text-gray-900 tracking-tight leading-[0.9] mb-6 italic">
                    Simply<br>
                    <span class="text-[#F07F22]">Connected.</span>
                </h1>

                <p class="text-sm md:text-base text-gray-500 font-medium leading-relaxed mb-8 max-w-sm mx-auto lg:mx-0">
                    The modern gateway for workshops, events, and seamless registrations in Lesotho.
                </p>

                {{-- Social proof — desktop only --}}
                <div class="hidden lg:flex items-center gap-4 mb-8">
                    <div class="flex -space-x-2.5">
                        <div class="w-9 h-9 rounded-full border-2 border-white bg-gray-200"></div>
                        <div class="w-9 h-9 rounded-full border-2 border-white bg-gray-300"></div>
                        <div class="w-9 h-9 rounded-full border-2 border-white bg-[#1D4069] flex items-center justify-center text-[8px] text-white font-black">{{ $activeOrgs > 99 ? '99+' : $activeOrgs }}</div>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Active Organizers</p>
                        <p class="text-[10px] text-gray-300 font-medium">across Lesotho</p>
                    </div>
                </div>

                {{-- Support link — desktop --}}
                <div class="hidden lg:flex items-center gap-6">
                    <button @click="$dispatch('contact-open')"
                            class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-[#1D4069] transition-colors">
                        Support
                    </button>
                    <span class="text-gray-200">·</span>
                    <p class="text-xs text-gray-400 font-medium">
                        Already using Ventiq?
                        <a href="{{ route('filament.admin.auth.login') }}" class="text-[#1D4069] font-black hover:underline ml-1">Log in</a>
                    </p>
                </div>

            </div>

            {{-- ===================== RIGHT: Cards ===================== --}}
            <div class="flex flex-col gap-3 w-full max-w-sm mx-auto lg:max-w-none">

                {{-- Find Event --}}
                <a href="{{ route('events.browse') }}"
                   class="group p-6 rounded-[1.75rem] bg-[#1D4069] text-white shadow-xl shadow-blue-900/20 active:scale-[0.98] transition-all relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-white/5 to-transparent pointer-events-none"></div>
                    <div class="flex justify-between items-center relative z-10">
                        <div>
                            <h2 class="text-lg font-black uppercase tracking-tight">Attend an Event</h2>
                            <p class="text-xs text-blue-100/60 font-medium mt-0.5">Discover &amp; register for upcoming events</p>
                        </div>
                        <div class="bg-white/10 p-3 rounded-xl group-hover:bg-[#F07F22] transition-colors flex-shrink-0 ml-4">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>
                </a>

                {{-- Host Event --}}
                <button @click="showHostModal = true" type="button"
                        class="group w-full text-left p-6 rounded-[1.75rem] bg-white border-2 border-[#F07F22]/10 hover:border-[#F07F22]/30 shadow-sm hover:shadow-lg active:scale-[0.98] transition-all relative">
                    <div class="absolute -top-3 -right-3 bg-[#F07F22] text-white text-[9px] font-black px-2.5 py-1 rounded-lg shadow-md rotate-3 group-hover:rotate-6 transition-transform uppercase tracking-wide">
                        Free Trial
                    </div>
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-lg font-black text-gray-900 uppercase tracking-tight">Organize an Event</h2>
                            <p class="text-xs font-semibold text-[#F07F22] mt-0.5">Manage registrations, payments & attendance</p>
                        </div>
                        <div class="bg-[#F07F22]/10 p-3 rounded-xl group-hover:bg-[#F07F22] group-hover:text-white text-[#F07F22] transition-all flex-shrink-0 ml-4">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                            </svg>
                        </div>
                    </div>
                </button>

                {{-- Pricing + Agent --}}
                <div class="grid grid-cols-2 gap-3">

                    <a href="/pricing"
                       class="group p-5 rounded-[1.5rem] bg-gray-50 hover:bg-white border border-transparent hover:border-gray-200 transition-all">
                        <div class="p-2 rounded-xl bg-gray-100 text-gray-400 group-hover:text-[#1D4069] group-hover:bg-[#1D4069]/10 transition-colors w-fit mb-3">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-black text-gray-800 uppercase tracking-tight group-hover:text-[#1D4069]">Pricing</p>
                        <p class="text-[10px] text-gray-400 font-medium mt-0.5">Compare plans</p>
                    </a>

                    {{-- Agent Card --}}
                    <button @click="showAgentModal = true" type="button"
                       class="group p-5 rounded-[1.5rem] bg-[#1D4069] hover:bg-[#0d2d4d] transition-all relative overflow-hidden text-left w-full active:scale-[0.98]">
                        {{-- Background decoration --}}
                        <div class="absolute -bottom-4 -right-4 w-20 h-20 bg-[#F07F22]/10 rounded-full pointer-events-none"></div>
                        <div class="absolute -top-4 -left-4 w-16 h-16 bg-white/5 rounded-full pointer-events-none"></div>
                        <div class="p-2 rounded-xl bg-[#F07F22]/20 text-[#F07F22] w-fit mb-3 relative z-10 group-hover:bg-[#F07F22] group-hover:text-white transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-black text-white uppercase tracking-tight relative z-10">Partner Up</p>
                        <p class="text-[10px] text-[#F07F22] font-black mt-0.5 relative z-10">Earn 20% commission</p>
                    </button>

                </div>

                {{-- Mobile bottom links --}}
                <div class="lg:hidden flex items-center justify-center gap-5 pt-1">
                    <button @click="$dispatch('contact-open')"
                            class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-[#1D4069] transition-colors">
                        Support
                    </button>
                    <span class="text-gray-200">·</span>
                    <p class="text-xs text-gray-400 font-medium">
                        Already using Ventiq?
                        <a href="{{ route('filament.admin.auth.login') }}" class="text-[#1D4069] font-black hover:underline ml-1">Log in</a>
                    </p>
                </div>

            </div>
        </div>
    </div>

    {{-- ================================================================
         Host Event Modal
    ================================================================ --}}
    <div x-show="showHostModal"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-[#1D4069]/60 backdrop-blur-md"
         @click.self="showHostModal = false">

        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-lg overflow-hidden"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0">

            <div class="h-1 bg-gradient-to-r from-[#1D4069] via-[#F07F22] to-[#1D4069]"></div>

            <div class="p-8 md:p-10 text-center">
                <div class="w-16 h-16 bg-orange-50 text-[#F07F22] rounded-2xl flex items-center justify-center mx-auto mb-5 rotate-3">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>

                <h3 class="text-2xl font-black text-[#1D4069] tracking-tight italic mb-3">
                    Set up your <span class="text-[#F07F22]">Organizer Account</span>
                </h3>

                <p class="text-sm text-gray-500 font-medium mb-6 leading-relaxed">
                    To create and manage events, you'll need an organizer account.
                    Quick to set up — usually about 2 minutes, no card required.
                </p>

                <div class="bg-gray-50 rounded-2xl p-4 mb-6 text-left space-y-2.5">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">What you get</p>
                    @foreach(['Configurable registration tiers', 'QR code access control', 'Registration & payment tracking', 'Participation & reporting dashboard'] as $feature)
                    <div class="flex items-center gap-2.5 text-xs text-gray-600 font-medium">
                        <div class="w-4 h-4 rounded-full bg-[#F07F22]/15 text-[#F07F22] flex items-center justify-center flex-shrink-0">
                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        {{ $feature }}
                    </div>
                    @endforeach
                </div>

                <div class="space-y-3">
                    <a href="{{ route('org.register.direct') }}"
                       class="block w-full py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white font-black text-xs uppercase tracking-[0.2em] shadow-lg transition-all">
                        Create Organizer Account
                    </a>
                    <button @click="showHostModal = false"
                            class="block w-full py-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest hover:text-gray-600 transition-colors">
                        I'm just browsing
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Chat modal lives in layouts/app.blade.php --}}

    {{-- ================================================================
         Agent Modal — VENTIQ colors
    ================================================================ --}}
    <div x-show="showAgentModal"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-[#1D4069]/60 backdrop-blur-md"
         @click.self="showAgentModal = false">

        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-lg overflow-hidden"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0">

            <div class="h-1 bg-gradient-to-r from-[#1D4069] via-[#F07F22] to-[#1D4069]"></div>

            <div class="p-8 md:p-10">

                {{-- Header --}}
                <div class="flex items-start gap-4 mb-6">
                    <div class="w-14 h-14 bg-[#1D4069]/8 text-[#1D4069] rounded-2xl flex items-center justify-center flex-shrink-0 rotate-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-2xl font-black text-[#1D4069] tracking-tight italic leading-tight">
                            Grow VENTIQ.<br>
                            <span class="text-[#F07F22]">Earn while you do.</span>
                        </h3>
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-1">Agent Partnership Program</p>
                    </div>
                </div>

                {{-- What it means --}}
                <p class="text-sm text-gray-500 font-medium leading-relaxed mb-6">
                    As a VENTIQ Agent, you introduce event organizers to the platform. Every time someone you refer buys a package, you earn a 20% commission — tracked automatically.
                </p>

                {{-- Commission breakdown --}}
                <div class="bg-[#1D4069]/4 rounded-2xl p-4 mb-6">
                    <p class="text-[10px] font-black text-[#1D4069] uppercase tracking-widest mb-3">Your earnings per package sold</p>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach([['M250', 'M50', 'Starter'], ['M600', 'M120', 'Standard'], ['M1,200', 'M240', 'Professional']] as $tier)
                        <div class="bg-white rounded-xl p-3 text-center shadow-sm">
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wide">{{ $tier[2] }}</p>
                            <p class="text-[10px] text-gray-300 font-medium line-through mt-0.5">{{ $tier[0] }}</p>
                            <p class="text-lg font-black text-[#F07F22] leading-none mt-1">{{ $tier[1] }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- How it works --}}
                <div class="space-y-2.5 mb-6">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">How it works</p>
                    @foreach([
                        ['1', 'You refer an event organizer to VENTIQ'],
                        ['2', 'They sign up and purchase any package'],
                        ['3', 'You earn 20% — tracked automatically'],
                    ] as $step)
                    <div class="flex items-center gap-3">
                        <div class="w-6 h-6 rounded-full bg-[#1D4069] text-white flex items-center justify-center text-[10px] font-black flex-shrink-0">
                            {{ $step[0] }}
                        </div>
                        <p class="text-xs text-gray-600 font-medium">{{ $step[1] }}</p>
                    </div>
                    @endforeach
                </div>

                {{-- CTAs --}}
                <div class="space-y-3">
                    <a href="/become-agent"
                       class="block w-full py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white font-black text-xs uppercase tracking-[0.2em] shadow-lg transition-all text-center">
                        Apply to Become an Agent
                    </a>
                    <button @click="showAgentModal = false"
                            class="block w-full py-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest hover:text-gray-600 transition-colors">
                        Maybe later
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

{{--
  ============================================================
  VENTIQ — Expanded Homepage Sections
  @include this directly below the existing hero block inside
  your welcome.blade.php, still inside @section('content').

  Design decisions:
  - Preserves every visual token already in use: #1D4069 navy,
    #F07F22 orange, rounded-[1.75rem] card radius, font-black
    uppercase tracking-tight headings, backdrop-blur modals.
  - Signature element: the Before/After section uses a vertical
    split with a live animated divider line that draws on scroll —
    one memorable structural moment, everything else is quiet.
  - Spacing: large section padding (py-24 / py-32) for the
    "Apple meets Stripe" breathing room requested.
  - Motion: scroll-triggered fade-up on every section via a
    single lightweight IntersectionObserver (no library needed).
  - No numbered markers except in How It Works where order
    genuinely matters (it's a real process sequence).
  ============================================================
--}}

{{-- ============================================================
     SCROLL REVEAL INITIALIZER
     Attaches to every [data-reveal] element below.
     Fires once per element, no library required.
============================================================ --}}
<script>
  document.addEventListener('DOMContentLoaded', () => {
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
  });
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
  [data-reveal-delay="1"] { transition-delay: 0.1s; }
  [data-reveal-delay="2"] { transition-delay: 0.2s; }
  [data-reveal-delay="3"] { transition-delay: 0.3s; }
  [data-reveal-delay="4"] { transition-delay: 0.4s; }

  /* FAQ accordion */
  .faq-body {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 0.35s ease;
  }
  .faq-body.open {
    grid-template-rows: 1fr;
  }
  .faq-body > div { overflow: hidden; }

  /* Payment animation */
  @keyframes flowRight {
    0%   { transform: translateX(-6px); opacity: 0; }
    40%  { opacity: 1; }
    100% { transform: translateX(0); opacity: 1; }
  }
  .flow-step { animation: flowRight 0.5s ease forwards; opacity: 0; }
  .flow-step:nth-child(1) { animation-delay: 0.1s; }
  .flow-step:nth-child(2) { animation-delay: 0.35s; }
  .flow-step:nth-child(3) { animation-delay: 0.6s; }
  .flow-step:nth-child(4) { animation-delay: 0.85s; }
  .flow-step:nth-child(5) { animation-delay: 1.1s; }

  /* Before/after divider */
  .ba-divider::after {
    content: '';
    position: absolute;
    top: 0; bottom: 0;
    left: 50%;
    width: 1px;
    background: linear-gradient(to bottom, transparent, #1D4069 20%, #F07F22 80%, transparent);
    transform: scaleY(0);
    transform-origin: top;
    transition: transform 0.9s cubic-bezier(.22,1,.36,1);
  }
  .ba-divider.revealed::after { transform: scaleY(1); }
</style>


{{-- ============================================================
     SECTION 1B — WHY EVENT OPERATIONS?
============================================================ --}}
<section class="py-20 px-6 text-center border-t border-gray-100" data-reveal>
  <div class="max-w-2xl mx-auto">
    <span class="inline-block px-3 py-1 rounded-full bg-[#F07F22]/8 text-[#F07F22] text-[10px] font-black uppercase tracking-widest mb-6">
      Why Event Operations?
    </span>
    <p class="text-2xl md:text-3xl font-black text-gray-900 tracking-tight leading-snug mb-4">
      Most platforms stop at registration.
    </p>
    <p class="text-base text-gray-500 font-medium leading-relaxed mb-3">
      VENTIQ continues through check-in, attendance verification, payments, digital signatures and reporting.
    </p>
    <p class="text-sm text-[#1D4069] font-black uppercase tracking-widest">
      That's why we call it an event operations platform.
    </p>
  </div>
</section>


{{-- ============================================================
     SECTION 2 — THE SCENE
============================================================ --}}
<section class="py-24 px-6 bg-[#1D4069] relative overflow-hidden">
  {{-- Atmosphere --}}
  <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-[#F07F22]/6 rounded-full blur-[120px] pointer-events-none"></div>
  <div class="absolute bottom-0 left-0 w-[300px] h-[300px] bg-white/4 rounded-full blur-[80px] pointer-events-none"></div>

  <div class="max-w-3xl mx-auto relative z-10">
    <span class="inline-block px-3 py-1 rounded-full bg-white/10 text-white/60 text-[10px] font-black uppercase tracking-widest mb-10" data-reveal>
      The Scene We See Every Day
    </span>

    <p class="text-white/70 text-base md:text-lg font-medium leading-loose mb-6" data-reveal data-reveal-delay="1">
      A conference is two hours from opening. Sponsors, clients and invited guests are beginning to arrive. At the door, a staff member holds a printed guest list on a clipboard, scanning line by line as the queue builds behind the first arrival.
    </p>

    <p class="text-white/70 text-base md:text-lg font-medium leading-loose mb-6" data-reveal data-reveal-delay="2">
      Inside, the coordinator is juggling three WhatsApp groups — catering numbers, a late change to the VIP list, a sponsor who's just added two extra guests — while still finalizing the seating chart.
    </p>

    <p class="text-white/70 text-base md:text-lg font-medium leading-loose mb-10" data-reveal data-reveal-delay="3">
      By the time the event ends, nobody can say with full confidence who actually attended, how many no-shows there were, or whether the people in the VIP area were meant to be there.
    </p>

    <p class="text-white text-xl md:text-2xl font-black italic tracking-tight leading-snug border-l-4 border-[#F07F22] pl-6" data-reveal data-reveal-delay="4">
      What if check-in took three seconds, the guest list updated itself, and a clean attendance report existed before the lights came up?
    </p>
  </div>
</section>


{{-- ============================================================
     SECTION 3 — BEFORE / AFTER
============================================================ --}}
<section class="py-24 px-6">
  <div class="max-w-5xl mx-auto">

    <div class="text-center mb-16" data-reveal>
      <span class="inline-block px-3 py-1 rounded-full bg-[#1D4069]/6 text-[#1D4069] text-[10px] font-black uppercase tracking-widest mb-4">
        Before & After
      </span>
      <h2 class="text-3xl md:text-4xl font-black text-gray-900 tracking-tight italic">
        This is what changes.
      </h2>
    </div>

    <div class="relative ba-divider grid md:grid-cols-2 gap-0" data-reveal>

      {{-- OLD WAY --}}
      <div class="bg-gray-50 rounded-l-[2rem] p-8 md:p-10 space-y-4">
        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-6">Old Way</p>
        @foreach([
          'Paper registration & clipboards',
          'Manual signatures on sign-in sheets',
          'Cash & proof-of-payment on WhatsApp',
          'Long entrance queues',
          'Excel attendance tracking',
          'Two weeks to write a report',
        ] as $item)
        <div class="flex items-start gap-3">
          <div class="w-5 h-5 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0 mt-0.5">
            <svg class="w-3 h-3 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </div>
          <p class="text-sm text-gray-500 font-medium leading-snug">{{ $item }}</p>
        </div>
        @endforeach
      </div>

      {{-- WITH VENTIQ --}}
      <div class="bg-[#1D4069] rounded-r-[2rem] p-8 md:p-10 space-y-4">
        <p class="text-[10px] font-black uppercase tracking-widest text-white/40 mb-6">With VENTIQ</p>
        @foreach([
          'Online registration',
          'Digital signature capture',
          'Configured online payments',
          'QR check-in — three seconds per guest',
          'Live participant dashboard',
          'Reports ready before your event ends',
        ] as $item)
        <div class="flex items-start gap-3">
          <div class="w-5 h-5 rounded-full bg-[#F07F22]/20 flex items-center justify-center flex-shrink-0 mt-0.5">
            <svg class="w-3 h-3 text-[#F07F22]" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
          </div>
          <p class="text-sm text-white font-medium leading-snug">{{ $item }}</p>
        </div>
        @endforeach
      </div>

    </div>
  </div>
</section>


{{-- ============================================================
     SECTION 4 — HOW IT WORKS
============================================================ --}}
<section class="py-24 px-6 bg-gray-50/60">
  <div class="max-w-4xl mx-auto">

    <div class="text-center mb-16" data-reveal>
      <span class="inline-block px-3 py-1 rounded-full bg-[#1D4069]/6 text-[#1D4069] text-[10px] font-black uppercase tracking-widest mb-4">
        How It Works
      </span>
      <h2 class="text-3xl md:text-4xl font-black text-gray-900 tracking-tight italic">
        Same flow. Any scale.
      </h2>
      <p class="text-sm text-gray-400 font-medium mt-3">
        From a 40-person internal townhall to a 400-person client summit.
      </p>
    </div>

    <div class="grid md:grid-cols-7 gap-0 items-start">
      @php
        $steps = [
          ['Create your event', 'Set date, capacity, registration fields and tiers.'],
          ['Publish registration', 'Share a branded registration link anywhere.'],
          ['Participants register', 'On phone or desktop — no app download needed.'],
          ['Configured payment', 'Set who pays, what and when. Optional.'],
          ['Confirmation sent', 'Passes delivered automatically via email or WhatsApp.'],
          ['QR or signature check-in', 'Three seconds per guest. Works offline.'],
          ['Reports generated', 'Ready before your event ends.'],
        ];
      @endphp

      @foreach($steps as $i => $step)
        {{-- Step --}}
        <div class="flex flex-col items-center text-center" data-reveal data-reveal-delay="{{ min($i, 4) }}">
          <div class="w-10 h-10 rounded-2xl flex items-center justify-center font-black text-sm mb-3
            {{ $step[0] === 'Configured payment' ? 'bg-[#F07F22] text-white' : 'bg-[#1D4069] text-white' }}">
            {{ $i + 1 }}
          </div>
          <p class="text-xs font-black text-gray-800 uppercase tracking-tight leading-tight mb-1">{{ $step[0] }}</p>
          <p class="text-[11px] text-gray-400 font-medium leading-snug hidden md:block">{{ $step[1] }}</p>
        </div>

        {{-- Arrow connector (not after last) --}}
        @if($i < count($steps) - 1)
          <div class="hidden md:flex items-center justify-center pt-5">
            <svg class="w-3 h-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
          </div>
        @endif
      @endforeach
    </div>

  </div>
</section>


{{-- ============================================================
     SECTION 5 — EVERYTHING IN ONE PLACE
============================================================ --}}
<section class="py-24 px-6">
  <div class="max-w-5xl mx-auto">

    <div class="text-center mb-16" data-reveal>
      <span class="inline-block px-3 py-1 rounded-full bg-[#F07F22]/8 text-[#F07F22] text-[10px] font-black uppercase tracking-widest mb-4">
        Platform
      </span>
      <h2 class="text-3xl md:text-4xl font-black text-gray-900 tracking-tight italic">
        Everything in one place.
      </h2>
    </div>

    @php
      $capabilities = [
        ['icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'label' => 'Registration', 'desc' => 'Custom forms, tiered pricing, branded pages', 'orange' => false],
        ['icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'label' => 'Online Payments', 'desc' => 'Set who pays, what they pay, when', 'orange' => true],
        ['icon' => 'M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 3.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z', 'label' => 'QR Access Control', 'desc' => 'Phone-based scanning, no extra hardware', 'orange' => false],
        ['icon' => 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z', 'label' => 'Digital Signatures', 'desc' => 'Replace paper sign-in sheets entirely', 'orange' => false],
        ['icon' => 'M12 18h.01M8 21l4-4 4 4M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z', 'label' => 'Mobile Scanner', 'desc' => 'Your phone is the device — no Zebra scanner needed', 'orange' => true],
        ['icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'label' => 'Live Attendance', 'desc' => 'Real-time headcount as people arrive', 'orange' => false],
        ['icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'label' => 'Certificates', 'desc' => 'Auto-generated on check-in or completion', 'orange' => false],
        ['icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'label' => 'Reports', 'desc' => 'Clean, exportable, ready for sponsors or leadership', 'orange' => false],
      ];
    @endphp

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      @foreach($capabilities as $i => $cap)
        <div class="p-5 rounded-[1.5rem] {{ $cap['orange'] ? 'bg-[#1D4069]' : 'bg-gray-50 hover:bg-white hover:shadow-md' }} transition-all"
             data-reveal data-reveal-delay="{{ $i % 4 }}">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center mb-3
            {{ $cap['orange'] ? 'bg-[#F07F22]/20 text-[#F07F22]' : 'bg-[#1D4069]/8 text-[#1D4069]' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $cap['icon'] }}"/>
            </svg>
          </div>
          <p class="text-xs font-black uppercase tracking-tight {{ $cap['orange'] ? 'text-white' : 'text-gray-800' }} mb-1">
            {{ $cap['label'] }}
          </p>
          <p class="text-[11px] font-medium leading-snug {{ $cap['orange'] ? 'text-white/60' : 'text-gray-400' }}">
            {{ $cap['desc'] }}
          </p>
        </div>
      @endforeach
    </div>

  </div>
</section>


{{-- ============================================================
     SECTION 6 — CONFIGURED ONLINE PAYMENTS
============================================================ --}}
<section class="py-24 px-6 bg-[#1D4069] overflow-hidden relative">
  <div class="absolute -top-20 -right-20 w-96 h-96 bg-[#F07F22]/8 rounded-full blur-[80px] pointer-events-none"></div>

  <div class="max-w-5xl mx-auto relative z-10 grid md:grid-cols-2 gap-12 items-center">

    {{-- Left: copy --}}
    <div data-reveal>
      <span class="inline-block px-3 py-1 rounded-full bg-white/10 text-white/60 text-[10px] font-black uppercase tracking-widest mb-6">
        Configured Online Payments
      </span>
      <h2 class="text-3xl md:text-4xl font-black text-white tracking-tight italic leading-snug mb-6">
        Collect payments<br>before your event.
      </h2>
      <p class="text-lg font-black text-[#F07F22] mb-4">
        You decide who pays, what they pay and when they pay.
      </p>
      <p class="text-sm text-white/60 font-medium leading-relaxed">
        Configure who attends for free. Every payment tracked automatically — no spreadsheets, no proof-of-payment screenshots on WhatsApp, no manual reconciliation.
      </p>
    </div>

    {{-- Right: payment flow animation --}}
    <div class="bg-white/5 rounded-[2rem] p-8 backdrop-blur-sm" data-reveal data-reveal-delay="2">
      <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-6">Payment flow</p>

      @php
        $flow = [
          ['icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'label' => 'Attendee pays online'],
          ['icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => 'Payment confirmed'],
          ['icon' => 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z', 'label' => 'Pass sent automatically'],
          ['icon' => 'M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 3.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z', 'label' => 'QR scanned at entry'],
          ['icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'label' => 'Revenue in report'],
        ];
      @endphp

      <div class="space-y-3 payment-flow-container">
        @foreach($flow as $i => $step)
          <div class="flow-step flex items-center gap-3">
            @if($i > 0)
              <div class="w-px h-3 bg-white/10 ml-4 -mt-3 mb-0 absolute"></div>
            @endif
            <div class="w-8 h-8 rounded-xl bg-[#F07F22]/15 flex items-center justify-center flex-shrink-0">
              <svg class="w-4 h-4 text-[#F07F22]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $step['icon'] }}"/>
              </svg>
            </div>
            <p class="text-sm text-white font-medium">{{ $step['label'] }}</p>
            @if($i < count($flow) - 1)
              <div class="ml-auto">
                <svg class="w-3 h-3 text-white/20 rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </div>
            @endif
          </div>
        @endforeach
      </div>
    </div>

  </div>
</section>


{{-- ============================================================
     SECTION 7 — REAL-TIME REPORTING
============================================================ --}}
<section class="py-24 px-6">
  <div class="max-w-5xl mx-auto grid md:grid-cols-2 gap-12 items-center">

    {{-- Left: mock dashboard --}}
    <div class="bg-gray-50 rounded-[2rem] p-8 border border-gray-100 shadow-sm" data-reveal>
      <div class="flex items-center justify-between mb-6">
        <p class="text-xs font-black uppercase tracking-widest text-gray-400">Live Dashboard — Sample</p>
        <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
      </div>

      @php
        $stats = [
          ['Registered', '342', false],
          ['Checked In', '297', false],
          ['Attendance', '87%', true],
          ['No Shows', '45', false],
          ['Certificates', '291', false],
          ['Revenue', 'M76,500', true],
        ];
      @endphp

      <div class="grid grid-cols-2 gap-3">
        @foreach($stats as $stat)
          <div class="bg-white rounded-[1.25rem] p-4 border border-gray-100">
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">{{ $stat[0] }}</p>
            <p class="text-2xl font-black {{ $stat[2] ? 'text-[#F07F22]' : 'text-[#1D4069]' }} tracking-tight">
              {{ $stat[1] }}
            </p>
          </div>
        @endforeach
      </div>

      <p class="text-[10px] text-gray-300 font-medium mt-4 text-center">
        Sample figures for illustration — your real data here
      </p>
    </div>

    {{-- Right: copy --}}
    <div data-reveal data-reveal-delay="2">
      <span class="inline-block px-3 py-1 rounded-full bg-[#1D4069]/6 text-[#1D4069] text-[10px] font-black uppercase tracking-widest mb-6">
        Real-Time Reporting
      </span>
      <h2 class="text-3xl md:text-4xl font-black text-gray-900 tracking-tight italic leading-snug mb-6">
        A clean report, ready before the lights come up.
      </h2>
      <p class="text-sm text-gray-500 font-medium leading-relaxed">
        Registered, checked-in, attendance percentage, no-shows, certificates issued and revenue collected — all in one place, updated live as your event runs. Share it with leadership, sponsors or donors immediately after.
      </p>
    </div>

  </div>
</section>


{{-- ============================================================
     SECTION 8 — BUILT FOR
============================================================ --}}
<section class="py-20 px-6 bg-gray-50/60">
  <div class="max-w-4xl mx-auto text-center">

    <div class="mb-12" data-reveal>
      <span class="inline-block px-3 py-1 rounded-full bg-[#F07F22]/8 text-[#F07F22] text-[10px] font-black uppercase tracking-widest mb-4">
        Built For
      </span>
      <h2 class="text-3xl font-black text-gray-900 tracking-tight italic">
        Whatever your event looks like.
      </h2>
    </div>

    @php
      $usecases = [
        ['Corporate Events', 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
        ['Government', 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3'],
        ['NGOs', 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'],
        ['Universities', 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222'],
        ['Training Providers', 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'],
        ['Church Conferences', 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z'],
        ['Community Programmes', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ['Sports Events', 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z'],
      ];
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      @foreach($usecases as $i => $uc)
        <div class="flex flex-col items-center p-5 rounded-[1.5rem] bg-white border border-gray-100 hover:border-[#1D4069]/20 hover:shadow-sm transition-all"
             data-reveal data-reveal-delay="{{ $i % 4 }}">
          <div class="w-10 h-10 rounded-xl bg-[#1D4069]/6 text-[#1D4069] flex items-center justify-center mb-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $uc[1] }}"/>
            </svg>
          </div>
          <p class="text-xs font-black text-gray-700 uppercase tracking-tight text-center">{{ $uc[0] }}</p>
        </div>
      @endforeach
    </div>

  </div>
</section>


{{-- ============================================================
     SECTION 9 — AFRICAN OPERATIONAL REALITIES
============================================================ --}}
<section class="py-24 px-6">
  <div class="max-w-5xl mx-auto grid md:grid-cols-2 gap-16 items-center">

    <div data-reveal>
      <span class="inline-block px-3 py-1 rounded-full bg-[#1D4069]/6 text-[#1D4069] text-[10px] font-black uppercase tracking-widest mb-6">
        Built for African Operational Realities
      </span>
      <h2 class="text-3xl md:text-4xl font-black text-gray-900 tracking-tight italic leading-snug">
        Designed for how events actually run here.
      </h2>
    </div>

    <div class="space-y-5" data-reveal data-reveal-delay="2">
      @php
        $realities = [
          ['Built, operated and supported from Maseru', 'If something breaks, you can call somebody.', true],
          ['Offline-ready scanning', 'Check-in keeps working even if venue connectivity drops, syncing once it returns.', false],
          ['Your phone is the scanner', 'No extra hardware to buy, lose, or charge.', false],
          ['WhatsApp-first delivery', 'Passes go out through the channel your guests already use daily.', false],
          ['Voucher code fallback', 'Guests without a smartphone are never excluded.', false],
          ['Historical records', 'Every past event remains searchable, useful for recurring programmes.', false],
        ];
      @endphp

      @foreach($realities as $r)
        <div class="flex items-start gap-4">
          <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5
            {{ $r[2] ? 'bg-[#F07F22] text-white' : 'bg-[#1D4069]/8 text-[#1D4069]' }}">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-black text-gray-800 {{ $r[2] ? 'text-[#1D4069]' : '' }}">{{ $r[0] }}</p>
            <p class="text-xs text-gray-400 font-medium mt-0.5">{{ $r[1] }}</p>
          </div>
        </div>
      @endforeach
    </div>

  </div>
</section>


{{-- ============================================================
     SECTION 10 — TRUSTED BY
============================================================ --}}
<section class="py-20 px-6 bg-gray-50/60">
  <div class="max-w-3xl mx-auto text-center">

    <div class="mb-10" data-reveal>
      <span class="inline-block px-3 py-1 rounded-full bg-[#1D4069]/6 text-[#1D4069] text-[10px] font-black uppercase tracking-widest mb-4">
        Trusted By
      </span>
      <h2 class="text-2xl font-black text-gray-900 tracking-tight italic">
        Real organizations. Real events.
      </h2>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-12">
      @foreach(['E-Legal Solutions Inc', 'Beka & Co Brunch', 'The Curriculum Expert', 'Expressions Conference'] as $i => $org)
        <div class="bg-white rounded-[1.25rem] p-4 border border-gray-100 text-center"
             data-reveal data-reveal-delay="{{ $i }}">
          <p class="text-xs font-black text-gray-700 leading-snug">{{ $org }}</p>
        </div>
      @endforeach
    </div>

    {{-- Testimonial placeholder — replace with Thembeka's actual quote before going live --}}
    {{-- IMPORTANT: do not publish this block until you have her exact words and explicit OK --}}
    {{--
    <div class="bg-white rounded-[2rem] p-8 border border-gray-100 text-left" data-reveal>
      <p class="text-base text-gray-700 font-medium italic leading-relaxed mb-4">
        "[ Thembeka's actual quote here — reach out and ask for one sentence. ]"
      </p>
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-full bg-[#1D4069]/10 flex items-center justify-center text-xs font-black text-[#1D4069]">TM</div>
        <div>
          <p class="text-xs font-black text-gray-800">Thembeka Magaga Mokhalinyane</p>
          <p class="text-[11px] text-gray-400 font-medium">Beka & Co Brunch</p>
        </div>
      </div>
    </div>
    --}}

  </div>
</section>


{{-- ============================================================
     SECTION 11 — PRICING PREVIEW
============================================================ --}}
<section class="py-24 px-6">
  <div class="max-w-4xl mx-auto">

    <div class="text-center mb-14" data-reveal>
      <span class="inline-block px-3 py-1 rounded-full bg-[#F07F22]/8 text-[#F07F22] text-[10px] font-black uppercase tracking-widest mb-4">
        Pricing
      </span>
      <h2 class="text-3xl font-black text-gray-900 tracking-tight italic">
        Simple, per-event pricing.
      </h2>
    </div>

    <div class="grid md:grid-cols-3 gap-4 mb-8">
      @php
        $plans = [
          ['Starter', 'M250', 'Per event', 'Up to 50 attendees', false],
          ['Standard', 'M600', 'Per event', 'Up to 200 attendees', true],
          ['Professional', 'M1,200', 'Per event', 'Up to 500 attendees', false],
        ];
      @endphp

      @foreach($plans as $i => $plan)
        <div class="relative rounded-[1.75rem] p-6 {{ $plan[4] ? 'bg-[#1D4069] text-white' : 'bg-gray-50 border border-gray-100' }}"
             data-reveal data-reveal-delay="{{ $i }}">
          @if($plan[4])
            <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-[#F07F22] text-white text-[9px] font-black px-3 py-1 rounded-lg uppercase tracking-widest">
              Popular
            </div>
          @endif
          <p class="text-[10px] font-black uppercase tracking-widest {{ $plan[4] ? 'text-white/50' : 'text-gray-400' }} mb-2">{{ $plan[0] }}</p>
          <p class="text-3xl font-black {{ $plan[4] ? 'text-white' : 'text-[#1D4069]' }} mb-1">{{ $plan[1] }}</p>
          <p class="text-[10px] {{ $plan[4] ? 'text-white/40' : 'text-gray-400' }} font-medium mb-3">{{ $plan[2] }}</p>
          <p class="text-xs {{ $plan[4] ? 'text-white/70' : 'text-gray-500' }} font-medium">{{ $plan[3] }}</p>
        </div>
      @endforeach
    </div>

    <div class="text-center" data-reveal>
      <p class="text-sm text-gray-400 font-medium mb-4">Enterprise pricing quoted directly based on event scale and complexity.</p>
      <a href="/pricing" class="inline-block px-6 py-3 rounded-2xl border-2 border-[#1D4069]/20 text-[#1D4069] font-black text-xs uppercase tracking-widest hover:bg-[#1D4069] hover:text-white transition-all">
        See Full Pricing
      </a>
    </div>

  </div>
</section>


{{-- ============================================================
     SECTION 12 — FAQ
============================================================ --}}
<section class="py-24 px-6 bg-gray-50/60">
  <div class="max-w-2xl mx-auto">

    <div class="text-center mb-12" data-reveal>
      <span class="inline-block px-3 py-1 rounded-full bg-[#1D4069]/6 text-[#1D4069] text-[10px] font-black uppercase tracking-widest mb-4">
        FAQ
      </span>
      <h2 class="text-3xl font-black text-gray-900 tracking-tight italic">Common questions.</h2>
    </div>

    @php
      $faqs = [
        ['Can I collect payments?', 'Yes — configure who pays, what they pay, and when, with everything tracked automatically.'],
        ['Can I use my own branding?', 'Yes, registration pages and passes can reflect your organization\'s identity.'],
        ['Do attendees need to install an app?', 'No. Everything works through a web browser — no downloads required for attendees or staff.'],
        ['Can I run free events?', 'Yes. VENTIQ supports both free and paid events.'],
        ['Can I export reports?', 'Yes, full attendance and revenue reports are exportable at any time.'],
        ['Can multiple organizers collaborate on one event?', 'Yes.'],
        ['Can participants register using just a phone?', 'Yes — registration, passes, and check-in all work directly from a phone.'],
        ['Can VENTIQ scan QR codes offline?', 'Yes — check-in keeps working without connectivity and syncs automatically once it\'s restored.'],
        ['Do I need a separate scanning device?', 'No — your phone is the scanner.'],
      ];
    @endphp

    <div class="space-y-2" data-reveal>
      @foreach($faqs as $i => $faq)
        <div class="bg-white rounded-[1.25rem] border border-gray-100 overflow-hidden"
             x-data="{ open: false }">
          <button class="w-full flex items-center justify-between p-5 text-left" @click="open = !open">
            <span class="text-sm font-black text-gray-800">{{ $faq[0] }}</span>
            <svg class="w-4 h-4 text-gray-400 transition-transform flex-shrink-0 ml-4" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
          <div x-show="open" x-collapse>
            <p class="text-sm text-gray-500 font-medium px-5 pb-5 leading-relaxed">{{ $faq[1] }}</p>
          </div>
        </div>
      @endforeach
    </div>

  </div>
</section>


{{-- ============================================================
     SECTION 13 — FINAL CTA
============================================================ --}}
<section class="py-32 px-6 text-center relative overflow-hidden">
  <div class="absolute inset-0 -z-10">
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[600px] h-[400px] bg-[#1D4069]/6 rounded-full blur-[100px]"></div>
  </div>

  <div class="max-w-2xl mx-auto" data-reveal>
    <h2 class="text-4xl md:text-5xl font-black text-gray-900 tracking-tight italic leading-tight mb-6">
      Ready to run your next event with VENTIQ?
    </h2>
    <p class="text-sm text-gray-400 font-medium mb-10">
      No long-term commitment. Start with a single pilot event.
    </p>
    <div class="flex flex-col sm:flex-row gap-3 justify-center">
      <a href="{{ route('org.register.direct') }}"
         class="px-8 py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white font-black text-xs uppercase tracking-[0.2em] shadow-xl shadow-blue-900/20 transition-all">
        Create Organizer Account
      </a>
      <button @click="$dispatch('contact-open')"
              class="px-8 py-4 rounded-2xl border-2 border-[#1D4069]/20 text-[#1D4069] font-black text-xs uppercase tracking-[0.2em] hover:border-[#1D4069] transition-all">
        Book a Demo
      </button>
    </div>
  </div>
</section>


{{-- ============================================================
     FOOTER
============================================================ --}}
<footer class="border-t border-gray-100 py-12 px-6">
  <div class="max-w-5xl mx-auto">

    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-10">

      <div>
        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-4">Company</p>
        <div class="space-y-2">
          @foreach(['About' => '/about', 'Pricing' => '/pricing', 'Careers' => '/careers'] as $label => $href)
            <a href="{{ $href }}" class="block text-sm text-gray-500 font-medium hover:text-[#1D4069] transition-colors">{{ $label }}</a>
          @endforeach
        </div>
      </div>

      <div>
        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-4">Platform</p>
        <div class="space-y-2">
          @foreach(['Registration' => '#', 'Payments' => '#', 'QR Scanner' => '#', 'Reports' => '#'] as $label => $href)
            <a href="{{ $href }}" class="block text-sm text-gray-500 font-medium hover:text-[#1D4069] transition-colors">{{ $label }}</a>
          @endforeach
        </div>
      </div>

      <div>
        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-4">Partners</p>
        <div class="space-y-2">
          @foreach(['Agent Programme' => '/become-agent', 'Book a Demo' => '#'] as $label => $href)
            <a href="{{ $href }}" class="block text-sm text-gray-500 font-medium hover:text-[#1D4069] transition-colors">{{ $label }}</a>
          @endforeach
        </div>
      </div>

      <div>
        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-4">Support</p>
        <div class="space-y-2">
          <button @click="$dispatch('contact-open')" class="block text-sm text-gray-500 font-medium hover:text-[#1D4069] transition-colors">Contact</button>
          <a href="mailto:support@ventiq.co.ls" class="block text-sm text-gray-500 font-medium hover:text-[#1D4069] transition-colors">support@ventiq.co.ls</a>
          <a href="https://wa.me/26662552155" class="block text-sm text-gray-500 font-medium hover:text-[#1D4069] transition-colors">WhatsApp</a>
        </div>
      </div>

    </div>

    <div class="flex flex-col md:flex-row items-center justify-between gap-4 pt-8 border-t border-gray-100">
      <div class="flex items-center gap-2">
        <span class="text-lg font-black text-[#1D4069] italic tracking-tight">VENTIQ</span>
        <span class="text-[10px] text-gray-300 font-medium">· Simply Connected.</span>
      </div>

      <div class="flex items-center gap-5">
        {{-- LinkedIn --}}
        <a href="#" class="text-gray-300 hover:text-[#1D4069] transition-colors">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
          </svg>
        </a>
        {{-- Facebook --}}
        <a href="#" class="text-gray-300 hover:text-[#1D4069] transition-colors">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
          </svg>
        </a>
        {{-- X/Twitter --}}
        <a href="#" class="text-gray-300 hover:text-[#1D4069] transition-colors">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.737-8.835L1.254 2.25H8.08l4.253 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
          </svg>
        </a>
      </div>

      <p class="text-[11px] text-gray-300 font-medium">
        © {{ date('Y') }} Statistical Data Analysis & Information Technology (Pty) Ltd
      </p>
    </div>

  </div>
</footer>
@endsection