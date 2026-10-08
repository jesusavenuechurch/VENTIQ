@extends('layouts.app')
@section('title', 'Schedule Session | VENTIQ')
@section('content')
<div class="max-w-2xl mx-auto px-4 py-12" x-data="{
    selectedType: 'presentation',
    customLabel: '',
    people: [],
    newName: '',
    newRole: '',
    newPresenting: true,
    roleOptions: {{ Illuminate\Support\Js::from(collect(\App\Support\SessionType::keys())->mapWithKeys(fn($k) => [$k => collect(\App\Support\SessionType::roles($k))->map(fn($r,$key) => ['value'=>$key,'label'=>$r['label']])->values()])) }},
    addPerson() {
        const name = this.newName.trim();
        if (!name) return;
        this.people.push({ name, role: this.newRole, presenting: this.newPresenting });
        this.newName = ''; this.newRole = ''; this.newPresenting = true;
    },
    removePerson(i) { this.people.splice(i, 1); },
}">
    <h1 class="text-2xl font-black text-[#1D4069] uppercase tracking-tight mb-1">Schedule a Session</h1>
    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-8">Set it up now — start it live today, or line it up for later</p>

    <div class="mb-6">
        <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1 mb-1 block">Session Type</label>
        <select x-model="selectedType"
                class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold text-[#1D4069] uppercase text-[11px] tracking-wide outline-none focus:ring-2 focus:ring-[#F07F22]/20">
            @foreach(\App\Support\SessionType::keys() as $key)
                <option value="{{ $key }}">{{ \App\Support\SessionType::label($key) }}</option>
            @endforeach
        </select>
    </div>

    <div x-show="selectedType === 'custom'" x-transition class="mb-8">
        <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1 mb-1 block">Custom Session Type</label>
        <input type="text" x-model="customLabel" placeholder="e.g. Retreat, Site Visit, Assessment"
               class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
    </div>

    <form method="POST" action="{{ route('sessions.store') }}" class="space-y-6">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1 mb-1 block">Session Name</label>
                <input type="text" name="title" required placeholder="e.g. Computer Science Presentations"
                    class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
            </div>
            <div>
                <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1 mb-1 block">Date</label>
                <input type="date" name="date" value="{{ now()->toDateString() }}"
                    class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
            </div>
        </div>
        <input type="hidden" name="type" :value="selectedType">
        <input type="hidden" name="custom_type_label" :value="selectedType === 'custom' ? customLabel : ''">

        <div>
            <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1 mb-1 block">People leading this session</label>

            <div class="flex flex-wrap gap-2 mb-3" x-show="people.length">
                <template x-for="(p, i) in people" :key="i">
                    <span class="inline-flex items-center gap-2 pl-3 pr-2 py-1.5 rounded-full bg-gray-50 text-[11px] font-bold text-[#1D4069]">
                        <span x-text="p.name"></span>
                        <span class="text-gray-400 font-normal" x-text="p.role ? '· ' + p.role : ''"></span>
                        <span class="text-[9px] font-black uppercase tracking-wide" :class="p.presenting ? 'text-emerald-500' : 'text-gray-400'"
                              x-text="p.presenting ? 'Presenting' : 'Not presenting'"></span>
                        <button type="button" @click="removePerson(i)" class="text-gray-300 hover:text-red-500">&times;</button>
                        <input type="hidden" :name="`people[${i}][name]`" :value="p.name">
                        <input type="hidden" :name="`people[${i}][role]`" :value="p.role">
                        <input type="hidden" :name="`people[${i}][presenting]`" :value="p.presenting ? 1 : 0">
                    </span>
                </template>
            </div>

            <div class="flex gap-2 items-center">
                <input type="text" x-model="newName" @keydown.enter.prevent="addPerson()"
                       placeholder="Name"
                       class="flex-1 bg-gray-50 border-none rounded-2xl px-5 py-3.5 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                <select x-model="newRole"
                        class="w-40 bg-gray-50 border-none rounded-2xl px-3 py-3.5 font-bold text-sm outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                    <option value="">Role…</option>
                    <template x-for="opt in (roleOptions[selectedType] || [])" :key="opt.value">
                        <option :value="opt.value" x-text="opt.label"></option>
                    </template>
                </select>
                <label class="flex items-center gap-1.5 text-[10px] font-black text-gray-500 uppercase tracking-wide whitespace-nowrap">
                    <input type="checkbox" x-model="newPresenting" class="accent-[#1D4069]"> Presenting
                </label>
                <button type="button" @click="addPerson()"
                        class="w-11 h-11 shrink-0 rounded-2xl bg-[#1D4069] text-white hover:bg-[#F07F22] transition-colors">+</button>
            </div>
        </div>

        <div>
            <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1 mb-1 block">Expected Duration (minutes, optional)</label>
            <input type="number" name="expected_duration" min="1" placeholder="e.g. 15"
                   class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
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

        @if($errors->any())
            <div class="mb-4 p-4 bg-rose-50 text-rose-600 rounded-2xl text-[11px] font-bold">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <button type="submit" class="w-full py-5 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white font-black text-[10px] uppercase tracking-[0.3em] shadow-xl transition-all">
            Schedule Session
        </button>
    </form>
</div>
@endsection