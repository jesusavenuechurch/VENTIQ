@extends('layouts.app')
@section('title', $session->resolved_title . ' — Report | VENTIQ')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-12"
     x-data="{
        status: '{{ $session->status }}',
        pollHandle: null,
        editing: false,
        reportText: {{ Illuminate\Support\Js::from($session->session_report ?? '') }},
        saving: false,
        savedJustNow: false,

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
                }
            } catch (e) { /* keep polling, network hiccup */ }
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
            </p>
        </div>
        <a href="{{ route('sessions.index') }}" class="text-[10px] font-black text-[#1D4069] uppercase tracking-widest hover:text-[#F07F22]">← Back to Sessions</a>
    </div>

    @if($session->status === 'draft' || $session->status === 'active')
        <div class="text-center py-20 border border-dashed border-gray-200 rounded-[2rem]">
            <p class="text-sm font-bold text-gray-400">This session is still in progress — finish every presenter first.</p>
        </div>

    @elseif($session->status !== 'reported')
        {{-- job has been queued (finishSegment already triggers this) — just waiting on it now --}}
        <div class="text-center py-20 border border-dashed border-gray-200 rounded-[2rem]">
            <div class="w-10 h-10 border-4 border-gray-100 border-t-[#F07F22] rounded-full animate-spin mx-auto mb-6"></div>
            <p class="text-sm font-bold text-gray-500 mb-1">Putting your report together…</p>
            <p class="text-[10px] font-bold text-gray-300 uppercase tracking-widest">This usually takes a few seconds. Feel free to head back — we'll have it ready.</p>
            <a href="{{ route('sessions.index') }}" class="inline-block mt-6 px-6 py-3 rounded-full bg-gray-50 text-[#1D4069] text-[10px] font-black uppercase tracking-widest hover:bg-gray-100 transition-all">
                ← Back to Sessions
            </a>
        </div>

    @else
        <div class="bg-white rounded-2xl border border-gray-100 p-8">
            <div class="flex items-center justify-between mb-5">
                <span class="text-[10px] font-black uppercase tracking-widest" :class="savedJustNow ? 'text-emerald-500' : 'text-gray-300'"
                      x-text="savedJustNow ? 'Saved ✓' : (editing ? 'Editing' : '')"></span>
                <div class="flex gap-2">
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
                </div>
            </div>

            <pre x-show="!editing" class="whitespace-pre-wrap text-[13px] font-medium text-gray-700 leading-relaxed" style="font-family: inherit;">{{ $session->session_report }}</pre>

            <textarea x-show="editing" x-model="reportText" rows="18"
                      class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 text-[13px] font-medium text-gray-700 leading-relaxed outline-none focus:ring-2 focus:ring-[#F07F22]/20 resize-none"></textarea>
        </div>
    @endif
</div>
@endsection