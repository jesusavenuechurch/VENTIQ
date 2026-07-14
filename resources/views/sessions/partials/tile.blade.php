@php
    $rotations = ['-2deg', '1.5deg', '-1deg', '2deg', '-1.5deg'];
    $r = $rotations[$session->id % count($rotations)];

    // ── Scale indicators ─────────────────────────────────────────────
    // ASSUMPTION: these two property names are guesses based on what's
    // used elsewhere (the roster sidebar's live $participantCount, and
    // the segments relation used to build the presenter roster). If your
    // actual accessors differ, or these aren't eager-loaded on the
    // controller's $sessions query, these will silently show "—" rather
    // than error — check the real property names before trusting the numbers.
    $attendeeCount  = $session->participants_count
        ?? optional($session->participants)->count()
        ?? null;

    $presenterCount = $session->segments_count
        ?? optional($session->segments)->count()
        ?? null;

    $activeSegmentPosition = null;
    if ($category === 'live' && $session->relationLoaded('segments')) {
        $segments = $session->segments;
        $activeIdx = $segments->search(fn ($s) => $s->status === 'active');
        $activeSegmentPosition = $activeIdx !== false ? $activeIdx + 1 : null;
    }
@endphp

<a href="{{ $href }}" class="session-note block" style="background: {{ $tint }}; transform: rotate({{ $r }});">
    <div class="session-tape"></div>

    {{-- title first — the session already knows it's a Presentation,
         the user already chose that when creating it, so it isn't the hero --}}
    <p class="text-[13px] font-black text-[#1D4069] leading-snug line-clamp-2 mb-1">
        {{ $session->resolved_title }}
    </p>
    <p class="text-[9px] font-black uppercase tracking-widest mb-2.5" style="color: rgba(15,23,42,0.4);">
        {{ ucfirst($session->type ?? 'Session') }}
    </p>

    {{-- the "scale" line — only what someone would ask before opening it --}}
    <div class="flex items-center gap-3 mb-2.5 text-[10px] font-bold" style="color: rgba(15,23,42,0.55);">
        @if($category === 'live')
            <span>👥 {{ $attendeeCount ?? '—' }} checked in</span>
            @if($activeSegmentPosition && $presenterCount)
                <span>🎤 Speaker {{ $activeSegmentPosition }} of {{ $presenterCount }}</span>
            @endif
        @elseif($category === 'upcoming')
            <span>👥 {{ $attendeeCount ?? ($session->meta['expected_participants'] ?? '—') }} expected</span>
            @if($presenterCount)
                <span>🎤 {{ $presenterCount }} presenter{{ $presenterCount === 1 ? '' : 's' }}</span>
            @endif
        @else
            {{-- review, recent --}}
            <span>👥 {{ $attendeeCount ?? '—' }} attended</span>
            @if($presenterCount)
                <span>🎤 {{ $presenterCount }} presenter{{ $presenterCount === 1 ? '' : 's' }}</span>
            @endif
        @endif
    </div>

    {{-- the status line — always last, always quiet, but reworded per
         category so it reads as "why click me" rather than a db value --}}
    <p class="text-[9px] font-bold" style="color: rgba(15,23,42,0.45);">
        @switch($category)
            @case('review')
                ✓ AI Report Ready
                @break
            @case('live')
                🟢 Live Now
                @break
            @case('upcoming')
                {{-- ASSUMPTION: using $session->date as the scheduled time.
                     Swap for whatever field actually holds start time if
                     it's separate from the date column. --}}
                Starts {{ optional($session->date)->format('H:i') ?? 'soon' }}
                @break
            @default
                {{ $session->updated_at->diffForHumans() }}
        @endswitch
    </p>
</a>
