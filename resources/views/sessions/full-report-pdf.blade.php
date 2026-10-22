@php
    $a = $numbers['attendance'];
    $t = $numbers['time'];
    $o = $numbers['outcomes'];
    $p = $numbers['programme'];
    $mins = fn (int $m) => $m >= 60 ? intdiv($m, 60) . 'h' . ($m % 60 ? ' ' . ($m % 60) . 'm' : '') : "{$m}m";
    $org = $session->organization?->name;
    $date = ($session->date ?? $session->created_at)->format('l, j F Y');
    $leads = $items->map(fn ($s) => $s->title && $s->presenter_name !== $s->title ? $s->presenter_name : ($s->title ? null : $s->presenter_name))->filter()->unique()->values();
    $written = collect($summary)->filter(fn ($s) => trim((string) $s['text']) !== '');
    $first = fn (string $text) => collect(preg_split('/(?<=[.!?])\s+/', trim(preg_replace('/\s+/', ' ', $text))))->take(2)->implode(' ');
    $sections = array_values(array_filter([
        'Summary and numbers',
        $minutes ? 'Minutes' : 'Report',
        'The notes it was written from',
        'Attendance register',
    ]));
    $n = 0;
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 48px 52px 60px; }
    body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 10.5px; line-height: 1.5; }
    .foot { position: fixed; bottom: -40px; left: 0; right: 0; font-size: 7.5px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }
    .break { page-break-before: always; }
    .eyebrow { font-size: 9px; letter-spacing: 3px; text-transform: uppercase; color: #F07F22; font-weight: bold; margin: 0; }
    .muted { color: #94a3b8; font-style: italic; }
    p { margin: 0 0 6px; }

    /* Cover */
    .cover { padding-top: 70px; }
    .cover .logo { max-height: 90px; max-width: 220px; margin-bottom: 28px; }
    .cover .orgname { font-size: 10px; letter-spacing: 3px; text-transform: uppercase; color: #64748b; margin: 0 0 40px; }
    .cover h1 { font-size: 28px; line-height: 1.15; color: #1D4069; margin: 8px 0 10px; }
    .cover .when { font-size: 12px; color: #475569; margin: 0 0 30px; }
    table.who { border-collapse: collapse; margin-bottom: 36px; }
    table.who td { padding: 3px 20px 3px 0; vertical-align: top; font-size: 11px; }
    table.who td.k { color: #94a3b8; width: 110px; text-transform: uppercase; font-size: 8px; letter-spacing: 1px; padding-top: 5px; }
    table.kpi { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 36px; }
    table.kpi td { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; vertical-align: top; width: 25%; }
    .kpi .k { font-size: 7.5px; letter-spacing: 1.5px; text-transform: uppercase; color: #94a3b8; font-weight: bold; }
    .kpi .v { font-size: 22px; font-weight: bold; color: #1D4069; margin: 4px 0 2px; }
    .kpi .d { font-size: 8px; color: #94a3b8; }
    .contents { border-top: 1px solid #e2e8f0; padding-top: 14px; }
    .contents p { font-size: 11px; color: #475569; margin: 0 0 4px; }

    /* Sections */
    h1.section { font-size: 18px; color: #1D4069; margin: 0 0 4px; }
    .section-sub { color: #64748b; margin: 0 0 18px; }
    h3.panel { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #64748b; margin: 18px 0 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
    table.bars { width: 100%; border-collapse: collapse; }
    table.bars td { padding: 3px 0; vertical-align: middle; font-size: 9.5px; }
    table.bars td.l { width: 42%; padding-right: 10px; color: #334155; }
    table.bars td.v { width: 13%; text-align: right; font-weight: bold; padding-left: 8px; white-space: nowrap; }
    .track { background: #f1f5f9; height: 8px; border-radius: 4px; }
    .fill { background: #1D4069; height: 8px; border-radius: 4px; }
    .fill.light { background: #8EA0B4; }
    table.halves { width: 100%; border-collapse: collapse; }
    table.halves > tbody > tr > td, table.halves > tr > td { width: 50%; vertical-align: top; padding-right: 18px; }
    ol.short { padding-left: 16px; margin: 0; }
    ol.short li { margin-bottom: 6px; }
    .status { display: inline-block; padding: 3px 8px; border-radius: 9px; font-size: 8.5px; font-weight: bold; margin-right: 4px; }
    .good { background: #ecfdf5; color: #047857; }
    .neutral { background: #f1f5f9; color: #475569; }
    .bad { background: #fff1f2; color: #be123c; }

    /* The minutes (shared with the minutes PDF) */
    .sub { color: #475569; margin: 0 0 14px; }
    table.facts { border-collapse: collapse; margin-bottom: 18px; }
    table.facts td { padding: 2px 16px 2px 0; vertical-align: top; }
    table.facts td.k { color: #64748b; width: 80px; }
    h2 { font-size: 10.5px; letter-spacing: 1px; text-transform: uppercase; color: #1D4069; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; margin: 16px 0 8px; }
    h3 { font-size: 11px; margin: 10px 0 2px; }
    .lead { color: #64748b; font-size: 9.5px; margin: 0 0 4px; }
    ul { margin: 2px 0 6px 0; padding-left: 16px; }
    table.actions { width: 100%; border-collapse: collapse; }
    table.actions th { text-align: left; font-size: 8px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 4px 6px 4px 0; }
    table.actions td { border-bottom: 1px solid #f1f5f9; padding: 5px 6px 5px 0; vertical-align: top; }
    .done { color: #059669; }
    .confirm { margin-top: 30px; }
    .line { border-bottom: 1px solid #94a3b8; width: 200px; height: 24px; }
    pre.report { white-space: pre-wrap; font-family: DejaVu Sans, sans-serif; font-size: 10.5px; line-height: 1.6; color: #334155; margin: 0; }

    /* Notes */
    .item { margin-bottom: 14px; }
    .item-title { font-size: 11px; font-weight: bold; color: #1D4069; margin: 0; }
    table.log { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.log td { padding: 2px 0; vertical-align: top; font-size: 9.5px; color: #334155; }
    table.log td.time { width: 52px; color: #94a3b8; font-family: DejaVu Sans Mono, monospace; font-size: 8.5px; }

    /* Register */
    table.register { width: 100%; border-collapse: collapse; }
    table.register th { background: #1D4069; color: #fff; text-align: left; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.5px; padding: 5px 4px; }
    table.register td { border: 1px solid #cbd5e1; padding: 3px 4px; vertical-align: middle; height: 24px; font-size: 8.5px; }
    table.register td.n { color: #94a3b8; width: 16px; text-align: right; }
    table.register td.in { width: 52px; text-align: center; white-space: nowrap; }
    table.register td.sig { width: 90px; padding: 1px 4px; }
    table.register td.sig img { height: 22px; max-width: 86px; }
    .how { color: #94a3b8; font-size: 7px; }
    .stamp { margin-top: 12px; border: 1px solid #cbd5e1; padding: 8px 10px; color: #475569; font-size: 8px; line-height: 1.5; }
    .stamp b { color: #1D4069; }
</style>
</head>
<body>
    <div class="foot">
        {{ $org }} · {{ $session->resolved_title }}, {{ ($session->date ?? $session->created_at)->format('j M Y') }} · Full report produced by VENTIQ Sessions on {{ $produced }}
    </div>

    {{-- ── Cover ─────────────────────────────────────────────── --}}
    <div class="cover">
        @if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
        <p class="orgname">{{ $org }}</p>
        <p class="eyebrow">{{ $type }} · full report</p>
        <h1>{{ $session->resolved_title }}</h1>
        <p class="when">{{ $date }}{{ $session->start_time ? ' at ' . substr($session->start_time, 0, 5) : '' }}{{ $session->location ? ' · ' . $session->location : '' }}</p>

        <table class="who">
            @if ($p)<tr><td class="k">Programme</td><td>{{ $p['name'] }}, day {{ $p['day'] }} of {{ count($p['days']) }}</td></tr>@endif
            @if ($session->meta['chair'] ?? null)<tr><td class="k">Chair</td><td>{{ $session->meta['chair'] }}</td></tr>@endif
            @if ($session->meta['secretary'] ?? null)<tr><td class="k">Secretary</td><td>{{ $session->meta['secretary'] }}</td></tr>@endif
            @if ($leads->isNotEmpty())<tr><td class="k">{{ $minutes ? 'Items led by' : 'Presented by' }}</td><td>{{ $leads->implode(', ') }}</td></tr>@endif
            @if ($session->reviewed_at)<tr><td class="k">Reviewed</td><td>{{ $session->reviewed_at->format('j F Y') }}{{ ($session->meta['reviewed_by'] ?? null) ? ' by ' . $session->meta['reviewed_by'] : '' }}</td></tr>@endif
        </table>

        <table class="kpi">
            <tr>
                @if ($a)
                    <td><div class="k">Checked in</div><div class="v">{{ $a['checkedIn'] }}</div><div class="d">{{ $a['expected'] ? "of {$a['expected']} expected · {$a['rate']}%" : 'No number expected was set' }}</div></td>
                @else
                    <td><div class="k">{{ $minutes ? 'Items taken' : 'Presenters' }}</div><div class="v">{{ count($t['items']) }}</div><div class="d">{{ $numbers['notes']['lines'] }} lines of notes</div></td>
                @endif
                <td><div class="k">Time</div><div class="v">{{ $mins($t['total']) }}</div><div class="d">{{ $t['planned'] ? $mins($t['planned']) . ' planned' : ($t['started'] ? "{$t['started']} to {$t['ended']}" : '') }}</div></td>
                <td><div class="k">Decisions</div><div class="v">{{ $o['decisions'] }}</div><div class="d">{{ $o['decisions'] ? 'Recorded' : 'None recorded' }}</div></td>
                <td><div class="k">Action points</div><div class="v">{{ $o['agreed']['total'] }}</div><div class="d">{{ $o['agreed']['total'] ? 'Agreed here' : 'None agreed' }}</div></td>
            </tr>
        </table>

        <div class="contents">
            <p class="eyebrow" style="margin-bottom: 8px;">Contents</p>
            @foreach ($sections as $i => $title)<p>{{ $i + 1 }}. {{ $title }}</p>@endforeach
        </div>
    </div>

    {{-- ── 1. Summary and numbers ────────────────────────────── --}}
    <div class="break">
        <p class="eyebrow">{{ ++$n }}</p>
        <h1 class="section">Summary and numbers</h1>
        <p class="section-sub">What was covered, and the session in figures.</p>

        @if ($written->isNotEmpty())
            <h3 class="panel">In short</h3>
            <ol class="short">
                @foreach ($written as $s)<li><b>{{ $s['label'] }}.</b> {{ $first($s['text']) }}</li>@endforeach
            </ol>
        @endif

        @if ($a)
            <table class="halves"><tr>
                <td>
                    <h3 class="panel">How people checked in</h3>
                    @include('sessions.partials.bars', ['rows' => $a['how'], 'total' => $a['checkedIn']])
                    <p class="lead" style="margin-top: 6px;">{{ $a['signed'] }} signed on the scanner{{ $a['noShows'] ? " · {$a['noShows']} registered but didn't come" : '' }}.</p>
                </td>
                <td>
                    <h3 class="panel">When they arrived</h3>
                    @include('sessions.partials.bars', ['rows' => collect($a['arrivals'])->map(fn ($r) => ['label' => 'From ' . $r['label'], 'count' => $r['count'], 'light' => $r['late']])->all(), 'total' => $a['checkedIn']])
                    @if ($a['late'] !== null)<p class="lead" style="margin-top: 6px;">{{ $a['late'] === 0 ? 'Everyone was in within 15 minutes of the start.' : "{$a['late']} arrived 15 minutes or more after the start (lighter bars)." }}</p>@endif
                </td>
            </tr><tr>
                <td>
                    <h3 class="panel">Where they came from</h3>
                    @include('sessions.partials.bars', ['rows' => $a['from'], 'total' => $a['checkedIn']])
                </td>
                <td>
                    <h3 class="panel">Districts</h3>
                    @include('sessions.partials.bars', ['rows' => $a['districts'], 'total' => $a['checkedIn']])
                </td>
            </tr></table>
        @endif

        <table class="halves"><tr>
            <td>
                <h3 class="panel">{{ $minutes ? 'Time on each item' : 'Time on each part' }}</h3>
                @include('sessions.partials.bars', ['rows' => collect($t['items'])->map(fn ($i) => ['label' => $i['label'], 'count' => $i['minutes'], 'shown' => $mins($i['minutes'])])->all()])
                @if ($t['started'])<p class="lead" style="margin-top: 6px;">Started {{ $t['started'] }}, ended {{ $t['ended'] }}{{ $t['planned'] ? '; ' . $mins($t['planned']) . ' planned' : '' }}.</p>@endif
            </td>
            <td>
                @if ($p)
                    <h3 class="panel">{{ $p['name'] }}</h3>
                    @include('sessions.partials.bars', ['rows' => collect($p['days'])->map(fn ($d) => ['label' => $d['label'] . ($d['date'] ? ', ' . $d['date'] : ''), 'count' => $d['count'], 'light' => !$d['this']])->all()])
                    <p class="lead" style="margin-top: 6px;">{{ $p['enrolled'] }} enrolled{{ $p['required'] ? " · {$p['required']} " . ($p['required'] === 1 ? 'day' : 'days') . ' for a certificate' : '' }}{{ $p['certificates'] !== null ? " · {$p['certificates']} certificates issued" : '' }}.</p>
                @elseif ($o['carried']['total'] || $o['agreed']['total'])
                    <h3 class="panel">Action points</h3>
                    @if ($o['carried']['total'])
                        <p>From earlier meetings: {{ $o['carried']['total'] }}</p>
                        <p style="margin-bottom: 10px;">
                            <span class="status good">Done here: {{ $o['carried']['doneHere'] }}</span>
                            <span class="status neutral">Still open: {{ $o['carried']['open'] - $o['carried']['overdue'] }}</span>
                            <span class="status bad">Overdue: {{ $o['carried']['overdue'] }}</span>
                        </p>
                    @endif
                    @if (count($o['owners']))
                        <p>Agreed here, by owner</p>
                        @include('sessions.partials.bars', ['rows' => $o['owners']])
                    @endif
                @endif
            </td>
        </tr></table>
    </div>

    {{-- ── 2. The report, or a meeting's minutes ─────────────── --}}
    <div class="break">
        <p class="eyebrow">{{ ++$n }}</p>
        @if ($minutes)
            @include('sessions.partials.minutes-body', ['minutes' => $minutes])
        @else
            <h1 class="section">Report</h1>
            <p class="section-sub">Written by Ventiq Assist from the notes{{ $session->reviewed_at ? ', reviewed ' . $session->reviewed_at->format('j F Y') : ', not yet reviewed' }}.</p>
            <pre class="report">{{ $session->session_report }}</pre>
        @endif
    </div>

    {{-- ── 3. The notes ─────────────────────────────────────── --}}
    <div class="break">
        <p class="eyebrow">{{ ++$n }}</p>
        <h1 class="section">The notes it was written from</h1>
        <p class="section-sub">As typed in the room, with the time of each line. {{ $minutes ? 'The minutes' : 'The report' }} above was written from these.</p>
        @forelse ($items as $item)
            <div class="item">
                <p class="item-title">{{ $item->title ?: $item->presenter_name }}</p>
                @if ($item->title && $item->presenter_name !== $item->title)<p class="lead">{{ $minutes ? 'Led by' : 'By' }} {{ $item->presenter_name }}</p>@endif
                @if (count($item->raw_log ?? []))
                    <table class="log">
                        @foreach ($item->raw_log as $line)<tr><td class="time">{{ substr($line['time'] ?? '', 0, 5) }}</td><td>{{ $line['text'] ?? '' }}</td></tr>@endforeach
                    </table>
                @else
                    <p class="muted">No notes typed for this one.</p>
                @endif
            </div>
        @empty
            <p class="muted">No notes.</p>
        @endforelse
    </div>

    {{-- ── 4. Attendance register ───────────────────────────── --}}
    @if (!$register)
        <div class="break">
            <p class="eyebrow">{{ ++$n }}</p>
            <h1 class="section">Attendance register</h1>
            <p class="section-sub">Check-in wasn't on for this {{ strtolower($type) }}, so these are the people {{ $minutes ? 'the minutes record as present' : 'on the session' }}. Each signs to confirm they were there; add anyone missing below.</p>
            <table class="register">
                <thead><tr><th>#</th><th>Name</th><th>Role</th><th>Signature</th></tr></thead>
                <tbody>
                    @foreach ($present as $i => $person)
                        <tr><td class="n">{{ $i + 1 }}</td><td><b>{{ $person['name'] }}</b></td><td>{{ $person['role'] }}</td><td class="sig" style="width: 160px;"></td></tr>
                    @endforeach
                    @for ($b = 0; $b < 4; $b++)
                        <tr><td class="n">{{ count($present) + $b + 1 }}</td><td></td><td></td><td class="sig" style="width: 160px;"></td></tr>
                    @endfor
                </tbody>
            </table>
        </div>
    @else
        <div class="break">
            <p class="eyebrow">{{ ++$n }}</p>
            <h1 class="section">Attendance register</h1>
            <p class="section-sub">{{ count($register['rows']) }} {{ count($register['rows']) === 1 ? 'person' : 'people' }} checked in. Phone numbers and emails are left out of this report; they are on the participant list.</p>
            <table class="register">
                <thead><tr><th>#</th><th>Name</th><th>Organisation</th><th>Position</th>@if ($register['districts'])<th>District</th>@endif<th style="text-align: center;">Time in</th><th>Signature</th></tr></thead>
                <tbody>
                    @foreach ($register['rows'] as $i => $r)
                        @php($in = collect($r['days'])->first())
                        <tr>
                            <td class="n">{{ $i + 1 }}</td>
                            <td><b>{{ $r['name'] }}</b></td>
                            <td>{{ $r['organization'] }}</td>
                            <td>{{ $r['position'] }}</td>
                            @if ($register['districts'])<td>{{ $r['district'] }}</td>@endif
                            <td class="in">@if ($in){{ $in['time'] }}<br><span class="how">{{ $in['how'] }}</span>@endif</td>
                            <td class="sig">@if ($r['signature'])<img src="{{ $r['signature'] }}" alt="">@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="stamp">
                <b>How this register was produced.</b>
                Each row is a check-in recorded in VENTIQ Sessions at the time shown:
                <b>Self</b>, with the session's QR code or code; <b>Desk</b>, added by the organizer's staff;
                <b>Scanner</b>, on the organizer's scanner app, where signatures are drawn.
                @if (count($register['how']))This register: {{ collect($register['how'])->map(fn ($count, $how) => "{$count} {$how}")->implode(', ') }}.@endif
            </div>
        </div>
    @endif

    <table class="confirm" style="border-collapse: collapse; margin-top: 30px;">
        <tr>
            <td style="padding-right: 36px; color: #475569; font-size: 9px;"><div class="line"></div>Certified by (name and signature)</td>
            <td style="padding-right: 36px; color: #475569; font-size: 9px;"><div class="line"></div>Position</td>
            <td style="color: #475569; font-size: 9px;"><div class="line"></div>Date</td>
        </tr>
    </table>
    <p class="lead" style="margin-top: 10px;">Produced {{ $produced }} by {{ $by }}.</p>
</body>
</html>
