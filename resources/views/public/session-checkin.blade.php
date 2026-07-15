<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Check In — {{ $session->resolved_title }}</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
<style>body{ font-family:'Inter',sans-serif; }</style>
</head>
<body class="min-h-screen bg-[#F8FAFC] flex items-center justify-center p-6">
<div class="w-full max-w-sm">
    <div class="text-center mb-8">
        <p class="text-xl font-black tracking-tighter text-[#1D4069]">VENTI<span class="text-[#F07F22]">Q</span></p>
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1">Self Check-in</p>
    </div>

    <div class="bg-white rounded-[2rem] shadow-xl p-8">
        @if(!empty($success))
            <div class="text-center py-6">
                <div class="w-16 h-16 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl font-black">✓</div>
                <h2 class="text-lg font-black text-[#1D4069] uppercase">Welcome, {{ $name }}</h2>
                <p class="text-[11px] text-gray-400 font-bold uppercase tracking-wide mt-2">You're checked in to</p>
                <p class="text-sm font-bold text-gray-600 mt-1">{{ $session->resolved_title }}</p>
            </div>
        @else
            <h2 class="text-lg font-black text-[#1D4069] uppercase tracking-tight mb-1">{{ $session->resolved_title }}</h2>
            <p class="text-[11px] text-gray-400 font-bold uppercase tracking-widest mb-6">Enter your details to check in</p>

            @if($errors->any())
                <div class="mb-4 p-3 bg-rose-50 text-rose-600 rounded-xl text-[11px] font-bold">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('public.session-checkin.submit', $session->public_token) }}" class="space-y-4">
                @csrf
                <input type="text" name="full_name" required placeholder="Full name" autofocus
                       class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                <input type="tel" name="phone" placeholder="Phone (optional)"
                       class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                <input type="email" name="email" placeholder="Email (optional)"
                       class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                <input type="text" name="institution" placeholder="Organization / Institution"
                    class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                <input type="text" name="position" placeholder="Position / Role"
                    class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                <button type="submit" class="w-full py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white font-black text-[10px] uppercase tracking-[0.3em] transition-all">
                    Check In
                </button>
            </form>
        @endif
    </div>
</div>
</body>
</html>