@extends('layouts.app')
@section('title', 'Sessions | VENTIQ')
@section('content')
<style>
    /* Same tape/folded-corner pattern already used for AI insight
       stickies in the live workspace — reused here, not reinvented. */
    .session-note {
        position: relative;
        padding: 18px 14px 14px;
        border-radius: 4px 4px 10px 10px;
        box-shadow: 0 10px 20px rgba(15,23,42,0.12), 0 1px 0 rgba(15,23,42,0.05);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .session-note:hover {
        transform: translateY(-3px) rotate(0deg) !important;
        box-shadow: 0 16px 28px rgba(15,23,42,0.18);
    }
    .session-note::after {
        content: '';
        position: absolute; bottom: 0; right: 0; width: 18px; height: 18px;
        background: linear-gradient(135deg, transparent 50%, rgba(15,23,42,0.12) 50%);
        border-radius: 0 0 10px 0;
    }
    .session-tape {
        position: absolute; top: -10px; left: 50%; width: 50px; height: 18px;
        background: rgba(255,255,255,0.6); border: 1px solid rgba(255,255,255,0.8);
        transform: translateX(-50%) rotate(-3deg);
        backdrop-filter: blur(1px);
        box-shadow: 0 2px 4px rgba(15,23,42,0.1);
    }
    .cork-board {
        background: #F7F3EC;
        border-radius: 24px;
        padding: 24px;
    }
    /* vertical separators between columns, per doc 12 — categories are
       columns on a desk, not horizontal sections stacked on a page */
    .desk-column {
        border-left: 1px dashed rgba(15,23,42,0.1);
        padding-left: 1.5rem;
    }
    .desk-column:first-child {
        border-left: none;
        padding-left: 0;
    }
</style>

<div class="max-w-6xl mx-auto px-4 py-8">

    <div class="mb-8">
        <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.3em] mb-1">Ventiq</p>

        @php
            $hour = now()->hour;
            $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
            $firstName = explode(' ', Auth::user()->name ?? 'there')[0];
        @endphp

        <p class="text-[15px] font-bold text-[#1D4069]">{{ $greeting }}, {{ $firstName }} — what's happening today?</p>

        {{-- ── "Start something" is its own object, deliberately not mixed
             with the board below, per doc 12's "two different conversations" ── --}}
        <div class="flex flex-wrap gap-2 items-center mt-4">
            <a href="{{ route('sessions.create') }}"
               class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-[#F07F22] text-white text-[12px] font-black uppercase tracking-wide shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all">
                🎤 Start a Presentation
            </a>
            @foreach(['🤝 Meeting', '📖 Lecture', '⛪ Church', '🛠 Workshop', '🎓 Training', '📋 Board'] as $type)
                @php [$icon, $label] = explode(' ', $type, 2); @endphp
                <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-full bg-gray-50 text-gray-300 text-[10px] font-black uppercase tracking-wide cursor-not-allowed" title="Coming soon">
                    {{ $icon }} {{ $label }} <span class="text-[8px]">soon</span>
                </span>
            @endforeach
        </div>
    </div>

    @unless($hasFeature)
        <div class="mb-6 p-4 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-between gap-4">
            <p class="text-[11px] font-bold text-amber-700">Package doesn't include Sessions.</p>
            <a href="{{ route('pricing') }}" class="shrink-0 px-4 py-1.5 rounded-full bg-amber-500 text-white text-[9px] font-black uppercase tracking-widest">Upgrade</a>
        </div>
    @endunless

    {{-- ── THE DESK — columns, not stacked sections. Notes stack DOWN
         inside a column; categories sit side by side, separated by a
         thin dashed rule, per doc 12. Headings are quiet on purpose:
         "think museum" — the notes are the stars, not the labels. ── --}}
    @if($readyToReview->isNotEmpty() || $happeningNow->isNotEmpty() || $comingUp->isNotEmpty() || $recentSessions->isNotEmpty())
        <div class="cork-board">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-0">

                <div class="desk-column">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-4">Ready to Review</p>
                    <div class="space-y-4">
                        @forelse($readyToReview as $session)
                            @include('sessions.partials.tile', [
                                'session' => $session, 'category' => 'review', 'tint' => '#FDF3D9',
                                'href' => route('sessions.report', $session),
                            ])
                        @empty
                            <p class="text-[10px] text-gray-300 italic">Nothing waiting</p>
                        @endforelse
                    </div>
                </div>

                <div class="desk-column">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-4">Happening Now</p>
                    <div class="space-y-4">
                        @forelse($happeningNow as $session)
                            @include('sessions.partials.tile', [
                                'session' => $session, 'category' => 'live', 'tint' => '#FFE9CE',
                                'href' => route('sessions.show', $session),
                            ])
                        @empty
                            <p class="text-[10px] text-gray-300 italic">Nothing live</p>
                        @endforelse
                    </div>
                </div>

                <div class="desk-column">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-4">Coming Up</p>
                    <div class="space-y-4">
                        @forelse($comingUp as $session)
                            @include('sessions.partials.tile', [
                                'session' => $session, 'category' => 'upcoming', 'tint' => '#E9EEF5',
                                'href' => route('sessions.show', $session),
                            ])
                        @empty
                            <p class="text-[10px] text-gray-300 italic">Nothing scheduled</p>
                        @endforelse
                    </div>
                </div>

                <div class="desk-column">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-4">Recently Finished</p>
                    <div class="space-y-4">
                        {{-- doc 12: "I'd show maybe two. Then View all history →" --}}
                        @forelse($recentSessions->take(2) as $session)
                            @include('sessions.partials.tile', [
                                'session' => $session, 'category' => 'recent', 'tint' => '#F1F5F9',
                                'href' => $session->status === 'reported' ? route('sessions.report', $session) : route('sessions.show', $session),
                            ])
                        @empty
                            <p class="text-[10px] text-gray-300 italic">Nothing yet</p>
                        @endforelse
                    </div>
                    @if($recentSessions->count() > 2)
                        {{-- ASSUMPTION: this route doesn't exist in what you've shared me —
                             swap in whatever your actual "all sessions" / history route is named --}}
                        <a href="{{ route('sessions.index') }}" class="inline-block text-[10px] font-bold text-[#1D4069] underline mt-3">
                            View all history →
                        </a>
                    @endif
                </div>

            </div>
        </div>
    @else
        <p class="text-sm text-gray-400 italic text-center py-16">Pick something above to get started — your first session will show up here.</p>
    @endif
</div>
@endsection
