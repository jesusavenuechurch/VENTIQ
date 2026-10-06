<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Your ticket link has changed | VENTIQ</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen flex items-center justify-center p-6 bg-slate-50 text-[#1D4069]">
    <div class="w-full max-w-md bg-white rounded-[2rem] shadow-xl border border-gray-100 p-8 text-center">
        <div class="w-14 h-14 rounded-2xl bg-action-soft text-action-ink flex items-center justify-center mx-auto mb-5"><i class="fas fa-shield-halved text-xl"></i></div>
        <h1 class="text-2xl font-black tracking-tight">Your ticket has a new private link</h1>
        <p class="text-[13px] font-medium text-gray-500 mt-3">To keep tickets safe, each one now has its own private link. This old link no longer opens it.</p>

        @if(session('status'))
            <div class="mt-6 p-4 rounded-2xl bg-mint text-mint-ink text-[13px] font-bold">{{ session('status') }}</div>
        @elseif($ticket && $maskedEmail)
            <form method="POST" action="{{ route('ticket.link.send', $ticket->id) }}" class="mt-6">
                @csrf
                <button class="w-full py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[11px] font-black uppercase tracking-[0.2em]">Email me my ticket link</button>
                <p class="mt-3 text-[11px] text-gray-400">It goes to {{ $maskedEmail }}, the address the ticket was registered with.</p>
            </form>
        @elseif($ticket)
            <p class="mt-6 text-[13px] font-bold text-gray-600">Open the ticket link from your WhatsApp message, or ask {{ $organizer }} to send your ticket again.</p>
        @else
            <p class="mt-6 text-[13px] font-bold text-gray-600">Open the ticket link from your email or WhatsApp message, or ask the organizer to send your ticket again.</p>
        @endif
    </div>
</body>
</html>
