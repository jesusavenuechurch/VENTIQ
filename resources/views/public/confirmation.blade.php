<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Protocol Status | {{ $event->name }}</title>
    @vite('resources/css/app.css')
    <style>
        body { font-family: 'Inter', sans-serif; }
        .rounded-ventiq { border-radius: 2.5rem; }
        @keyframes protocol-pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.4); opacity: 0.3; }
            100% { transform: scale(1); opacity: 1; }
        }
        .status-pulse { animation: protocol-pulse 2s infinite ease-in-out; }
        @keyframes shake {
            0%, 100% { transform: rotate(0deg); }
            10%, 30%, 50%, 70%, 90% { transform: rotate(-8deg); }
            20%, 40%, 60%, 80% { transform: rotate(8deg); }
        }
        .shake-icon { animation: shake 2.5s infinite ease-in-out; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased min-h-screen flex items-center justify-center p-4 sm:p-6">

    <main class="w-full max-w-2xl">
        <div class="bg-white rounded-ventiq shadow-2xl shadow-slate-200/60 overflow-hidden border border-slate-100">
            
            {{-- Header --}}
            <div class="px-8 py-6 border-b border-slate-50 flex justify-between items-center bg-slate-50/30">
                <div class="flex items-center gap-3">
                    <span class="text-2xl font-black tracking-tighter italic">V.</span>
                    <span class="text-[9px] font-black uppercase tracking-[0.4em] text-slate-300">{{ $event->organization->name }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative flex h-2 w-2">
                        <span class="status-pulse absolute inline-flex h-full w-full rounded-full {{ $ticket->payment_status === 'completed' ? 'bg-emerald-400' : 'bg-[#F07F22]' }} opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 {{ $ticket->payment_status === 'completed' ? 'bg-emerald-500' : 'bg-[#F07F22]' }}"></span>
                    </div>
                    <span class="text-[9px] font-black uppercase tracking-[0.2em]">{{ $ticket->payment_status }}</span>
                </div>
            </div>

            <div class="p-8 sm:p-12 space-y-10">
                
                {{-- Status Title --}}
                <div class="text-center space-y-4">
                    @if($ticket->payment_status !== 'completed')
                        <div class="w-20 h-20 bg-[#F07F22] rounded-3xl flex items-center justify-center mx-auto shadow-xl shadow-orange-200 shake-icon">
                            <i class="fas fa-clock text-white text-3xl"></i>
                        </div>
                    @else
                        <div class="w-20 h-20 bg-emerald-500 rounded-3xl flex items-center justify-center mx-auto shadow-xl shadow-emerald-200">
                            <i class="fas fa-check text-white text-3xl"></i>
                        </div>
                    @endif

                    <h1 class="text-5xl sm:text-7xl font-black tracking-tighter uppercase italic leading-none">
                        {{ $ticket->payment_status === 'completed' ? 'Verified' : 'Pending' }}
                    </h1>

                    {{-- Prominent Ticket Number --}}
                    <div class="inline-flex items-center gap-3 px-6 py-3 bg-gradient-to-r from-[#1D4069] to-[#F07F22] rounded-2xl shadow-lg">
                        <i class="fas fa-ticket-alt text-white text-lg"></i>
                        <div class="text-left">
                            <p class="text-[8px] font-black text-white/60 uppercase tracking-widest leading-none mb-1">Reference ID</p>
                            <p class="text-xl font-black text-white font-mono tracking-tight leading-none">#{{ $ticket->ticket_number }}</p>
                        </div>
                    </div>
                </div>

                {{-- Event Details --}}
                <div class="bg-slate-50 rounded-3xl p-6 flex flex-col sm:flex-row justify-between items-center gap-4 text-center sm:text-left border border-slate-100">
                    <div>
                        <p class="text-[8px] font-black text-slate-300 uppercase tracking-widest mb-1">Event</p>
                        <h2 class="text-sm font-black uppercase tracking-tight">{{ $event->name }}</h2>
                    </div>
                    <div class="flex gap-6">
                        <div>
                            <p class="text-[8px] font-black text-slate-300 uppercase mb-1">Date</p>
                            <p class="text-[10px] font-black uppercase">{{ $event->event_date->format('d M Y') }}</p>
                        </div>
                        <div>
                            <p class="text-[8px] font-black text-slate-300 uppercase mb-1">Venue</p>
                            <p class="text-[10px] font-black uppercase truncate max-w-[120px]">{{ $event->venue }}</p>
                        </div>
                    </div>
                </div>

                {{-- Ticket List --}}
                <div class="space-y-4">
                    <p class="text-[9px] font-black text-slate-300 uppercase tracking-[0.4em] text-center mb-6">Authorized Identity</p>
                    @foreach($allTickets as $index => $singleTicket)
                        <div class="bg-white border-2 border-slate-100 rounded-[2rem] p-6 sm:p-8 transition-all hover:border-[#F07F22] hover:shadow-lg">
                            <div class="flex items-center justify-between mb-6">
                                <div>
                                    <p class="text-[8px] font-black text-[#F07F22] uppercase mb-1">
                                        @if(($singleTicket->admissions ?? 1) > 1)
                                            Group ticket &middot; {{ $singleTicket->admissions }} people
                                        @else
                                            Attendee 0{{ $index + 1 }}
                                        @endif
                                    </p>
                                    <h4 class="text-2xl font-black uppercase italic tracking-tighter leading-none text-slate-900">
                                        {{ $singleTicket->client->full_name }}
                                    </h4>
                                </div>
                                @if($loop->first)
                                    <span class="text-[9px] bg-[#1D4069]/10 text-[#1D4069] px-3 py-1 rounded-full font-black uppercase tracking-widest border border-[#1D4069]/20">Primary</span>
                                @endif
                            </div>

                            <div class="grid grid-cols-2 gap-4 border-t border-dashed border-slate-100 pt-6 mb-6">
                                <div>
                                    <p class="text-[9px] font-black text-slate-300 uppercase tracking-widest">Ticket ID</p>
                                    <p class="font-mono text-[10px] font-black text-slate-900 mt-1">{{ $singleTicket->ticket_number }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[9px] font-black text-slate-300 uppercase tracking-widest">Tier</p>
                                    <p class="text-[10px] font-black text-slate-900 mt-1 uppercase tracking-tight">{{ $singleTicket->tier->tier_name }}</p>
                                </div>
                            </div>

                            @if($ticket->payment_status === 'completed')
                                <a href="{{ route('ticket.download', $singleTicket->qr_code) }}" target="_blank"
                                   class="flex items-center justify-center w-full bg-[#F07F22] hover:bg-[#1D4069] text-white py-5 rounded-2xl transition-all group shadow-lg">
                                    <i class="fas fa-download mr-3 text-sm group-hover:translate-y-0.5 transition-transform"></i>
                                    <span class="text-[11px] font-black uppercase tracking-[0.3em]">Download Ticket</span>
                                </a>
                            @else
                                <div class="flex items-center justify-center w-full bg-slate-100 text-slate-400 py-5 rounded-2xl">
                                    <i class="fas fa-lock mr-3 text-sm"></i>
                                    <span class="text-[11px] font-black uppercase tracking-[0.3em]">Locked Until Payment is Confirmed</span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Payment Info (if pending) --}}
                @if($ticket->payment_status !== 'completed')
                    <div class="pt-10 border-t border-dashed border-slate-200 space-y-6">
                        <div class="flex justify-between items-end">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none">Investment Due</p>
                            <span class="text-4xl font-black tracking-tighter italic leading-none text-slate-900">M{{ number_format($allTickets->sum('amount')) }}</span>
                        </div>

                        @if($byHand ?? false)
                            <div class="p-6 bg-mint rounded-3xl text-mint-ink">
                                <p class="text-[13px] font-black"><i class="fas fa-magnifying-glass-dollar mr-1"></i>We're checking your EcoCash payment</p>
                                <p class="text-[12px] font-medium mt-1">VENTIQ is matching it with the merchant statement. Your place is held, and your ticket is sent as soon as it's confirmed, usually within a few hours. No need to pay again.</p>
                            </div>
                        @endif

                        @if($paymentMethodDetails)
                            <div class="p-6 bg-slate-900 rounded-3xl text-white">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="w-10 h-10 bg-[#F07F22] rounded-xl flex items-center justify-center">
                                        <i class="fas fa-info-circle text-white"></i>
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black text-[#F07F22] uppercase tracking-[0.3em]">Protocol Steps</p>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">{{ $paymentMethodDetails->payment_method }}</p>
                                    </div>
                                </div>
                                <p class="text-xs font-bold leading-relaxed text-slate-300 italic">
                                    "{{ $paymentMethodDetails->instructions }}"
                                </p>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Delivery Confirmation (if completed) --}}
                @if($ticket->payment_status === 'completed')
                    <div class="pt-6 border-t border-slate-100 grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($ticket->client->email)
                            <div class="bg-blue-50/50 border-2 border-blue-100 rounded-2xl p-6 text-center">
                                <i class="fas fa-envelope text-blue-500 text-2xl mb-3"></i>
                                <h4 class="text-[10px] font-black text-gray-900 uppercase tracking-widest mb-1">Sent to Email</h4>
                                <p class="text-[9px] font-bold text-blue-600 truncate">{{ $ticket->client->email }}</p>
                            </div>
                        @endif

                        @if($ticket->has_whatsapp)
                            <div class="bg-emerald-50/50 border-2 border-emerald-100 rounded-2xl p-6 text-center">
                                <i class="fa-brands fa-whatsapp text-emerald-500 text-3xl mb-3"></i>
                                <h4 class="text-[10px] font-black text-gray-900 uppercase tracking-widest mb-1">Sent to WhatsApp</h4>
                                <p class="text-[9px] font-bold text-emerald-600">+266 {{ str_replace('+266', '', $ticket->client->phone) }}</p>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- CTA --}}
                <div class="pt-4">
                    <a href="{{ route('public.event', ['orgSlug' => $event->organization->slug, 'eventSlug' => $event->slug]) }}" 
                       class="flex items-center justify-center gap-3 w-full py-5 border-2 border-slate-900 text-slate-900 rounded-2xl text-[10px] font-black uppercase tracking-[0.4em] hover:bg-slate-900 hover:text-white transition-all">
                        <span>Event Home</span>
                        <i class="fas fa-arrow-right text-[8px]"></i>
                    </a>
                </div>
            </div>
        </div>

        <p class="mt-8 text-center text-[9px] font-black text-slate-300 uppercase tracking-[1em] ml-[1em] opacity-50">Ventiq Protocol</p>
    </main>

@include('partials.cookie-notice')
</body>
</html>