@extends('layouts.app')
@section('title', $programme->name . ' | VENTIQ')
@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">

    <a href="{{ route('programmes.index') }}" class="text-[10px] font-bold text-gray-400 hover:text-[#1D4069] uppercase tracking-widest mb-2 inline-block">← All Programmes</a>

    <div class="mb-8 flex items-start justify-between gap-4">
        <div>
            <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.3em] mb-1">Ventiq</p>
            <p class="text-[15px] font-bold text-[#1D4069]">{{ $programme->name }}</p>
            <p class="text-[11px] text-gray-400 font-medium mt-1">
                @if($programme->duration_days > 1)
                    {{ $programme->event_date?->format('d M') }} – {{ $programme->event_date?->copy()->addDays($programme->duration_days - 1)->format('d M Y') }}
                @else
                    {{ $programme->event_date?->format('d M Y') }}
                @endif
                @if($programme->venue) · {{ $programme->venue }} @endif
            </p>
        </div>
        <a href="{{ route('sessions.create', ['event' => $programme->id]) }}"
           class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-[#F07F22] text-white text-[12px] font-black uppercase tracking-wide shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all shrink-0">
            + Add Session
        </a>
    </div>

    <a href="{{ route('programmes.report', $programme) }}"
       class="inline-flex items-center gap-1.5 mb-6 px-4 py-3 rounded-2xl bg-white border border-gray-100 hover:border-[#1D4069]/30 transition-all text-[10px] font-black text-[#1D4069] uppercase tracking-wide">
        📄 Programme Report
    </a>

    @if(session('status'))
        <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-100 text-[11px] font-bold text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @if($programme->certificates_enabled)
        <div class="mb-6 p-5 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-between gap-4">
            <div>
                <p class="text-[11px] font-black text-blue-700 uppercase tracking-wide">Certificates</p>
                <p class="text-[10px] text-blue-500 font-bold mt-0.5">
                    {{ $issuedCertificates->count() }} issued · {{ $eligibleCount }} eligible (checked in at least once)
                </p>
            </div>
            <form method="POST" action="{{ route('programmes.certificates.issue', $programme) }}">
                @csrf
                <button type="submit" class="px-5 py-3 rounded-2xl bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest hover:bg-blue-700 transition-all">
                    Issue Certificates
                </button>
            </form>
        </div>

        @if($issuedCertificates->isNotEmpty())
            <div class="mb-6 space-y-2">
                @foreach($issuedCertificates as $certificate)
                    <a href="{{ route('certificates.verify', $certificate->token) }}" target="_blank"
                       class="flex items-center justify-between px-5 py-3 rounded-2xl bg-white border border-gray-100 hover:border-[#1D4069]/30 hover:shadow-sm transition-all">
                        <div>
                            <p class="text-[11px] font-bold text-[#1D4069]">{{ $certificate->client?->full_name ?? 'Unknown' }}</p>
                            <p class="text-[9px] text-gray-400 font-bold mt-0.5">Issued {{ $certificate->issued_at->format('d M Y') }}</p>
                        </div>
                        <span class="text-[9px] font-black uppercase tracking-widest text-blue-500">View Certificate ↗</span>
                    </a>
                @endforeach
            </div>
        @endif
    @endif

    <div class="space-y-3">
        @forelse($sessions as $session)
            <a href="{{ $session->status === 'reported' ? route('sessions.report', $session) : route('sessions.show', $session) }}"
               class="flex items-center justify-between px-5 py-4 rounded-2xl bg-white border border-gray-100 hover:border-[#1D4069]/30 hover:shadow-sm transition-all">
                <div>
                    <p class="text-[12px] font-bold text-[#1D4069]">{{ $session->resolved_title }}</p>
                    <p class="text-[10px] text-gray-400 font-bold mt-0.5">
                        {{ $session->date?->format('d M Y') }}
                        @if($session->start_time) · {{ \Carbon\Carbon::parse($session->start_time)->format('g:i A') }} @endif
                    </p>
                </div>
                <span class="text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-full
                    {{ match($session->status) {
                        'draft' => 'bg-gray-50 text-gray-400',
                        'active' => 'bg-amber-50 text-amber-600',
                        'completed' => 'bg-blue-50 text-blue-500',
                        'reported' => 'bg-emerald-50 text-emerald-600',
                        default => 'bg-gray-50 text-gray-400',
                    } }}">
                    {{ ucfirst($session->status) }}
                </span>
            </a>
        @empty
            <p class="text-[11px] text-gray-300 italic text-center py-16">No sessions yet — add the first one above.</p>
        @endforelse
    </div>

</div>
@endsection