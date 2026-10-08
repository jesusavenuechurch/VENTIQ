<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Initialized | VENTIQ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        @keyframes pulse-soft {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .animate-pulse-soft { animation: pulse-soft 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    </style>
</head>
<body class="bg-[#0b0f1a] text-slate-300 min-h-screen flex items-center justify-center p-6 selection:bg-emerald-500">
    
    <div class="max-w-lg w-full">
        
        <div class="text-center mb-8">
             <div class="inline-block bg-emerald-600 text-white font-black px-3 py-1 rounded text-xl tracking-tighter mb-4">
                VENTI<span class="text-[#F07F22]">Q</span>
            </div>
        </div>

        <div class="bg-slate-900/50 border border-slate-800 rounded-[3rem] shadow-2xl overflow-hidden backdrop-blur-xl relative">
            
            <div class="absolute -top-24 -left-24 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl"></div>
            
            <div class="p-10 sm:p-12 text-center relative z-10">
                
                <div class="relative mx-auto mb-8 w-24 h-24">
                    <div class="absolute inset-0 bg-emerald-500/20 rounded-3xl rotate-12 animate-pulse-soft"></div>
                    <div class="absolute inset-0 bg-emerald-500/10 rounded-3xl -rotate-12 animate-pulse-soft" style="animation-delay: 1s;"></div>
                    <div class="relative w-24 h-24 bg-slate-950 border border-emerald-500/30 rounded-3xl flex items-center justify-center text-emerald-500 shadow-2xl">
                        <i class="fas fa-check-double text-3xl"></i>
                    </div>
                </div>

                <h1 class="text-3xl font-black text-white italic uppercase tracking-tight mb-2">System Initialized</h1>
                <p class="text-slate-500 font-bold text-xs uppercase tracking-widest mb-10">
                    Welcome to the fold, <span class="text-emerald-400">{{ $organization->name }}</span>
                </p>

                <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-6 mb-10 space-y-4">
                    <div class="flex items-center gap-4 text-left">
                        <div class="flex-shrink-0 w-5 h-5 rounded-full bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center">
                            <i class="fas fa-check text-[10px] text-emerald-500"></i>
                        </div>
                        <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">Database Record Created</p>
                    </div>
                    <div class="flex items-center gap-4 text-left">
                        <div class="flex-shrink-0 w-5 h-5 rounded-full bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center">
                            <i class="fas fa-check text-[10px] text-emerald-500"></i>
                        </div>
                        <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">Organization ID Linked</p>
                    </div>
                    <div class="flex items-center gap-4 text-left">
                        <div class="flex-shrink-0 w-5 h-5 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center">
                            <div class="w-1.5 h-1.5 bg-indigo-500 rounded-full animate-pulse"></div>
                        </div>
                        <p class="text-[11px] font-black text-white uppercase tracking-widest">Awaiting First Event Setup</p>
                    </div>
                </div>

                <a href="/admin"
                   class="flex items-center justify-center gap-4 w-full bg-emerald-600 text-white font-black py-5 rounded-2xl shadow-[0_0_30px_rgba(16,185,129,0.2)] hover:bg-emerald-500 hover:-translate-y-1 active:scale-[0.98] transition-all uppercase tracking-[0.3em] text-sm">
                    Access Console <i class="fas fa-terminal opacity-50 text-xs"></i>
                </a>

                <div class="mt-10 pt-8 border-t border-slate-800/50">
                    <p class="text-[10px] text-slate-600 font-black uppercase tracking-[0.2em] leading-relaxed">
                        Technical Assistance Needed? <br>
                        <a href="mailto:support@ventiq.co.ls" class="text-slate-400 hover:text-emerald-400 transition-colors">support@ventiq.co.ls</a>
                    </p>
                </div>
            </div>
        </div>

        <p class="text-center mt-8 mono text-[9px] text-slate-700 uppercase tracking-widest">
            VNTQ-SYS-LOG: {{ now()->format('Y.m.d.H.i.s') }} // SUCCESS
        </p>
    </div>

@include('partials.cookie-notice')
</body>
</html>