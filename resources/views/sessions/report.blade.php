@extends('layouts.app')
@section('title', $session->resolved_title . ' — Report | VENTIQ')
@section('content')
<div class="max-w-6xl mx-auto px-4 py-12"
     x-data="{
        status: '{{ $session->status }}',
        pollHandle: null,
        generationFailed: false,
        retrying: false,
        editing: false,
        reportText: {{ Illuminate\Support\Js::from($session->session_report ?? '') }},
        saving: false,
        savedJustNow: false,
        openSegment: null,

        init() {
            if (this.status !== 'reported' && this.status !== 'draft' && this.status !== 'active') {
                this.pollHandle = setInterval(() => this.checkStatus(), 3000);
            }
        },

        async checkStatus() {
            try {
                const res = await fetch('{{ route('sessions.report.status', $session) }}');
                const data = await res.json();
                if (data.ready) {
                    clearInterval(this.pollHandle);
                    window.location.reload();
                } else if (data.failed) {
                    clearInterval(this.pollHandle);
                    this.generationFailed = true;
                }
            } catch (e) { /* keep polling, network hiccup */ }
        },

        async retryGeneration() {
            this.retrying = true;
            try {
                await fetch('{{ route('sessions.report.generate', $session) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    }
                });
                this.generationFailed = false;
                this.pollHandle = setInterval(() => this.checkStatus(), 3000);
            } finally {
                this.retrying = false;
            }
        },

        async saveReport() {
            this.saving = true;
            try {
                await fetch('{{ route('sessions.report.update', $session) }}', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    },
                    body: JSON.stringify({ session_report: this.reportText })
                });
                this.editing = false;
                this.savedJustNow = true;
                setTimeout(() => this.savedJustNow = false, 2000);
            } finally {
                this.saving = false;
            }
        }
     }">

    @if(session('status'))
        <div class="mb-6 p-4 bg-amber-50 text-amber-700 rounded-2xl text-[11px] font-bold">{{ session('status') }}</div>
    @endif

    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-black text-[#1D4069] uppercase tracking-tight">{{ $session->resolved_title }}</h1>
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mt-1">
                {{ $session->date?->format('d M Y') ?? $session->created_at->format('d M Y') }}
                @if($session->reviewed_at)
                    <span class="text-emerald-500">· Reviewed {{ $session->reviewed_at->diffForHumans() }}</span>
                @endif
            </p>
        </div>
        <a href="{{ route('sessions.index') }}" class="text-[10px] font-black text-[#1D4069] uppercase tracking-widest hover:text-[#F07F22]">← Back to Sessions</a>
    </div>

    @if($session->status === 'draft' || $session->status === 'active')
        <div class="text-center py-20 border border-dashed border-gray-200 rounded-[2rem]">
            <p class="text-sm font-bold text-gray-400">This session is still in progress — finish every presenter first.</p>
        </div>

    @elseif($session->status !== 'reported')
        <div class="text-center py-20 border border-dashed border-gray-200 rounded-[2rem]" x-show="!generationFailed">
            <div class="w-10 h-10 border-4 border-gray-100 border-t-[#F07F22] rounded-full animate-spin mx-auto mb-6"></div>
            <p class="text-sm font-bold text-gray-500 mb-1">Putting your report together…</p>
            <p class="text-[10px] font-bold text-gray-300 uppercase tracking-widest">This usually takes a few seconds. Feel free to head back — we'll have it ready.</p>
            <a href="{{ route('sessions.index') }}" class="inline-block mt-6 px-6 py-3 rounded-full bg-gray-50 text-[#1D4069] text-[10px] font-black uppercase tracking-widest hover:bg-gray-100 transition-all">
                ← Back to Sessions
            </a>
        </div>

        <div class="text-center py-20 border border-dashed border-rose-200 rounded-[2rem] bg-rose-50/30" x-show="generationFailed" x-cloak>
            <p class="text-sm font-bold text-rose-600 mb-1">Report generation failed</p>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-6">The raw notes are still safe — nothing was lost. You can try generating the report again.</p>
            <button @click="retryGeneration()" :disabled="retrying" type="button"
                class="px-6 py-3 rounded-full bg-[#1D4069] text-white text-[10px] font-black uppercase tracking-widest hover:bg-[#F07F22] transition-all">
                <span x-show="!retrying">Try Again</span>
                <span x-show="retrying">Retrying…</span>
            </button>
        </div>

    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- LEFT — the AI report itself, editable, same behaviour as before --}}
            <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 p-8">
                <div class="flex items-center justify-between mb-5">
                    <span class="text-[10px] font-black uppercase tracking-widest" :class="savedJustNow ? 'text-emerald-500' : 'text-gray-300'"
                          x-text="savedJustNow ? 'Saved ✓' : (editing ? 'Editing' : '')"></span>
                    <div class="flex gap-2 flex-wrap justify-end">
                        <button x-show="!editing" @click="editing = true" type="button"
                                class="px-4 py-2 rounded-full bg-gray-50 text-[#1D4069] text-[9px] font-black uppercase tracking-widest hover:bg-gray-100 transition-all">
                            Edit
                        </button>
                        <button x-show="editing" @click="saveReport()" :disabled="saving" type="button"
                                class="px-4 py-2 rounded-full bg-[#1D4069] text-white text-[9px] font-black uppercase tracking-widest hover:bg-[#F07F22] transition-all">
                            <span x-show="!saving">Save</span>
                            <span x-show="saving">Saving…</span>
                        </button>
                        <a href="{{ route('sessions.report.pdf', $session) }}"
                           class="px-4 py-2 rounded-full border border-gray-200 text-[#1D4069] text-[9px] font-black uppercase tracking-widest hover:border-[#1D4069] transition-all">
                            Export PDF
                        </a>
                        @if($session->event_id)
                            <a href="{{ route('sessions.checkin.pdf', $session) }}"
                               class="px-4 py-2 rounded-full border border-gray-200 text-[#1D4069] text-[9px] font-black uppercase tracking-widest hover:border-[#1D4069] transition-all">
                                Export Roster
                            </a>
                        @endif
                    </div>
                </div>

                <pre x-show="!editing" class="whitespace-pre-wrap text-[13px] font-medium text-gray-700 leading-relaxed" style="font-family: inherit;">{{ $session->session_report }}</pre>

                <textarea x-show="editing" x-model="reportText" rows="18"
                          class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 text-[13px] font-medium text-gray-700 leading-relaxed outline-none focus:ring-2 focus:ring-[#F07F22]/20 resize-none"></textarea>

                <div class="mt-8 pt-6 border-t border-gray-100 flex items-center justify-between">
                    @if($session->reviewed_at)
                        <span class="text-[10px] font-black text-emerald-500 uppercase tracking-widest">✓ Reviewed {{ $session->reviewed_at->format('d M Y, g:i A') }}</span>
                    @else
                        <span class="text-[10px] font-bold text-gray-400">Not yet marked as reviewed.</span>
                    @endif

                    <form method="POST" action="{{ route('sessions.report.review', $session) }}">
                        @csrf
                        <button type="submit"
                            class="px-5 py-3 rounded-2xl {{ $session->reviewed_at ? 'bg-gray-50 text-gray-400' : 'bg-[#F07F22] text-white hover:shadow-lg' }} text-[10px] font-black uppercase tracking-widest transition-all">
                            {{ $session->reviewed_at ? 'Reviewed ✓' : 'Mark as Reviewed' }}
                        </button>
                    </form>
                </div>
            </div>

            {{-- RIGHT — raw source notepad: what the AI actually saw, per segment --}}
            <div class="bg-[#F7F3EC] rounded-2xl p-5">
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-4">Source Notes</p>
                <div class="space-y-3">
                    @forelse($session->segments as $segment)
                        <div x-data="{ open: false }" class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                            <button @click="open = !open" type="button" class="w-full flex items-center justify-between px-4 py-3 text-left">
                                <div>
                                    <p class="text-[11px] font-bold text-[#1D4069]">{{ $segment->presenter_name }}</p>
                                    @if($segment->role)
                                        <p class="text-[9px] text-gray-400 font-bold">{{ $segment->role }}</p>
                                    @endif
                                </div>
                                <span class="text-gray-300 text-xs" x-text="open ? '−' : '+'"></span>
                            </button>

                            <div x-show="open" x-collapse class="px-4 pb-4">
                                @if($segment->ai_summary)
                                    <p class="text-[9px] font-black text-gray-400 uppercase tracking-wide mb-1 mt-2">AI Summary</p>
                                    <p class="text-[11px] text-gray-600 font-medium mb-3">{{ $segment->ai_summary['summary'] ?? '' }}</p>
                                @endif

                                @if($segment->raw_log)
                                    <p class="text-[9px] font-black text-gray-400 uppercase tracking-wide mb-1">Raw Capture</p>
                                    <div class="space-y-1 max-h-48 overflow-y-auto pr-1">
                                        @foreach($segment->raw_log as $line)
                                            <p class="text-[10px] text-gray-500">
                                                <span class="text-gray-300 font-mono">{{ $line['time'] ?? '' }}</span>
                                                {{ $line['text'] ?? '' }}
                                            </p>
                                        @endforeach
                                    </div>
                                @endif

                                @if(!$segment->ai_summary && !$segment->raw_log)
                                    <p class="text-[10px] text-gray-300 italic mt-2">Nothing captured for this segment.</p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-[10px] text-gray-400 italic">No segments recorded.</p>
                    @endforelse
                </div>
            </div>

        </div>
    @endif
</div>
@endsection