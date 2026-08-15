<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Certificate — {{ $certificate->client->full_name }} | VENTIQ</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
<style>body{ font-family:'Inter',sans-serif; }</style>
</head>
<body class="min-h-screen bg-[#F8FAFC] flex items-center justify-center p-6">
<div class="w-full max-w-md">
    <div class="text-center mb-8">
        <p class="text-xl font-black tracking-tighter text-[#1D4069]">VENTI<span class="text-[#F07F22]">Q</span></p>
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1">Certificate Verification</p>
    </div>

    <div class="bg-white rounded-[2rem] shadow-xl p-8 text-center">
        <div class="w-14 h-14 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-black">✓</div>
        <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest mb-6">Verified Certificate</p>

        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Awarded to</p>
        <h1 class="text-2xl font-black text-[#1D4069] mb-6">{{ $certificate->client->full_name }}</h1>

        <p class="text-[13px] font-medium text-gray-500 leading-relaxed mb-6">
            For participating in <span class="font-black text-gray-800">{{ $certificate->programme->name }}</span>,
            hosted by <span class="font-black text-gray-800">{{ $certificate->organization->name }}</span>.
        </p>

        <p class="text-[9px] font-bold text-gray-300 uppercase tracking-widest mb-1">
            Issued {{ $certificate->issued_at->format('d M Y') }}
        </p>
        <p class="text-[9px] font-bold text-gray-300 uppercase tracking-widest mb-8">
            Certificate No. {{ $certificate->certificate_number }}
        </p>

        <div class="space-y-3">
            <a href="{{ $certificate->linked_in_add_url }}"
               target="_blank" rel="noopener"
               class="block w-full py-4 rounded-2xl bg-[#0A66C2] hover:bg-[#08508f] text-white font-black text-[10px] uppercase tracking-[0.3em] transition-all">
                Add to LinkedIn Profile
            </a>
            <a href="{{ route('certificates.download.public', $certificate->token) }}"
               class="block w-full py-4 rounded-2xl bg-gray-100 hover:bg-gray-200 text-[#1D4069] font-black text-[10px] uppercase tracking-[0.3em] transition-all">
                Download PDF
            </a>
        </div>
    </div>

    <p class="text-center text-[9px] font-bold text-gray-300 uppercase tracking-widest mt-6">
        Verified by Ventiq · ventiq.co.ls
    </p>
</div>
</body>
</html>
