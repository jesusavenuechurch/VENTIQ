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
@endsection