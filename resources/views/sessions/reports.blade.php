@extends('layouts.app')
@section('title', 'Reports | VENTIQ')
@section('content')

<div class="max-w-4xl mx-auto px-4 py-8">

    <a href="{{ route('sessions.index') }}" class="text-[10px] font-bold text-gray-400 hover:text-[#1D4069] uppercase tracking-widest mb-2 inline-block">← Back to Desk</a>

    <div class="mb-8">
        <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.3em] mb-1">Ventiq</p>
        <p class="text-[15px] font-bold text-[#1D4069]">Reports</p>
        <p class="text-[11px] text-gray-400 font-medium mt-1">Every report ever generated, always here — whether or not you've opened it yet.</p>
    </div>

    <div class="flex gap-2 mb-6">
        <a href="{{ route('sessions.reports', ['tab' => 'needs_review']) }}"
           class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-wide transition-all {{ $tab !== 'reviewed' ? 'bg-[#1D4069] text-white' : 'bg-gray-50 text-gray-500 hover:bg-gray-100' }}">
            Needs Review @if($needsReviewCount > 0)<span class="ml-1">({{ $needsReviewCount }})</span>@endif
        </a>
        <a href="{{ route('sessions.reports', ['tab' => 'reviewed']) }}"
           class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-wide transition-all {{ $tab === 'reviewed' ? 'bg-[#1D4069] text-white' : 'bg-gray-50 text-gray-500 hover:bg-gray-100' }}">
            Reviewed
        </a>
    </div>

    <div class="space-y-3">
        @forelse($reports as $session)
            <a href="{{ route('sessions.report', $session) }}"
               class="flex items-center justify-between px-5 py-4 rounded-2xl bg-white border border-gray-100 hover:border-[#1D4069]/30 hover:shadow-sm transition-all">
                <div>
                    <p class="text-[12px] font-bold text-[#1D4069]">{{ $session->resolved_title }}</p>
                    <p class="text-[10px] text-gray-400 font-bold mt-0.5">{{ $session->date?->format('d M Y') }}</p>
                </div>
                @if($tab !== 'reviewed')
                    <span class="text-[9px] font-black text-[#F07F22] uppercase tracking-widest">Needs Review</span>
                @else
                    <span class="text-[9px] font-black text-emerald-500 uppercase tracking-widest">Reviewed</span>
                @endif
            </a>
        @empty
            <p class="text-[11px] text-gray-300 italic text-center py-16">
                {{ $tab === 'reviewed' ? "Nothing reviewed yet." : "Nothing waiting for review — you're caught up." }}
            </p>
        @endforelse
    </div>

    @if($reports->hasPages())
        <div class="mt-6">{{ $reports->withQueryString()->links() }}</div>
    @endif

</div>
@endsection