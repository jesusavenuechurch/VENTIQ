<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ventiq Pass - {{ $ticket->ticket_number }}</title>
    <style>
        @page { 
            margin: 0; 
            size: A4 landscape;
        }
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        body {
            font-family: 'Helvetica', Arial, sans-serif;
            background-color: #000000;
            padding: 60px;
        }
        
        @php
            /* Syncing logic with your web view */
            $isVip = str_contains(strtolower($ticket->tier->tier_name), 'vip');
            $accentColor = $isVip ? '#D4AF37' : '#10b981';
        @endphp

        .ticket-wrapper {
            width: 950px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 40px;
            overflow: hidden;
            position: relative;
        }

        /* Layout Table */
        .main-table {
            width: 100%;
            border-collapse: collapse;
        }

        /* Left Content */
        .info-side {
            width: 65%;
            padding: 60px;
            position: relative;
        }

        .watermark {
            position: absolute;
            bottom: -30px;
            left: -30px;
            font-size: 140px;
            font-weight: 900;
            color: rgba(15, 23, 42, 0.03); /* Matching the web opacity */
            font-style: italic;
            text-transform: uppercase;
        }

        .tier-badge {
            background-color: {{ $accentColor }};
            color: #ffffff;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 900;
            display: inline-block;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .event-name {
            font-size: 38px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -1.5px;
            margin-bottom: 50px;
            text-transform: uppercase;
        }

        .label {
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }

        .value {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            margin-bottom: 35px;
        }

        /* Right Stub */
        .qr-side {
            width: 35%;
            background-color: #f8fafc;
            padding: 60px 40px;
            text-align: center;
            border-left: 2px dashed #e2e8f0;
            vertical-align: middle;
        }

        .qr-box {
            background: #ffffff;
            padding: 25px;
            border-radius: 30px;
            border: 1px solid #e2e8f0;
            display: inline-block;
            margin-bottom: 25px;
        }

        .qr-img {
            width: 180px;
            height: 180px;
        }

        .scan-text {
            font-size: 10px;
            font-weight: 900;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 3px;
        }

        /* Perforation circles (The physical ticket holes) */
        .hole {
            position: absolute;
            width: 36px;
            height: 36px;
            background-color: #000000;
            border-radius: 50%;
            right: 33.3%;
            z-index: 20;
        }
        .hole-top { top: -18px; }
        .hole-bottom { bottom: -18px; }

        .system-id {
            margin-top: 50px;
            font-size: 11px;
            font-weight: 700;
            color: #cbd5e1;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>

    <div class="ticket-wrapper">
        <div class="hole hole-top"></div>
        <div class="hole hole-bottom"></div>

        <table class="main-table">
            <tr>
                <td class="info-side">
                    <div class="watermark">VENTIQ</div>
                    
                    <div style="position: relative; z-index: 10;">
                        <div class="tier-badge">{{ $ticket->tier->tier_name }}</div>
                        <h1 class="event-name">{{ $ticket->event->name }}</h1>

                        <table width="100%" border="0">
                            <tr>
                                <td width="50%">
                                    <div class="label">Guest Name</div>
                                    <div class="value">{{ $ticket->client->full_name }}</div>
                                </td>
                                <td width="50%">
                                    <div class="label">Event Date</div>
                                    <div class="value">{{ $ticket->event->event_date->format('d M Y') }}</div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <div class="label">Venue Location</div>
                                    <div class="value" style="margin-bottom: 0;">{{ $ticket->event->location }}</div>
                                </td>
                            </tr>
                        </table>

                        <div class="system-id">
                            <span style="color: {{ $accentColor }}">●</span> 
                            SECURE PASS ID: {{ $ticket->ticket_number }}
                        </div>
                    </div>
                </td>

                <td class="qr-side">
                    <div class="qr-box">
                        @if($ticket->qr_code_path)
                            @php
                                $path = storage_path('app/public/' . $ticket->qr_code_path);
                                $type = pathinfo($path, PATHINFO_EXTENSION);
                                $data = file_get_contents($path);
                                $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                            @endphp
                            <img src="{{ $base64 }}" class="qr-img">
                        @endif
                    </div>
                    
                <div class="scan-text">Scan for Entry</div>
 
                    {{-- Voucher code — fallback for guests who cannot scan --}}
                    @if($ticket->voucher_code)
                    <div style="margin-top: 20px; padding: 10px 16px; background: #f1f5f9; border: 1.5px dashed #cbd5e1; border-radius: 10px; display: inline-block;">
                        <div style="font-size: 8px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 4px;">
                            Entry Code
                        </div>
                        <div style="font-size: 20px; font-weight: 900; color: #0f172a; letter-spacing: 4px;">
                            {{ $ticket->voucher_code }}
                        </div>
                        <div style="font-size: 7px; color: #94a3b8; margin-top: 3px;">
                            Use this if you cannot scan
                        </div>
                    </div>
                    @endif
 
                    <div style="margin-top: 20px; opacity: 0.3;">
                        <span style="font-size: 8px; font-weight: bold; color: #000; text-transform: uppercase;">Powered by</span><br>
                        <span style="font-size: 12px; font-weight: 900; color: #000; letter-spacing: -1px;">VENTIQ</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>