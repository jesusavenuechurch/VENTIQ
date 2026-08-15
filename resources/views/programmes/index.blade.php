@extends('layouts.app')
@section('title', 'Programmes | VENTIQ')
@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">

    <a href="{{ route('sessions.index') }}" class="text-[10px] font-bold text-gray-400 hover:text-[#1D4069] uppercase tracking-widest mb-2 inline-block">← Back to Desk</a>

    <div class="mb-8 flex items-start justify-between gap-4">
        <div>
            <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.3em] mb-1">Ventiq</p>
            <p class="text-[15px] font-bold text-[#1D4069]">Programmes</p>
            <p class="text-[11px] text-gray-400 font-medium mt-1">For anything with more than one session — trainings, conferences, multi-day events.</p>
        </div>
        <a href="{{ route('programmes.create') }}"
           class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-[#F07F22] text-white text-[12px] font-black uppercase tracking-wide shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all shrink-0">
            + New Programme
        </a>
    </div>

    @if(session('status'))
        <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-100 text-[11px] font-bold text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-3">
        @forelse($programmes as $programme)
            <a href="{{ route('programmes.show', $programme) }}"
               class="flex items-center justify-between px-5 py-4 rounded-2xl bg-white border border-gray-100 hover:border-[#1D4069]/30 hover:shadow-sm transition-all">
                <div>
                    <p class="text-[13px] font-bold text-[#1D4069]">{{ $programme->name }}</p>
                    <p class="text-[10px] text-gray-400 font-bold mt-0.5">
                        @if($programme->duration_days > 1)
                            {{ $programme->event_date?->format('d M') }} – {{ $programme->event_date?->copy()->addDays($programme->duration_days - 1)->format('d M Y') }}
                        @else
                            {{ $programme->event_date?->format('d M Y') }}
                        @endif
                    </p>
                </div>
                <span class="text-[9px] font-black text-[#1D4069] uppercase tracking-widest bg-gray-50 px-3 py-1.5 rounded-full">
                    {{ $programme->sessions_count }} {{ Str::plural('Session', $programme->sessions_count) }}
                </span>
            </a>
        @empty
            <p class="text-[11px] text-gray-300 italic text-center py-16">
                No programmes yet. Use this when a single Session isn't enough — a training with multiple days, a conference with multiple tracks.
            </p>
        @endforelse
    </div>

</div>
@endsection