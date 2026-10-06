{{--
  The attendee side: event page, registration, paying, the ticket itself.
  Same palette as the organizer area (resources/css/app.css), warmer words,
  phone first. Pages set: title, width (2xl default: max-w-2xl, max-w-3xl,
  max-w-5xl are the ones used), and optionally push
  'head' (meta tags) and 'scripts'.
--}}
@php
    $org = $organization ?? ($ticket ?? null)?->event?->organization ?? ($event ?? null)?->organization ?? null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'VENTIQ')</title>
    @stack('head')
    @vite('resources/css/app.css')
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .accordion-panel { max-height: 0; overflow: hidden; transition: max-height 0.35s ease; }
        .accordion-panel.open { max-height: 3000px; }
        @keyframes soft-pulse { 0%, 100% { transform: scale(1); opacity: .25; } 50% { transform: scale(1.35); opacity: .08; } }
        .soft-pulse { animation: soft-pulse 2s infinite ease-in-out; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 text-[#1D4069] antialiased flex flex-col">
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-gray-100">
        <div class="mx-auto max-w-5xl px-4 h-14 flex items-center justify-between gap-3">
            <a href="{{ $org ? route('public.events', $org->slug) : url('/') }}" class="flex items-center gap-2.5 min-w-0">
                @if($org?->logo)
                    <img src="{{ Storage::url($org->logo) }}" alt="" class="h-8 w-8 rounded-full object-cover border border-gray-100">
                @else
                    <span class="h-8 w-8 shrink-0 rounded-full bg-[#1D4069] text-white text-[12px] font-black flex items-center justify-center">{{ strtoupper(mb_substr($org?->name ?? 'V', 0, 1)) }}</span>
                @endif
                <span class="text-[13px] font-black truncate">{{ $org?->name ?? 'VENTIQ' }}</span>
            </a>
            <a href="{{ route('ticket.find') }}" class="shrink-0 text-[11px] font-bold text-gray-500 hover:text-[#F07F22]"><i class="fas fa-ticket mr-1"></i>Find my ticket</a>
        </div>
    </header>

    <main class="flex-1 w-full mx-auto max-w-@yield('width', '2xl') px-4 py-6 pb-28">
        @yield('content')
    </main>

    <footer class="pb-24 lg:pb-8 pt-4 text-center text-[11px] text-gray-400">
        Tickets by <a href="{{ url('/') }}" class="font-black text-[#1D4069]">VENTI<span class="text-[#F07F22]">Q</span></a>
        · <a href="{{ route('terms') }}" class="hover:text-[#1D4069]">Ticket terms</a>
    </footer>

    @stack('scripts')
    @include('partials.cookie-notice')
</body>
</html>
