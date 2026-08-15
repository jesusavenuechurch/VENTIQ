@extends('layouts.app')
@section('title', 'Sessions | VENTIQ')
@section('content')
<style>
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
        <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.3em] mb-1">
            Ventiq · {{ $organization->name }}
        </p>

        @php
            $hour = now()->hour;
            $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
            $firstName = explode(' ', Auth::user()->name ?? 'there')[0];
        @endphp

        <p class="text-[15px] font-bold text-[#1D4069]">{{ $greeting }}, {{ $firstName }} — what's happening today?</p>

        @if(session('status'))
            <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-100 text-[11px] font-bold text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap gap-2 items-center mt-4">
            <a href="{{ route('sessions.create') }}"
            class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-[#F07F22] text-white text-[12px] font-black uppercase tracking-wide shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all">
                ✨ Schedule a Session
            </a>

            <a href="{{ route('organization.members') }}"
            class="inline-flex items-center gap-1.5 px-4 py-3 rounded-2xl bg-white border border-gray-100 hover:border-[#1D4069]/30 transition-all text-[10px] font-black text-[#1D4069] uppercase tracking-wide">
                👥 {{ $memberCount }} {{ Str::plural('Member', $memberCount) }}
            </a>

            <a href="{{ \App\Filament\Resources\SessionPackageResource::getUrl('index') }}"
            class="inline-flex items-center gap-1.5 px-4 py-3 rounded-2xl {{ $sessionsRemaining > 0 ? 'bg-white border border-gray-100 hover:border-[#1D4069]/30 text-[#1D4069]' : 'bg-rose-50 border border-rose-100 hover:border-rose-300 text-rose-700' }} transition-all text-[10px] font-black uppercase tracking-wide">
                🎟 {{ $sessionsUsed }}/{{ $sessionsIncluded }} Sessions
            </a>

            @if($pendingInviteCount > 0)
                <a href="{{ route('organization.members') }}"
                class="inline-flex items-center gap-1.5 px-4 py-3 rounded-2xl bg-amber-50 border border-amber-100 hover:border-amber-300 transition-all text-[10px] font-black text-amber-700 uppercase tracking-wide">
                    ⏳ {{ $pendingInviteCount }} Pending
                </a>
            @endif

            {{-- Always visible — this is the permanent archive, not a
                 desk column that can empty out and disappear on you. --}}
            <a href="{{ route('sessions.reports') }}"
               class="inline-flex items-center gap-1.5 px-4 py-3 rounded-2xl bg-white border border-gray-100 hover:border-[#1D4069]/30 transition-all text-[10px] font-black text-[#1D4069] uppercase tracking-wide">
                📄 Reports
                @if($needsReviewCount > 0)
                    <span class="ml-1 px-1.5 py-0.5 rounded-full bg-[#F07F22] text-white text-[9px]">{{ $needsReviewCount }}</span>
                @endif
            </a>

            <a href="{{ route('programmes.index') }}"
               class="inline-flex items-center gap-1.5 px-4 py-3 rounded-2xl bg-white border border-gray-100 hover:border-[#1D4069]/30 transition-all text-[10px] font-black text-[#1D4069] uppercase tracking-wide">
                📁 Programmes
            </a>

            {{-- Ticketed events live in the Filament admin, a separate
                 product from Sessions — opens in a new tab so people don't
                 lose their place on the Desk. --}}
            <a href="{{ \App\Filament\Resources\EventResource::getUrl('create') }}" target="_blank"
               class="inline-flex items-center gap-1.5 px-4 py-3 rounded-2xl bg-white border border-gray-100 hover:border-[#1D4069]/30 transition-all text-[10px] font-black text-[#1D4069] uppercase tracking-wide">
                🎫 Create an Event ↗
            </a>
        </div>
    </div>

    @if($sessionsRemaining <= 0)
        <div class="mb-6 p-4 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-between gap-4">
            <p class="text-[11px] font-bold text-amber-700">
                You've used your {{ $sessionsIncluded }} included {{ Str::plural('session', $sessionsIncluded) }}. Add more sessions with PAYG or upgrade your plan.
            </p>
            <a href="{{ \App\Filament\Resources\SessionPackageResource::getUrl('index') }}" class="shrink-0 px-4 py-1.5 rounded-full bg-amber-500 text-white text-[9px] font-black uppercase tracking-widest">Add Sessions</a>
        </div>
    @endif

    @php
        $filterMap = [
            'ready'   => ['label' => 'Ready to Review',   'items' => $readyToReview,  'tint' => '#FDF3D9', 'empty' => 'Nothing waiting'],
            'live'    => ['label' => 'Happening Now',     'items' => $happeningNow,   'tint' => '#FFE9CE', 'empty' => 'Nothing live'],
            'upcoming'=> ['label' => 'Coming Up',         'items' => $comingUp,       'tint' => '#E9EEF5', 'empty' => 'Nothing scheduled'],
            'recent'  => ['label' => 'Recently Finished', 'items' => $recentSessions, 'tint' => '#F1F5F9', 'empty' => 'Nothing yet'],
        ];
        $active = $filter && isset($filterMap[$filter]) ? $filterMap[$filter] : null;
    @endphp

    @if($active)
        <a href="{{ route('sessions.index') }}" class="inline-block text-[10px] font-bold text-gray-400 hover:text-[#1D4069] uppercase tracking-widest mb-4">← Back to Desk</a>

        <div class="cork-board">
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-4">{{ $active['label'] }} · {{ $active['items']->count() }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($active['items'] as $session)
                    @include('sessions.partials.tile', [
                        'session' => $session,
                        'category' => $filter,
                        'tint' => $active['tint'],
                        'href' => in_array($filter, ['ready']) || ($filter === 'recent' && $session->status === 'reported')
                            ? route('sessions.report', $session)
                            : route('sessions.show', $session),
                    ])
                @empty
                    <p class="text-[10px] text-gray-300 italic">{{ $active['empty'] }}</p>
                @endforelse
            </div>
        </div>
    @elseif($readyToReview->isNotEmpty() || $happeningNow->isNotEmpty() || $comingUp->isNotEmpty() || $recentSessions->isNotEmpty())
        <div class="cork-board">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-0">

                <div class="desk-column">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-4">Ready to Review</p>
                    <div class="space-y-4">
                        @forelse($readyToReview->take(4) as $session)
                            @include('sessions.partials.tile', [
                                'session' => $session, 'category' => 'review', 'tint' => '#FDF3D9',
                                'href' => route('sessions.report', $session),
                            ])
                        @empty
                            <p class="text-[10px] text-gray-300 italic">Nothing waiting</p>
                        @endforelse
                    </div>
                    @if($readyToReview->count() > 4)
                        <a href="{{ route('sessions.index', ['filter' => 'ready']) }}" class="inline-block text-[10px] font-bold text-[#1D4069] underline mt-3">
                            View all {{ $readyToReview->count() }} →
                        </a>
                    @endif
                </div>

                <div class="desk-column">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-4">Happening Now</p>
                    <div class="space-y-4">
                        @forelse($happeningNow->take(4) as $session)
                            @include('sessions.partials.tile', [
                                'session' => $session, 'category' => 'live', 'tint' => '#FFE9CE',
                                'href' => route('sessions.show', $session),
                            ])
                        @empty
                            <p class="text-[10px] text-gray-300 italic">Nothing live</p>
                        @endforelse
                    </div>
                    @if($happeningNow->count() > 4)
                        <a href="{{ route('sessions.index', ['filter' => 'live']) }}" class="inline-block text-[10px] font-bold text-[#1D4069] underline mt-3">
                            View all {{ $happeningNow->count() }} →
                        </a>
                    @endif
                </div>

                <div class="desk-column">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-4">Coming Up</p>
                    <div class="space-y-4">
                        @forelse($comingUp->take(4) as $session)
                            @include('sessions.partials.tile', [
                                'session' => $session, 'category' => 'upcoming', 'tint' => '#E9EEF5',
                                'href' => route('sessions.show', $session),
                            ])
                        @empty
                            <p class="text-[10px] text-gray-300 italic">Nothing scheduled</p>
                        @endforelse
                    </div>
                    @if($comingUp->count() > 4)
                        <a href="{{ route('sessions.index', ['filter' => 'upcoming']) }}" class="inline-block text-[10px] font-bold text-[#1D4069] underline mt-3">
                            View all {{ $comingUp->count() }} →
                        </a>
                    @endif
                </div>

                <div class="desk-column">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-4">Recently Finished</p>
                    <div class="space-y-4">
                        @forelse($recentSessions->take(4) as $session)
                            @include('sessions.partials.tile', [
                                'session' => $session, 'category' => 'recent', 'tint' => '#F1F5F9',
                                'href' => $session->status === 'reported' ? route('sessions.report', $session) : route('sessions.show', $session),
                            ])
                        @empty
                            <p class="text-[10px] text-gray-300 italic">Nothing yet</p>
                        @endforelse
                    </div>
                    @if($recentSessions->count() > 4)
                        <a href="{{ route('sessions.index', ['filter' => 'recent']) }}" class="inline-block text-[10px] font-bold text-[#1D4069] underline mt-3">
                            View all {{ $recentSessions->count() }} →
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