<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <title>Attendance Register — {{ $event->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5pt;
            color: #1a202c;
            background: #fff;
        }

        /* ── Header ──────────────────────────────────────────────── */
        .header {
            background: #1D4069;
            color: #fff;
            padding: 16px 24px;
            display: table;
            width: 100%;
        }

        .header-left  { display: table-cell; vertical-align: middle; width: 70%; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; width: 30%; }

        .logo-img { height: 44px; width: auto; }

        .logo-warning {
            font-size: 7pt;
            color: rgba(255,255,255,0.5);
            font-style: italic;
        }

        .report-label {
            font-size: 7pt;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: rgba(255,255,255,0.6);
            margin-bottom: 4px;
        }

        .event-title {
            font-size: 15pt;
            font-weight: bold;
            color: #fff;
            margin-bottom: 6px;
        }

        .event-meta {
            font-size: 7.5pt;
            color: rgba(255,255,255,0.75);
        }

        /* ── Stats strip ─────────────────────────────────────────── */
        .stats-strip {
            display: table;
            width: 100%;
            background: #f7fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 24px;
        }

        .stat-cell {
            display: table-cell;
            text-align: center;
            padding: 0 16px;
            border-right: 1px solid #e2e8f0;
        }

        .stat-cell:last-child { border-right: none; }

        .stat-num   { font-size: 16pt; font-weight: bold; color: #1D4069; }
        .stat-label { font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }

        /* ── Table ───────────────────────────────────────────────── */
        .content { padding: 14px 24px; }

        table { width: 100%; border-collapse: collapse; }

        thead tr {
            background: #1D4069;
            color: #fff;
        }

        thead th {
            padding: 7px 6px;
            text-align: left;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:nth-child(odd)  { background: #fff; }

        tbody td {
            padding: 6px 6px;
            font-size: 7.5pt;
            color: #374151;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-green  { background: #d1fae5; color: #065f46; }
        .badge-gray   { background: #f1f5f9; color: #64748b; }
        .badge-orange { background: #fed7aa; color: #92400e; }
        .badge-red    { background: #fee2e2; color: #991b1b; }

        /* ── Signature cell ──────────────────────────────────────── */
        .sig-img { max-height: 32px; max-width: 90px; }

        /* ── Footer ──────────────────────────────────────────────── */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 8px 24px;
            border-top: 1px solid #e2e8f0;
            display: table;
            width: 100%;
            font-size: 7pt;
            color: #94a3b8;
            background: #fff;
        }

        .footer-left  { display: table-cell; }
        .footer-right { display: table-cell; text-align: right; }
        .footer-brand { color: #1D4069; font-weight: bold; }

        /* ── Page number ─────────────────────────────────────────── */
        .page-num:after { content: counter(page); }
    </style>
</head>
<body>

    {{-- Footer (fixed, appears on every page) --}}
    <div class="footer">
        <div class="footer-left">
            {{ $org->name }} · {{ $event->name }} · Generated {{ $generatedAt }} by {{ $generatedBy }}
        </div>
        <div class="footer-right">
            <span class="footer-brand">Ventiq</span> · Page <span class="page-num"></span>
        </div>
    </div>

    {{-- Header --}}
    <div class="header">
        <div class="header-left">
            <div class="report-label">Attendance Register</div>
            <div class="event-title">{{ $event->name }}</div>
            <div class="event-meta">
                @if($event->event_date) {{ $event->event_date->format('l, d F Y') }} @endif
                @if($event->venue) &nbsp;·&nbsp; {{ $event->venue }} @endif
                @if($event->location && $event->location !== $event->venue) &nbsp;·&nbsp; {{ $event->location }} @endif
            </div>
        </div>
        <div class="header-right">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" class="logo-img" alt="{{ $org->name }}"/>
            @elseif($logoWarning)
                <span class="logo-warning">No logo on file</span>
            @endif
        </div>
    </div>

    {{-- Stats strip --}}
    <div class="stats-strip">
        <div class="stat-cell">
            <div class="stat-num">{{ $totalCount }}</div>
            <div class="stat-label">Registered</div>
        </div>
        <div class="stat-cell">
            <div class="stat-num" style="color:#10B981;">{{ $checkedCount }}</div>
            <div class="stat-label">Checked In</div>
        </div>
        <div class="stat-cell">
            <div class="stat-num" style="color:#F59E0B;">{{ $totalCount - $checkedCount }}</div>
            <div class="stat-label">Absent</div>
        </div>
        @if($isWorkshop)
        <div class="stat-cell">
            <div class="stat-num" style="color:#8B5CF6;">{{ $signedCount }}</div>
            <div class="stat-label">Signed</div>
        </div>
        <div class="stat-cell">
            <div class="stat-num" style="color:#EF4444;">{{ $checkedCount - $signedCount }}</div>
            <div class="stat-label">Unsigned</div>
        </div>
        @endif
        <div class="stat-cell">
            <div class="stat-num">
                {{ $totalCount > 0 ? round(($checkedCount / $totalCount) * 100) : 0 }}%
            </div>
            <div class="stat-label">Attendance</div>
        </div>
    </div>

    {{-- Table --}}
    <div class="content">
        <table>
            <thead>
                <tr>
                    <th style="width:28px;">#</th>
                    <th>Full Name</th>
                    <th>Phone</th>
                    <th>Ticket</th>
                    <th>Voucher</th>
                    <th>Tier</th>
                    <th>Status</th>
                    <th>Check-in Time</th>
                    @if($isWorkshop)
                        <th>Position</th>
                        <th>Institution</th>
                        <th>District</th>
                        <th style="width:90px;">Signature</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($tickets as $i => $ticket)
                <tr>
                    <td style="color:#94a3b8; text-align:center;">{{ $i + 1 }}</td>
                    <td style="font-weight:600;">{{ $ticket->client->full_name }}</td>
                    <td>{{ $ticket->client->phone ?? '—' }}</td>
                    <td style="font-size:7pt; color:#64748b;">{{ $ticket->ticket_number }}</td>
                    <td style="font-weight:700; letter-spacing:1px;">{{ $ticket->voucher_code ?? '—' }}</td>
                    <td>{{ $ticket->tier->tier_name }}</td>
                    <td>
                        @if($ticket->checked_in_at)
                            <span class="badge badge-green">Checked In</span>
                        @else
                            <span class="badge badge-gray">Absent</span>
                        @endif
                    </td>
                    <td>{{ $ticket->checked_in_at?->format('H:i') ?? '—' }}</td>

                    @if($isWorkshop)
                        @php $detail = $ticket->workshopDetail; @endphp
                        <td>{{ $detail?->position ?? '—' }}</td>
                        <td>{{ $detail?->institution ?? '—' }}</td>
                        <td>{{ $detail?->district_label ?? '—' }}</td>
                        <td>
                            @if($detail?->isSigned() && $detail->signature_path)
                                @php
                                    $sigPath = storage_path('app/public/' . $detail->signature_path);
                                    $sigBase64 = file_exists($sigPath)
                                        ? 'data:image/png;base64,' . base64_encode(file_get_contents($sigPath))
                                        : null;
                                @endphp
                                @if($sigBase64)
                                    <img src="{{ $sigBase64 }}" class="sig-img" alt="Signature"/>
                                @else
                                    <span class="badge badge-gray">Missing</span>
                                @endif
                            @elseif($detail?->signature_status === 'declined')
                                <span class="badge badge-red">Declined</span>
                            @elseif($detail?->signature_status === 'skipped')
                                <span class="badge badge-orange">Skipped</span>
                            @else
                                <span class="badge badge-gray">—</span>
                            @endif
                        </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</body>
</html>