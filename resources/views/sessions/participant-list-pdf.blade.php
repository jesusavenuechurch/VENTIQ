@php($multi = count($list['days']) > 1)
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 36px 36px 48px; }
    body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 9px; }
    .org { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #64748b; margin: 0; }
    h1 { font-size: 16px; color: #1D4069; margin: 3px 0 2px; }
    .sub { color: #475569; font-size: 10px; margin: 0 0 4px; }
    .facts { color: #475569; font-size: 9px; margin: 0 0 12px; }
    table.list { width: 100%; border-collapse: collapse; }
    table.list th { background: #1D4069; color: #fff; text-align: left; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.5px; padding: 5px 4px; }
    table.list th.day { text-align: center; }
    table.list td { border: 1px solid #cbd5e1; padding: 4px; vertical-align: middle; height: 26px; }
    table.list td.n { color: #94a3b8; width: 16px; text-align: right; }
    table.list td.day { text-align: center; width: 44px; white-space: nowrap; }
    table.list td.sig { width: 96px; padding: 1px 4px; }
    table.list td.sig img { height: 24px; max-width: 92px; }
    .how { color: #94a3b8; font-size: 7px; }
    .absent { color: #cbd5e1; }
    .stamp { margin-top: 14px; border: 1px solid #cbd5e1; padding: 8px 10px; color: #475569; font-size: 8px; line-height: 1.5; }
    .stamp b { color: #1D4069; }
    .confirm { margin-top: 22px; }
    .confirm td { padding-right: 36px; color: #475569; font-size: 9px; }
    .line { border-bottom: 1px solid #94a3b8; width: 190px; height: 22px; }
    .foot { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 7px; color: #94a3b8; }
</style>
</head>
<body>
    <div class="foot">{{ $list['organization'] }} · {{ $list['title'] }} · Participant list produced by VENTIQ Sessions on {{ $list['generated'] }}</div>

    @include('sessions.partials.letterhead', ['logo' => $logo ?? null, 'name' => $list['organization']])
    <h1>Participant list: {{ $list['title'] }}</h1>
    <p class="sub">{{ $list['kind'] }}{{ $list['dates'] ? ' · ' . $list['dates'] : '' }}{{ $list['venue'] ? ' · ' . $list['venue'] : '' }}</p>
    <p class="facts">
        {{ count($list['rows']) }} {{ count($list['rows']) === 1 ? 'participant' : 'participants' }}
        @if ($multi)
            · {{ collect($list['days'])->map(fn ($d) => "{$d['label']} ({$d['date']}): {$d['count']}")->implode(', ') }}
        @endif
    </p>

    <table class="list">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Organisation</th>
                <th>Position</th>
                @if ($list['districts'])<th>District</th>@endif
                <th>Phone</th>
                <th>Email</th>
                @foreach ($list['days'] as $d)
                    <th class="day">{{ $multi ? $d['label'] : 'Time in' }}@if ($multi)<br>{{ $d['date'] }}@endif</th>
                @endforeach
                <th>Signature</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($list['rows'] as $i => $r)
                <tr>
                    <td class="n">{{ $i + 1 }}</td>
                    <td><b>{{ $r['name'] }}</b></td>
                    <td>{{ $r['organization'] }}</td>
                    <td>{{ $r['position'] }}</td>
                    @if ($list['districts'])<td>{{ $r['district'] }}</td>@endif
                    <td>{{ $r['phone'] }}</td>
                    <td>{{ $r['email'] }}</td>
                    @foreach ($list['days'] as $d)
                        @php($in = $r['days'][$d['id']] ?? null)
                        <td class="day">
                            @if ($in){{ $in['time'] }}<br><span class="how">{{ $in['how'] }}</span>@else<span class="absent">—</span>@endif
                        </td>
                    @endforeach
                    <td class="sig">@if ($r['signature'])<img src="{{ $r['signature'] }}" alt="">@endif</td>
                </tr>
            @endforeach
            {{-- Rows to fill in by hand, for anyone who didn't check in. --}}
            @for ($b = 0; $b < $blank; $b++)
                <tr>
                    <td class="n">{{ count($list['rows']) + $b + 1 }}</td>
                    <td></td><td></td><td></td>
                    @if ($list['districts'])<td></td>@endif
                    <td></td><td></td>
                    @foreach ($list['days'] as $d)<td class="day"></td>@endforeach
                    <td class="sig"></td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="stamp">
        <b>How this list was produced.</b>
        Each row is a check-in recorded in VENTIQ Sessions at the time shown:
        <b>Self</b>, the person checked themselves in with the session's QR code or code;
        <b>Desk</b>, added by the organizer's staff;
        <b>Scanner</b>, checked in on the organizer's scanner app, where signatures are drawn.
        @if (count($list['how']))
            This list: {{ collect($list['how'])->map(fn ($count, $how) => "{$count} {$how}")->implode(', ') }}.
        @endif
        Rows numbered after the last check-in, if any, were added by hand.
        Produced {{ $list['generated'] }}{{ $list['by'] ? ' by ' . $list['by'] : '' }}.
    </div>

    <table class="confirm">
        <tr>
            <td><div class="line"></div>Certified by (name and signature)</td>
            <td><div class="line"></div>Position</td>
            <td><div class="line"></div>Date</td>
        </tr>
    </table>
</body>
</html>
