<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>{{ $record->resolved_title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #1a1a2e;
            background: #ffffff;
            line-height: 1.6;
        }

        /* ── Header ─────────────────────────────────────────────────── */
        .header {
            background: #1D4069;
            color: #ffffff;
            padding: 28px 36px 20px;
            margin-bottom: 0;
        }

        .header-org {
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: rgba(255,255,255,0.65);
            margin-bottom: 6px;
        }

        .header-title {
            font-size: 18pt;
            font-weight: bold;
            color: #ffffff;
            margin-bottom: 14px;
            line-height: 1.3;
        }

        .header-meta {
            display: table;
            width: 100%;
        }

        .header-meta-item {
            display: table-cell;
            font-size: 8.5pt;
            color: rgba(255,255,255,0.8);
            padding-right: 24px;
        }

        .header-meta-label {
            color: rgba(255,255,255,0.5);
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: block;
            margin-bottom: 2px;
        }

        /* ── Record type badge ──────────────────────────────────────── */
        .type-bar {
            background: #F07F22;
            padding: 6px 36px;
            font-size: 7.5pt;
            font-weight: bold;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 24px;
        }

        /* ── Attendance strip ───────────────────────────────────────── */
        .attendance-strip {
            display: table;
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin: 0 36px 22px;
            width: calc(100% - 72px);
            overflow: hidden;
        }

        .attendance-cell {
            display: table-cell;
            text-align: center;
            padding: 12px 0;
            border-right: 1px solid #e2e8f0;
        }

        .attendance-cell:last-child { border-right: none; }

        .attendance-number {
            font-size: 18pt;
            font-weight: bold;
            color: #1D4069;
            display: block;
        }

        .attendance-label {
            font-size: 7.5pt;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ── Content area ───────────────────────────────────────────── */
        .content {
            padding: 0 36px;
        }

        /* ── Section ────────────────────────────────────────────────── */
        .section {
            margin-bottom: 22px;
            page-break-inside: avoid;
        }

        .section-header {
            display: table;
            width: 100%;
            margin-bottom: 10px;
            border-bottom: 2px solid #1D4069;
            padding-bottom: 5px;
        }

        .section-icon {
            display: table-cell;
            width: 20px;
            font-size: 11pt;
            vertical-align: middle;
        }

        .section-title {
            display: table-cell;
            font-size: 9pt;
            font-weight: bold;
            color: #1D4069;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            vertical-align: middle;
        }

        .section-count {
            display: table-cell;
            text-align: right;
            font-size: 7.5pt;
            color: #94a3b8;
            vertical-align: middle;
        }

        /* ── Items ──────────────────────────────────────────────────── */
        .item {
            padding: 7px 10px 7px 14px;
            margin-bottom: 5px;
            border-left: 3px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 0 4px 4px 0;
            font-size: 9.5pt;
            color: #334155;
        }

        .item.decision {
            border-left-color: #10B981;
            background: #f0fdf4;
        }

        .item.action {
            border-left-color: #F07F22;
            background: #fff7ed;
        }

        .item.issue {
            border-left-color: #ef4444;
            background: #fef2f2;
        }

        .item-meta {
            margin-top: 3px;
            font-size: 8pt;
            color: #94a3b8;
        }

        .item-meta span {
            margin-right: 12px;
        }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-pending   { background: #fef3c7; color: #92400e; }
        .badge-completed { background: #d1fae5; color: #065f46; }

        /* ── Empty state ────────────────────────────────────────────── */
        .empty {
            color: #94a3b8;
            font-style: italic;
            font-size: 9pt;
            padding: 6px 10px;
        }

        /* ── Attendee list ──────────────────────────────────────────── */
        .attendee-list {
            font-size: 8.5pt;
            color: #475569;
            padding: 8px 10px;
            background: #f8fafc;
            border-radius: 4px;
            line-height: 1.8;
        }

        /* ── Footer ─────────────────────────────────────────────────── */
        .footer {
            margin-top: 32px;
            padding: 14px 36px;
            border-top: 1px solid #e2e8f0;
            display: table;
            width: 100%;
        }

        .footer-left {
            display: table-cell;
            font-size: 7.5pt;
            color: #94a3b8;
        }

        .footer-right {
            display: table-cell;
            text-align: right;
            font-size: 7.5pt;
            color: #94a3b8;
        }

        .footer-brand {
            color: #1D4069;
            font-weight: bold;
        }

        /* ── Page break ─────────────────────────────────────────────── */
        .page-break { page-break-after: always; }
    </style>
</head>
<body>

    {{-- ── HEADER ──────────────────────────────────────────────────────── --}}
    <div class="header">
        <div class="header-org">{{ $record->organization?->name }}</div>
        <div class="header-title">{{ $record->resolved_title }}</div>
        <div class="header-meta">
            @if($record->meeting_date)
            <div class="header-meta-item">
                <span class="header-meta-label">Date</span>
                {{ $record->meeting_date->format('l, d F Y') }}
            </div>
            @endif
            @if($record->location)
            <div class="header-meta-item">
                <span class="header-meta-label">Venue</span>
                {{ $record->location }}
            </div>
            @endif
            <div class="header-meta-item">
                <span class="header-meta-label">Prepared By</span>
                {{ $record->creator?->name }}
            </div>
            <div class="header-meta-item">
                <span class="header-meta-label">Generated</span>
                {{ now()->format('d M Y, H:i') }}
            </div>
        </div>
    </div>

    {{-- ── TYPE BAR ─────────────────────────────────────────────────────── --}}
    <div class="type-bar">
        {{ ucfirst($record->record_type) }} Record
        @if($record->event)
            &nbsp;·&nbsp; {{ $record->event->name }}
        @endif
    </div>

    {{-- ── ATTENDANCE (only if event linked) ──────────────────────────── --}}
    @if($attendance)
    <div class="attendance-strip">
        <div class="attendance-cell">
            <span class="attendance-number">{{ $attendance->count }}</span>
            <span class="attendance-label">Checked In</span>
        </div>
        <div class="attendance-cell">
            <span class="attendance-number">{{ $attendance->absent }}</span>
            <span class="attendance-label">Absent</span>
        </div>
        <div class="attendance-cell">
            <span class="attendance-number">{{ $attendance->total }}</span>
            <span class="attendance-label">Total</span>
        </div>
    </div>

    {{-- Attendee names --}}
    @if($attendance->checked_in->count() > 0)
    <div class="content" style="margin-bottom: 16px;">
        <div class="attendee-list">
            <strong>Present:</strong>
            {{ $attendance->checked_in->map(fn($t) => $t->client->full_name)->join(', ') }}
        </div>
    </div>
    @endif
    @endif

    {{-- ── CONTENT ──────────────────────────────────────────────────────── --}}
    <div class="content">

        {{-- Agenda --}}
        <div class="section">
            <div class="section-header">
                <div class="section-icon">📋</div>
                <div class="section-title">Agenda / Topics</div>
                <div class="section-count">{{ $agenda->count() }} item(s)</div>
            </div>
            @forelse($agenda as $item)
                <div class="item">{{ $item->content }}</div>
            @empty
                <div class="empty">No agenda items recorded.</div>
            @endforelse
        </div>

        {{-- Discussion Points --}}
        <div class="section">
            <div class="section-header">
                <div class="section-icon">💬</div>
                <div class="section-title">Key Discussion Points</div>
                <div class="section-count">{{ $discussion->count() }} point(s)</div>
            </div>
            @forelse($discussion as $item)
                <div class="item">{{ $item->content }}</div>
            @empty
                <div class="empty">No discussion points recorded.</div>
            @endforelse
        </div>

        {{-- Decisions --}}
        <div class="section">
            <div class="section-header">
                <div class="section-icon">✅</div>
                <div class="section-title">Decisions / Resolutions</div>
                <div class="section-count">{{ $decisions->count() }} decision(s)</div>
            </div>
            @forelse($decisions as $item)
                <div class="item decision">{{ $item->content }}</div>
            @empty
                <div class="empty">No decisions recorded.</div>
            @endforelse
        </div>

        {{-- Action Items --}}
        <div class="section">
            <div class="section-header">
                <div class="section-icon">⚡</div>
                <div class="section-title">Action Items</div>
                <div class="section-count">{{ $actions->count() }} action(s)</div>
            </div>
            @forelse($actions as $action)
                <div class="item action">
                    {{ $action->description }}
                    <div class="item-meta">
                        @if($action->assigned_to_name)
                            <span>👤 {{ $action->assigned_to_name }}</span>
                        @endif
                        @if($action->due_date)
                            <span>📅 {{ $action->due_date->format('d M Y') }}</span>
                        @endif
                        <span>
                            <span class="badge badge-{{ $action->status === 'completed' ? 'completed' : 'pending' }}">
                                {{ ucfirst($action->status) }}
                            </span>
                        </span>
                    </div>
                </div>
            @empty
                <div class="empty">No action items recorded.</div>
            @endforelse
        </div>

        {{-- Open Issues --}}
        <div class="section">
            <div class="section-header">
                <div class="section-icon">⚠️</div>
                <div class="section-title">Open Issues / Pending Matters</div>
                <div class="section-count">{{ $issues->count() }} issue(s)</div>
            </div>
            @forelse($issues as $issue)
                <div class="item issue">
                    {{ $issue->description }}
                    @if($issue->raised_by)
                        <div class="item-meta">
                            <span>👤 Raised by {{ $issue->raised_by }}</span>
                        </div>
                    @endif
                </div>
            @empty
                <div class="empty">No open issues recorded.</div>
            @endforelse
        </div>

    </div>

    {{-- ── FOOTER ───────────────────────────────────────────────────────── --}}
    <div class="footer">
        <div class="footer-left">
            {{ $record->organization?->name }} &nbsp;·&nbsp;
            {{ $record->meeting_date?->format('d M Y') ?? now()->format('d M Y') }}
        </div>
        <div class="footer-right">
            Generated by <span class="footer-brand">Ventiq Assist</span>
        </div>
    </div>

</body>
</html>