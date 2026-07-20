@extends('layouts.app')
@section('title', 'New Session | VENTIQ')
@section('content')
<div class="max-w-2xl mx-auto px-4 py-12" x-data="{
    presenters: [''],
    judges: [''],
    addPresenter() { this.presenters.push(''); },
    removePresenter(i) { this.presenters.splice(i, 1); },
    addJudge() { this.judges.push(''); },
    removeJudge(i) { this.judges.splice(i, 1); },
}">
    <h1 class="text-2xl font-black text-[#1D4069] uppercase tracking-tight mb-1">New Session</h1>
    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-8">Set it up now, start capturing whenever you're ready</p>

    {{-- Type picker — only Presentation is real right now. The rest are
         shown, not hidden, so the roadmap is visible, but they're inert. --}}
    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 mb-10">
        <div class="px-3 py-3 rounded-xl bg-[#1D4069] text-white text-[10px] font-black uppercase tracking-wide text-center">Presentation</div>
        @foreach(['Meeting','Lecture','Workshop','Church Service','Brainstorming','Interview','Training','Board Meeting','Committee','Custom'] as $type)
            <div class="px-3 py-3 rounded-xl bg-gray-50 text-gray-300 text-[10px] font-black uppercase tracking-wide text-center cursor-not-allowed" title="Coming soon">
                {{ $type }}
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('sessions.store') }}" class="space-y-6">
        @csrf

        <div>
            <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1 mb-1 block">Presentation Name</label>
            <input type="text" name="title" required placeholder="e.g. Computer Science Presentations"
                   class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
        </div>

        <div>
            <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1 mb-1 block">Presenters</label>
            <div class="space-y-2">
                <template x-for="(p, i) in presenters" :key="i">
                    <div class="flex gap-2">
                       <input type="text" :name="`presenters[${i}]`" x-model="presenters[i]"
                            placeholder="Presenter name (optional — you can add them live)"
                            class="flex-1 bg-gray-50 border-none rounded-2xl px-5 py-3.5 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                        <button type="button" @click="removePresenter(i)" x-show="presenters.length > 1"
                                class="w-11 h-11 shrink-0 rounded-2xl bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-colors">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>
                </template>
            </div>
            <button type="button" @click="addPresenter()" class="mt-2 text-[10px] font-black text-[#1D4069] uppercase tracking-widest hover:text-[#F07F22]">
                + Add another presenter
            </button>
        </div>

        <div>
            <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1 mb-1 block">Expected Duration (minutes, optional)</label>
            <input type="number" name="expected_duration" min="1" placeholder="e.g. 15"
                   class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
        </div>

        <div>
            <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1 mb-1 block">Judges (optional)</label>
            <div class="space-y-2">
                <template x-for="(j, i) in judges" :key="i">
                    <div class="flex gap-2">
                        <input type="text" :name="`judges[${i}]`" x-model="judges[i]"
                               placeholder="Judge name"
                               class="flex-1 bg-gray-50 border-none rounded-2xl px-5 py-3.5 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                        <button type="button" @click="removeJudge(i)" x-show="judges.length > 1"
                                class="w-11 h-11 shrink-0 rounded-2xl bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-colors">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>
                </template>
            </div>
            <button type="button" @click="addJudge()" class="mt-2 text-[10px] font-black text-[#1D4069] uppercase tracking-widest hover:text-[#F07F22]">
                + Add a judge
            </button>
        </div>

        <div x-data="{ trackParticipants: false }">
            <label class="flex items-center justify-between px-5 py-4 bg-gray-50 rounded-2xl cursor-pointer">
                <div>
                    <p class="text-[11px] font-black text-[#1D4069] uppercase tracking-wide">Track Participants</p>
                    <p class="text-[9px] text-gray-400 font-bold mt-0.5">Open a QR / link so people register the moment they arrive</p>
                </div>
                <input type="checkbox" name="track_participants" value="1" x-model="trackParticipants" class="w-5 h-5 accent-[#1D4069]">
            </label>

            <div x-show="trackParticipants" x-transition class="mt-3">
                <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1 mb-1 block">Expected Participants (optional)</label>
                <input type="number" name="expected_participants" min="1" placeholder="e.g. 20"
                       class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                <p class="text-[9px] text-gray-400 font-bold mt-1 ml-1">Just for display — "12/20 checked in." No limit enforced.</p>
            </div>
        </div>

        <button type="submit" class="w-full py-5 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white font-black text-[10px] uppercase tracking-[0.3em] shadow-xl transition-all">
            Create Session
        </button>
    </form>
</div>
@endsection