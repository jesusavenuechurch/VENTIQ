<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventiq Pass - {{ $ticket->event->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        
        :root {
            --tier-color: {{ str_contains(strtolower($ticket->tier->tier_name), 'vip') ? '#D4AF37' : '#10b981' }};
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #000;
        }

        .ticket-shadow {
            box-shadow: 0 40px 80px -20px rgba(0,0,0,0.6);
        }

        /* Perforation Line */
        .divider-dots {
            background-image: radial-gradient(#cbd5e1 30%, transparent 30%);
            background-position: center;
            background-size: 1px 18px;
            width: 2px;
            height: 100%;
        }

        /* Fix for potential text clipping in html2canvas */
        .safe-area-padding {
            padding-bottom: 0.5rem;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6 bg-[radial-gradient(circle_at_top,_var(--tw-gradient-stops))] from-slate-900 via-black to-black">
    
    <div class="w-full max-w-2xl animate-in fade-in duration-700">
        
        <div class="flex justify-between items-center mb-6 px-4">
            <div class="flex items-center space-x-2">
                <span class="text-white font-black tracking-tighter text-sm uppercase">Ventiq</span>
                <span class="w-1 h-1 rounded-full bg-white/20"></span>
                <span class="text-white/40 font-bold text-[9px] uppercase tracking-[0.3em]">Digital Fulfillment</span>
            </div>
            <div class="px-3 py-1 rounded-full border border-white/10">
                <span class="text-white/60 font-bold text-[9px] uppercase tracking-widest">{{ $ticket->ticket_number }}</span>
            </div>
        </div>

        <div id="ticket-capture" class="ticket-shadow">
            <div class="bg-white rounded-[2.5rem] overflow-hidden flex flex-col md:flex-row relative">
                
                <div class="flex-grow p-10 md:p-12 relative bg-white">
                    <div class="absolute -bottom-6 -left-8 opacity-[0.03] select-none pointer-events-none transform -rotate-12">
                        <h1 class="text-[120px] font-black italic">VENTIQ</h1>
                    </div>

                    <div class="relative z-10">
                        <div class="mb-12">
                            <div class="inline-block px-4 py-1.5 rounded-lg mb-4 text-[11px] font-black text-white uppercase tracking-widest shadow-sm" style="background-color: var(--tier-color)">
                                {{ $ticket->tier->tier_name }}
                            </div>
                            <h2 class="text-3xl font-black text-slate-900 tracking-tighter uppercase leading-none">{{ $ticket->event->name }}</h2>
                        </div>

                        <div class="grid grid-cols-2 gap-y-10 gap-x-8">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Guest Name</p>
                                <p class="text-md font-extrabold text-slate-900 uppercase leading-tight">{{ $ticket->client->full_name }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Date & Time</p>
                                <p class="text-md font-extrabold text-slate-900 uppercase leading-tight">{{ $ticket->event->event_date->format('d M Y') }}</p>
                            </div>
                            <div class="col-span-2 safe-area-padding">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Venue Location</p>
                                <p class="text-md font-extrabold text-slate-900 uppercase leading-relaxed max-w-sm">
                                    {{ $ticket->event->location }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-12 flex items-center space-x-2 opacity-40">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-900">{{ $ticket->event->organization->name }}</span>
                        </div>
                    </div>
                </div>

                <div class="w-full md:w-72 bg-slate-50/80 p-10 flex flex-col items-center justify-center relative min-h-[320px]">
                    <div class="hidden md:block absolute left-0 top-10 bottom-10">
                        <div class="divider-dots"></div>
                    </div>

                    <div class="bg-white p-4 rounded-3xl shadow-md border border-slate-200 w-44 h-44 flex items-center justify-center">
                        @if($ticket->qr_code_path)
                            <img src="{{ Storage::url($ticket->qr_code_path) }}" 
                                 crossorigin="anonymous" 
                                 alt="QR Code" 
                                 class="w-full h-full aspect-square object-contain">
                        @else
                            <i class="fas fa-qrcode text-slate-200 text-5xl"></i>
                        @endif
                    </div>

                    <div class="mt-8 text-center">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]">Entry Pass</p>
                        <p class="text-[9px] font-bold text-slate-400 mt-1 uppercase">Scan at Entrance</p>
                    </div>
 
                    {{-- Voucher code — shown below QR on web view --}}
                    @if($ticket->voucher_code)
                    <div class="mt-6 px-4 py-3 bg-white border-2 border-dashed border-slate-200 rounded-2xl text-center w-full">
                        <p class="text-[8px] font-black text-slate-400 uppercase tracking-[0.25em] mb-1">
                            Entry Code
                        </p>
                        <p class="text-2xl font-black text-slate-900 tracking-[0.2em]">
                            {{ $ticket->voucher_code }}
                        </p>
                        <p class="text-[8px] text-slate-400 mt-1">
                            Show this if you cannot scan
                        </p>
                    </div>
                    @endif

                    <div class="absolute bottom-6 flex items-center space-x-1.5 opacity-20">
                        <span class="text-[8px] font-bold uppercase">Powered by</span>
                        <span class="text-[10px] font-black tracking-tighter uppercase">Ventiq</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-10 grid grid-cols-2 gap-4">
            <button id="download-png" class="flex items-center justify-center space-x-3 bg-white text-black py-5 rounded-2xl font-black text-[10px] uppercase tracking-[0.2em] hover:bg-slate-100 transition-all">
                <i class="fas fa-camera text-sm"></i>
                <span>Save Image</span>
            </button>
            <a href="{{ route('ticket.avatar.download', $qrCode) }}" class="flex items-center justify-center space-x-3 bg-white/5 border border-white/10 text-white py-5 rounded-2xl font-black text-[10px] uppercase tracking-[0.2em] hover:bg-white/10 transition-all">
                <i class="fas fa-file-pdf text-red-500 text-sm"></i>
                <span>PDF Ticket</span>
            </a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        document.getElementById('download-png').addEventListener('click', function() {
            const ticketArea = document.getElementById('ticket-capture');
            const btn = this;
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<span>Processing...</span>';
            
            html2canvas(ticketArea, {
                scale: 3, 
                useCORS: true, 
                backgroundColor: null,
                logging: false,
                onclone: (clonedDoc) => {
                    // Ensures the capture has rounded corners without border artifacts
                    clonedDoc.getElementById('ticket-capture').style.borderRadius = '2.5rem';
                }
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'Ventiq-{{ $ticket->ticket_number }}.png';
                link.href = canvas.toDataURL('image/png', 1.0);
                link.click();
                btn.innerHTML = originalText;
            });
        });
    </script>
</body>
</html>