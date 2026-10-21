@php($h = $minutes['heading'])
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 48px 56px; }
    body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 11px; line-height: 1.55; }
    .org { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: #64748b; margin: 0; }
    h1 { font-size: 17px; color: #1D4069; margin: 4px 0 2px; }
    .sub { color: #475569; margin: 0 0 14px; }
    table.facts { border-collapse: collapse; margin-bottom: 18px; }
    table.facts td { padding: 2px 16px 2px 0; vertical-align: top; }
    table.facts td.k { color: #64748b; width: 80px; }
    h2 { font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: #1D4069; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; margin: 18px 0 8px; }
    h3 { font-size: 12px; margin: 12px 0 2px; }
    .lead { color: #64748b; font-size: 10px; margin: 0 0 4px; }
    p { margin: 0 0 6px; }
    ul { margin: 2px 0 6px 0; padding-left: 16px; }
    .muted { color: #94a3b8; font-style: italic; }
    table.actions { width: 100%; border-collapse: collapse; }
    table.actions th { text-align: left; font-size: 9px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 4px 6px 4px 0; }
    table.actions td { border-bottom: 1px solid #f1f5f9; padding: 5px 6px 5px 0; vertical-align: top; }
    .done { color: #059669; }
    .confirm { margin-top: 36px; }
    .line { border-bottom: 1px solid #94a3b8; width: 220px; height: 26px; }
    .foot { margin-top: 28px; font-size: 8px; color: #94a3b8; }
</style>
</head>
<body>
    @include('sessions.partials.letterhead', ['logo' => $logo ?? null, 'name' => $h['organization']])
    <h1>Minutes: {{ $h['title'] }}</h1>
    <p class="sub">{{ $h['type'] }} held on {{ $h['date'] }}{{ $h['time'] ? ' at ' . $h['time'] : '' }}{{ $h['venue'] ? ', ' . $h['venue'] : '' }}</p>

    <table class="facts">
        @if ($h['chair'])<tr><td class="k">Chair</td><td>{{ $h['chair'] }}</td></tr>@endif
        @if ($h['secretary'])<tr><td class="k">Secretary</td><td>{{ $h['secretary'] }}</td></tr>@endif
    </table>

    <h2>1. Present</h2>
    @if (count($minutes['present']))
        <p>{{ implode(', ', $minutes['present']) }}</p>
    @else
        <p class="muted">Not recorded.</p>
    @endif

    <h2>2. Apologies</h2>
    @if (count($minutes['apologies']))
        <p>{{ implode(', ', $minutes['apologies']) }}</p>
    @else
        <p>None.</p>
    @endif

    @php($n = 3)
    @if ($minutes['previous'])
        <h2>{{ $n++ }}. Minutes of the previous meeting</h2>
        <p>The minutes of the meeting of {{ $minutes['previous']['date'] }} {{ $minutes['previous']['confirmed'] ? 'were confirmed.' : 'have not yet been confirmed.' }}</p>
    @endif

    @if (count($minutes['arising']))
        <h2>{{ $n++ }}. Matters arising</h2>
        <table class="actions">
            <tr><th>Action</th><th>Owner</th><th>Due</th><th>Status</th></tr>
            @foreach ($minutes['arising'] as $a)
                <tr>
                    <td>{{ $a['task'] }}@if ($a['session'])<br><span class="lead">From {{ $a['session']['date'] }}</span>@endif</td>
                    <td>{{ $a['owner'] ?? '—' }}</td>
                    <td>{{ $a['dueLabel'] ?? '—' }}</td>
                    <td class="{{ $a['status'] === 'done' ? 'done' : '' }}">{{ $a['status'] === 'done' ? 'Done' : ($a['overdue'] ? 'Overdue' : 'Open') }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @foreach ($minutes['items'] as $item)
        <h2>{{ $n++ }}. {{ $item['title'] }}</h2>
        @if ($item['lead'])<p class="lead">Led by {{ $item['lead'] }}</p>@endif
        @if ($item['discussion'])
            <p>{{ $item['discussion'] }}</p>
        @else
            <p class="muted">No discussion recorded.</p>
        @endif
        @if (count($item['decisions']))
            <h3>Resolved</h3>
            <ul>@foreach ($item['decisions'] as $d)<li>{{ $d }}</li>@endforeach</ul>
        @endif
    @endforeach

    <h2>{{ $n++ }}. Action points</h2>
    @php($actions = array_filter($minutes['actions'], fn ($a) => $a['status'] !== 'proposed' || !$minutes['confirmed']))
    @if (count($actions))
        <table class="actions">
            <tr><th>Action</th><th>Owner</th><th>Due</th></tr>
            @foreach ($actions as $a)
                <tr><td>{{ $a['task'] }}</td><td>{{ $a['owner'] ?? '—' }}</td><td>{{ $a['dueLabel'] ?? '—' }}</td></tr>
            @endforeach
        </table>
    @else
        <p>None.</p>
    @endif

    <h2>{{ $n++ }}. Any other business</h2>
    @if ($minutes['aob'])
        <p style="white-space: pre-wrap">{{ $minutes['aob'] }}</p>
    @else
        <p>None.</p>
    @endif

    <h2>{{ $n++ }}. Next meeting</h2>
    <p>{{ $minutes['next'] ?? 'To be confirmed.' }}</p>

    <div class="confirm">
        @if ($minutes['confirmed'])
            <p>Confirmed{{ $minutes['confirmed']['by'] ? ' by ' . $minutes['confirmed']['by'] : '' }} on {{ $minutes['confirmed']['date'] }}.</p>
        @else
            <p class="muted">Draft: not yet confirmed.</p>
        @endif
        <table class="facts" style="margin-top: 18px">
            <tr>
                <td><div class="line"></div>Chair{{ $h['chair'] ? ': ' . $h['chair'] : '' }}</td>
                <td><div class="line"></div>Date</td>
            </tr>
        </table>
    </div>

    <p class="foot">Minutes kept with VENTIQ Sessions. Notes typed during the meeting and written up by Ventiq Assist, then reviewed.</p>
</body>
</html>
