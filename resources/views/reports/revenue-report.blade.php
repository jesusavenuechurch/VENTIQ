<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <title>Revenue Report — {{ $event->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1a202c; background: #fff; }
 
        .header { background: #1D4069; color: #fff; padding: 20px 28px; display: table; width: 100%; }
        .header-left  { display: table-cell; vertical-align: middle; width: 70%; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; }
        .logo-img     { height: 44px; }
        .report-label { font-size: 7pt; text-transform: uppercase; letter-spacing: 2px; color: rgba(255,255,255,0.6); margin-bottom: 4px; }
        .event-title  { font-size: 16pt; font-weight: bold; color: #fff; margin-bottom: 6px; }
        .event-meta   { font-size: 8pt; color: rgba(255,255,255,0.75); }
 
        .content { padding: 24px 28px; }
 
        .revenue-hero {
            display: table; width: 100%;
            background: linear-gradient(135deg, #1D4069, #2A5298);
            border-radius: 10px; padding: 20px; margin-bottom: 20px; color: #fff;
        }
        .revenue-hero-left  { display: table-cell; vertical-align: middle; }
        .revenue-hero-right { display: table-cell; vertical-align: middle; text-align: right; }
        .revenue-amount { font-size: 26pt; font-weight: bold; }
        .revenue-label  { font-size: 8pt; opacity: 0.7; text-transform: uppercase; letter-spacing: 1px; }
 
        .metric-row { display: table; width: 100%; margin-bottom: 20px; }
        .metric-cell {
            display: table-cell; padding: 12px; text-align: center;
            border: 1px solid #e2e8f0; border-radius: 6px;
        }
        .metric-num   { font-size: 16pt; font-weight: bold; }
        .metric-label { font-size: 7.5pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-top: 3px; }
 
        .section-title {
            font-size: 9pt; font-weight: bold; color: #1D4069;
            text-transform: uppercase; letter-spacing: 1px;
            border-bottom: 2px solid #1D4069;
            padding-bottom: 5px; margin-bottom: 12px; margin-top: 20px;
        }
 
        table { width: 100%; border-collapse: collapse; }
        thead th { background: #1D4069; color: #fff; padding: 7px 10px; text-align: left; font-size: 7.5pt; font-weight: bold; text-transform: uppercase; }
        thead th.right { text-align: right; }
        tbody td { padding: 7px 10px; font-size: 8.5pt; border-bottom: 1px solid #f1f5f9; }
        tbody td.right { text-align: right; }
        tbody tr:nth-child(even) { background: #f8fafc; }
 
        .footer {
            position: fixed; bottom: 0; left: 0; right: 0;
            padding: 8px 28px; border-top: 1px solid #e2e8f0;
            display: table; width: 100%; font-size: 7pt; color: #94a3b8; background: #fff;
        }
        .footer-left  { display: table-cell; }
        .footer-right { display: table-cell; text-align: right; }
        .brand { color: #1D4069; font-weight: bold; }
    </style>
</head>
<body>
 
    <div class="footer">
        <div class="footer-left">Generated {{ $generatedAt }} by {{ $generatedBy }}</div>
        <div class="footer-right"><span class="brand">Ventiq</span></div>
    </div>
 
    <div class="header">
        <div class="header-left">
            <div class="report-label">Revenue Report</div>
            <div class="event-title">{{ $event->name }}</div>
            <div class="event-meta">
                @if($event->event_date) {{ $event->event_date->format('l, d F Y') }} @endif
                @if($event->venue) &nbsp;·&nbsp; {{ $event->venue }} @endif
            </div>
        </div>
        <div class="header-right">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" class="logo-img"/>
            @endif
        </div>
    </div>
 
    <div class="content">
 
        @if($logoWarning)
        <div style="padding:8px 12px; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; font-size:8pt; color:#92400e; margin-bottom:16px;">
            !No organisation logo found. Upload one under System → Organisations.
        </div>
        @endif
 
        {{-- Revenue hero --}}
        <div class="revenue-hero">
            <div class="revenue-hero-left">
                <div class="revenue-label">Total Collected</div>
                <div class="revenue-amount">{{ $currency }} {{ number_format($totalCollected, 2) }}</div>
            </div>
            <div class="revenue-hero-right">
                <div class="revenue-label">Collection Rate</div>
                <div style="font-size:22pt; font-weight:bold;">{{ $collectionRate }}%</div>
            </div>
        </div>
 
        {{-- Key metrics --}}
        <div class="metric-row">
            <div class="metric-cell" style="margin-right:6px;">
                <div class="metric-num" style="color:#1D4069;">{{ $currency }} {{ number_format($totalExpected, 2) }}</div>
                <div class="metric-label">Expected Revenue</div>
            </div>
            <div class="metric-cell" style="margin: 0 6px;">
                <div class="metric-num" style="color:#EF4444;">{{ $currency }} {{ number_format($totalOutstanding, 2) }}</div>
                <div class="metric-label">Outstanding</div>
            </div>
            <div class="metric-cell" style="margin-left:6px;">
                <div class="metric-num" style="color:#8B5CF6;">{{ $compTickets }}</div>
                <div class="metric-label">Comp Tickets</div>
            </div>
        </div>
 
        {{-- Payment status --}}
        <div class="section-title">Payment Status Breakdown</div>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th class="right">Count</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span style="color:#10B981;">●</span> Completed</td>
                    <td class="right" style="font-weight:bold; color:#10B981;">{{ $paymentBreakdown['completed'] }}</td>
                </tr>
                <tr>
                    <td><span style="color:#F59E0B;">●</span> Partial</td>
                    <td class="right" style="font-weight:bold; color:#F59E0B;">{{ $paymentBreakdown['partial'] }}</td>
                </tr>
                <tr>
                    <td><span style="color:#EF4444;">●</span> Pending</td>
                    <td class="right" style="font-weight:bold; color:#EF4444;">{{ $paymentBreakdown['pending'] }}</td>
                </tr>
                <tr>
                    <td><span style="color:#94a3b8;">●</span> Refunded</td>
                    <td class="right" style="color:#94a3b8;">{{ $paymentBreakdown['refunded'] }}</td>
                </tr>
            </tbody>
        </table>
 
        {{-- Per-tier revenue --}}
        <div class="section-title">Revenue by Tier</div>
        <table>
            <thead>
                <tr>
                    <th>Tier</th>
                    <th class="right">Price</th>
                    <th class="right">Sold</th>
                    <th class="right">Expected</th>
                    <th class="right">Collected</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tierRevenue as $tier)
                <tr>
                    <td style="font-weight:600;">{{ $tier['name'] }}</td>
                    <td class="right">{{ $currency }} {{ number_format($tier['price'], 2) }}</td>
                    <td class="right">{{ $tier['sold'] }}</td>
                    <td class="right">{{ $currency }} {{ number_format($tier['expected'], 2) }}</td>
                    <td class="right" style="font-weight:bold; color:#10B981;">{{ $currency }} {{ number_format($tier['collected'], 2) }}</td>
                </tr>
                @endforeach
                <tr style="background:#f0fdf4; font-weight:bold;">
                    <td colspan="3">Total</td>
                    <td class="right">{{ $currency }} {{ number_format($totalExpected, 2) }}</td>
                    <td class="right" style="color:#10B981;">{{ $currency }} {{ number_format($totalCollected, 2) }}</td>
                </tr>
            </tbody>
        </table>
 
    </div>
</body>
</html>