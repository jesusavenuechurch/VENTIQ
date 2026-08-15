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

@if(empty($existing))
    <label class="block text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-2">
        Search by phone or email to edit missing details
    </label>
    <form method="GET" action="{{ route('public.session-checkin.form', $session->public_token) }}" class="mb-5 flex gap-2">
        <input type="text" name="search" placeholder="Search here"
            class="flex-1 bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
        <button type="submit" class="px-4 py-3 rounded-2xl bg-gray-100 text-[#1D4069] text-[9px] font-black uppercase tracking-widest">Find Me</button>
    </form>
    <div class="text-center text-[9px] font-bold text-gray-300 uppercase tracking-widest mb-5">— or fill in fresh —</div>
@else
    <div class="mb-5 p-3 bg-emerald-50 text-emerald-600 rounded-xl text-[11px] font-bold">
        Found your check-in — just fill in what's missing.
    </div>
@endif

            @if($errors->any())
                <div class="mb-4 p-3 bg-rose-50 text-rose-600 rounded-xl text-[11px] font-bold">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('public.session-checkin.submit', $session->public_token) }}" class="space-y-4" enctype="multipart/form-data">
                @csrf
                <input type="text" name="full_name" required placeholder="Full name" autofocus
                       value="{{ old('full_name', $existing->client->full_name ?? '') }}"
                       class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                <input type="email" name="email" required placeholder="Email"
                       value="{{ old('email', $existing->client->email ?? '') }}"
                       class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                <input type="tel" name="phone" placeholder="Phone (optional)"
                       value="{{ old('phone', $existing->client->phone ?? '') }}"
                       class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                <input type="text" name="institution" placeholder="Organization / Institution"
                       value="{{ old('institution', $existing->institution ?? '') }}"
                       class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                <input type="text" name="position" placeholder="Position / Role"
                       value="{{ old('position', $existing->position ?? '') }}"
                       class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">

                <label class="block">
                    <span class="block text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-2">
                        Photo for your attendance card (optional)
                    </span>
                    <div class="flex items-center gap-3">
                        <div id="photo-preview" class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center text-gray-300 text-xl shrink-0 overflow-hidden">📷</div>
                        <input type="file" name="photo" accept="image/*" capture="user" id="photo-input"
                               class="flex-1 text-[11px] font-bold text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[9px] file:font-black file:uppercase file:tracking-widest file:bg-gray-100 file:text-[#1D4069]">
                    </div>
                </label>

                <button type="submit" class="w-full py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white font-black text-[10px] uppercase tracking-[0.3em] transition-all">
                    Check In
                </button>
            </form>
            <script>
                document.getElementById('photo-input')?.addEventListener('change', function (e) {
                    const file = e.target.files[0];
                    const preview = document.getElementById('photo-preview');
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = () => {
                        preview.innerHTML = `<img src="${reader.result}" class="w-full h-full object-cover">`;
                    };
                    reader.readAsDataURL(file);
                });
            </script>
        @endif
    </div>
</div>
</body>
</html>