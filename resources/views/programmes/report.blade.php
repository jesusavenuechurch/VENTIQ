@extends('layouts.app')
@section('title', $programme->name . ' — Report | VENTIQ')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-12"
     x-data="{
        pollHandle: null,
        generationFailed: false,
        retrying: false,
        hasReport: {{ $programme->programme_report ? 'true' : 'false' }},

        init() {
            if (!this.hasReport) {
                this.pollHandle = setInterval(() => this.checkStatus(), 3000);
            }
        },

        async checkStatus() {
            try {
                const res = await fetch('{{ route('programmes.report.status', $programme) }}');
                const data = await res.json();
                if (data.ready) {
                    clearInterval(this.pollHandle);
                    window.location.reload();
                } else if (data.failed) {
                    clearInterval(this.pollHandle);
                    this.generationFailed = true;
                }
            } catch (e) { /* keep polling */ }
        },

        async retryGeneration() {
            this.retrying = true;
            try {
                await fetch('{{ route('programmes.report.generate', $programme) }}', {
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
        }
     }">

    @if(session('status'))
        <div class="mb-6 p-4 bg-amber-50 text-amber-700 rounded-2xl text-[11px] font-bold">{{ session('status') }}</div>
    @endif

    <div class="flex items-center justify-between mb-8">
        <div>
            <a href="{{ route('programmes.show', $programme) }}" class="text-[10px] font-bold text-gray-400 hover:text-[#1D4069] uppercase tracking-widest mb-2 inline-block">← Back to Programme</a>
            <h1 class="text-2xl font-black text-[#1D4069] uppercase tracking-tight">{{ $programme->name }} — Report</h1>
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mt-1">
                {{ $reportedCount }} of {{ $sessions->count() }} sessions reported
            </p>
        </div>
    </div>

    @if($sessions->isEmpty() || $reportedCount === 0)
        <div class="text-center py-20 border border-dashed border-gray-200 rounded-[2rem]">
            <p class="text-sm font-bold text-gray-400">No sessions have a completed report yet — this fills in as each session gets reported.</p>
        </div>

    @elseif(!$programme->programme_report)
        <div class="mb-8 p-6 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-between">
            <div>
                <p class="text-[12px] font-black text-blue-700 uppercase tracking-wide">Ready to generate</p>
                <p class="text-[10px] text-blue-500 font-bold mt-0.5">{{ $reportedCount }} session(s) ready to roll up into one Programme report.</p>
            </div>
            <form method="POST" action="{{ route('programmes.report.generate', $programme) }}">
                @csrf
                <button type="submit" class="px-5 py-3 rounded-2xl bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest hover:bg-blue-700 transition-all">
                    Generate Report
                </button>
            </form>
        </div>

        <div class="text-center py-20 border border-dashed border-gray-200 rounded-[2rem]" x-show="pollHandle && !generationFailed" x-cloak>
            <div class="w-10 h-10 border-4 border-gray-100 border-t-[#F07F22] rounded-full animate-spin mx-auto mb-6"></div>
            <p class="text-sm font-bold text-gray-500">Putting the Programme report together…</p>
        </div>

        <div class="text-center py-20 border border-dashed border-rose-200 rounded-[2rem] bg-rose-50/30" x-show="generationFailed" x-cloak>
            <p class="text-sm font-bold text-rose-600 mb-1">Report generation failed</p>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-6">Nothing was lost — the individual session reports are all still there.</p>
            <button @click="retryGeneration()" :disabled="retrying" type="button"
                class="px-6 py-3 rounded-full bg-[#1D4069] text-white text-[10px] font-black uppercase tracking-widest hover:bg-[#F07F22] transition-all">
                <span x-show="!retrying">Try Again</span>
                <span x-show="retrying">Retrying…</span>
            </button>
        </div>

    @else
        <div class="bg-white rounded-2xl border border-gray-100 p-8 mb-6">
            <pre class="whitespace-pre-wrap text-[13px] font-medium text-gray-700 leading-relaxed" style="font-family: inherit;">{{ $programme->programme_report }}</pre>
        </div>

        <form method="POST" action="{{ route('programmes.report.generate', $programme) }}">
            @csrf
            <button type="submit" class="text-[10px] font-bold text-gray-400 hover:text-[#1D4069] uppercase tracking-widest underline">
                Regenerate (picks up any newly reported sessions)
            </button>
        </form>
    @endif

</div>
@endsection