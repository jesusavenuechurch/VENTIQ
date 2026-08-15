<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Certificate — {{ $certificate->client->full_name }}</title>
<style>
    @page { margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Helvetica', Arial, sans-serif; background-color: #F7F3EC; padding: 36px; }

    .frame {
        border: 3px solid #1D4069;
        padding: 6px;
    }
    .frame-inner {
        border: 1px solid #1D4069;
        background-color: #ffffff;
        padding: 60px 70px;
        text-align: center;
    }

    .wordmark {
        position: absolute;
        top: 30px; right: 40px;
        font-size: 12px; font-weight: bold; color: #1D4069; letter-spacing: -0.3px;
    }
    .wordmark span { color: #F07F22; }

    .eyebrow {
        font-size: 12px; font-weight: bold; color: #94a3b8;
        text-transform: uppercase; letter-spacing: 4px;
        margin-bottom: 10px;
    }

    .headline {
        font-size: 34px; font-weight: bold; color: #0f172a;
        text-transform: uppercase; letter-spacing: 1px;
        display: inline-block;
        border-bottom: 3px solid #F07F22;
        padding-bottom: 16px;
        margin-bottom: 34px;
    }

    .presented-to { font-size: 11px; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 10px; }
    .recipient { font-size: 40px; font-weight: bold; color: #1D4069; letter-spacing: -1px; margin-bottom: 28px; }

    .body-text { font-size: 14px; font-weight: 500; color: #475569; line-height: 1.7; max-width: 560px; margin: 0 auto 34px; }
    .body-text b { color: #0f172a; font-weight: bold; }

    .meta-row { display: table; width: 100%; margin-top: 20px; }
    .meta-cell { display: table-cell; width: 33.33%; text-align: center; vertical-align: top; }
    .meta-label { font-size: 9px; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 6px; }
    .meta-value { font-size: 12px; font-weight: bold; color: #0f172a; }

    .verify-link { font-size: 9px; font-weight: bold; color: #94a3b8; margin-top: 6px; word-break: break-all; }
</style>
</head>
<body>
    <div class="frame">
        <div class="frame-inner" style="position: relative;">
            <div class="wordmark">VENTI<span>Q</span></div>

            <div class="eyebrow">Certificate of Attendance</div>
            <div class="headline">{{ $certificate->programme->name }}</div>

            <div class="presented-to">This certifies that</div>
            <div class="recipient">{{ $certificate->client->full_name }}</div>

            <div class="body-text">
                participated in <b>{{ $certificate->programme->name }}</b>, hosted by
                <b>{{ $certificate->organization->name }}</b>@if($certificate->programme->venue) at {{ $certificate->programme->venue }}@endif.
            </div>

            <div class="meta-row">
                <div class="meta-cell">
                    <div class="meta-label">Issued</div>
                    <div class="meta-value">{{ $certificate->issued_at->format('d M Y') }}</div>
                </div>
                <div class="meta-cell">
                    <img src="data:image/png;base64,{{ $qrBase64 }}" width="90" height="90">
                    <div class="verify-link">Scan to verify</div>
                </div>
                <div class="meta-cell">
                    <div class="meta-label">Issued By</div>
                    <div class="meta-value">{{ $certificate->organization->name }}</div>
                </div>
            </div>

            <div class="verify-link" style="margin-top: 22px;">
                Certificate No. {{ $certificate->certificate_number }} · Verify at ventiq.co.ls/certify
            </div>
        </div>
    </div>
</body>
</html>
