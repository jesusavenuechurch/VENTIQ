<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Helvetica', Arial, sans-serif;
            background-color: #000000;
            margin: 0;
            padding: 40px 20px;
            color: #0f172a;
        }
        .container {
            max-width: 560px;
            margin: 0 auto;
        }
        .brand-row {
            text-align: center;
            margin-bottom: 24px;
        }
        .brand-name {
            color: #ffffff;
            font-weight: 900;
            font-size: 13px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .brand-sub {
            color: rgba(255,255,255,0.4);
            font-weight: 700;
            font-size: 9px;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .card {
            background: #ffffff;
            border-radius: 28px;
            padding: 40px;
        }
        .tier-badge {
            display: inline-block;
            background-color: {{ $accentColor ?? '#10b981' }};
            color: #ffffff;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }
        .event-name {
            font-size: 24px;
            font-weight: 900;
            letter-spacing: -0.5px;
            text-transform: uppercase;
            color: #0f172a;
            margin: 0 0 28px 0;
        }
        .label {
            font-size: 9px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 4px;
        }
        .value {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            margin: 0 0 22px 0;
        }
        .divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 0 0 24px 0;
        }
        .button {
            display: block;
            text-align: center;
            background: #0f172a;
            color: #ffffff !important;
            text-decoration: none;
            padding: 16px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 11px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin: 24px 0 0 0;
        }
        .note {
            font-size: 12px;
            color: #64748b;
            line-height: 1.6;
            margin-top: 20px;
        }
        .system-id {
            font-size: 10px;
            font-weight: 700;
            color: #cbd5e1;
            letter-spacing: 1px;
            margin-top: 30px;
        }
        .footer {
            text-align: center;
            margin-top: 28px;
        }
        .footer p {
            color: rgba(255,255,255,0.3);
            font-size: 10px;
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="brand-row">
            <div class="brand-name">Ventiq</div>
            <div class="brand-sub">Digital Fulfillment</div>
        </div>

        <div class="card">
            <div class="tier-badge">{{ $tier->tier_name }}</div>
            <h1 class="event-name">{{ $event->name }}</h1>

            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="50%">
                        <div class="label">Guest Name</div>
                        <div class="value">{{ $client->full_name }}</div>
                    </td>
                    <td width="50%">
                        @if($event->event_date)
                            <div class="label">Date</div>
                            <div class="value">{{ $event->event_date->format('d M Y') }}</div>
                        @endif
                    </td>
                </tr>
            </table>

            @if($event->venue || $event->location)
                <div class="label">Venue</div>
                <div class="value">{{ $event->venue ?? $event->location }}</div>
            @endif

            <hr class="divider">

            <div class="label">Ticket Number</div>
            <div class="value" style="margin-bottom: 0;">{{ $ticket->ticket_number }}</div>

            <a href="{{ $downloadLink }}" class="button">View Your Ticket</a>

            <p class="note">
                Tap above to view your ticket, save it, or get the PDF — your QR code and entry voucher code are both there. A PDF copy is also attached to this email for offline access at the venue.
            </p>

            <div class="system-id">
                <span style="color: {{ $accentColor ?? '#10b981' }}">●</span>
                SECURE PASS ID: {{ $ticket->ticket_number }}
            </div>
        </div>

        <div class="footer">
            <p>{{ $organization->name }} &middot; {{ date('Y') }}</p>
            <p>Sent to {{ $client->email }}</p>
        </div>
    </div>
</body>
</html>