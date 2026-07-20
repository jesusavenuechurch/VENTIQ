<div class="space-y-2">
    @foreach($recentSessions as $session)
        <a href="{{ $session->status === 'reported' ? route('sessions.report', $session) : route('sessions.show', $session) }}"
           class="block p-5 bg-white rounded-2xl border border-gray-100 hover:border-gray-200 hover:shadow-sm transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-black text-[#1D4069]">{{ $session->resolved_title }}</p>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mt-1">
                        {{ ucfirst($session->type) }} · {{ $session->date?->format('d M Y') ?? $session->created_at->format('d M Y') }}
                    </p>
                </div>
                <span class="text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-full bg-gray-50 text-gray-400">
                    {{ $session->status }}
                </span>
            </div>
        </a>
    @endforeach
</div>