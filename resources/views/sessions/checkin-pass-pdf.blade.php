<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ventiq Check-in Pass</title>
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', Arial, sans-serif; padding: 40px; }

        .pass {
            border: 2px solid #0f172a;
            border-radius: 24px;
            overflow: hidden;
        }

        .badge {
            display: inline-block;
            background-color: #D4AF37;
            color: #ffffff;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 20px;
        }

        .title {
            font-size: 26px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -1px;
            text-transform: uppercase;
            margin-bottom: 40px;
        }

        .label {
            font-size: 9px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 6px;
        }

        .value {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
        }

        .qr-side {
            background-color: #f8fafc;
            text-align: center;
            padding: 40px;
        }

        .link-box {
            border: 1.5px dashed #cbd5e1;
            border-radius: 12px;
            padding: 12px 16px;
            margin-top: 20px;
        }

        .link-text {
            font-size: 9px;
            font-weight: 700;
            color: #0f172a;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="pass">
        <table width="100%" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td width="60%" valign="top" style="padding: 40px;">
                    <div class="badge">{{ \App\Support\SessionType::label($session->type) }}</div>
                    <div class="title">{{ $session->resolved_title }}</div>

                    <div style="margin-bottom: 24px;">
                        <div class="label">Date</div>
                        <div class="value">{{ $session->date?->format('d M Y') ?? $session->created_at->format('d M Y') }}</div>
                    </div>

                    @if($session->meta['expected_participants'] ?? null)
                    <div style="margin-bottom: 24px;">
                        <div class="label">Expected</div>
                        <div class="value">{{ $session->meta['expected_participants'] }} People</div>
                    </div>
                    @endif

                    <div style="margin-top: 40px; opacity: 0.4; font-size: 9px; font-weight: 800; text-transform: uppercase;">
                        {{ $session->organization->name }}
                    </div>
                </td>
                <td width="40%" valign="top" class="qr-side">
                    <img src="data:image/png;base64,{{ $qrBase64 }}" width="160" height="160">
                    <div class="label" style="margin-top: 16px;">Scan to Register</div>
                    <div class="link-box">
                        <div class="label" style="margin-bottom: 4px;">Can't scan?</div>
                        <div class="link-text">{{ $checkinUrl }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>