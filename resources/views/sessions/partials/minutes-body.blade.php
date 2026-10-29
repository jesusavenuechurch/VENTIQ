{{-- A meeting's minutes, section by section: in the minutes PDF and the full report. Needs $minutes. --}}
@php($h = $minutes['heading'])
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
        @if ($item['lead'])<p class="lead">{{ $item['leadAs'] ?? 'Led by' }} {{ $item['lead'] }}</p>@endif
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

