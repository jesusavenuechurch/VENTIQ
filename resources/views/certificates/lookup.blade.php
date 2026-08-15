<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify a Certificate | VENTIQ</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
<style>body{ font-family:'Inter',sans-serif; }</style>
</head>
<body class="min-h-screen bg-[#F8FAFC] flex items-center justify-center p-6">
<div class="w-full max-w-sm">
    <div class="text-center mb-8">
        <p class="text-xl font-black tracking-tighter text-[#1D4069]">VENTI<span class="text-[#F07F22]">Q</span></p>
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1">Certificate Verification</p>
    </div>

    <div class="bg-white rounded-[2rem] shadow-xl p-8">
        <h1 class="text-lg font-black text-[#1D4069] uppercase tracking-tight mb-1">Verify a Certificate</h1>
        <p class="text-[11px] text-gray-400 font-bold uppercase tracking-widest mb-6">Enter the certificate number printed on the document</p>

        @isset($error)
            <div class="mb-4 p-3 bg-rose-50 text-rose-600 rounded-xl text-[11px] font-bold">
                {{ $error }}
            </div>
        @endisset

        <form method="GET" action="{{ route('certificates.lookup') }}" class="space-y-4">
            <input type="text" name="number" required placeholder="VQ-2026-000184" autofocus
                   value="{{ $number ?? '' }}"
                   class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold uppercase outline-none focus:ring-2 focus:ring-[#F07F22]/20">
            <button type="submit" class="w-full py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white font-black text-[10px] uppercase tracking-[0.3em] transition-all">
                Verify
            </button>
        </form>
    </div>

    <p class="text-center text-[9px] font-bold text-gray-300 uppercase tracking-widest mt-6">
        Ventiq · ventiq.co.ls
    </p>
</div>
</body>
</html>
