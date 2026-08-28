<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Certificate — {{ $certificate->client->full_name }}</title>
<style>
    @php
        $interBase64 = base64_encode(file_get_contents(resource_path('fonts/Inter.ttf')));
        $scriptBase64 = base64_encode(file_get_contents(resource_path('fonts/GreatVibes-Regular.ttf')));
    @endphp

    @font-face {
        font-family: 'Inter';
        src: url(data:font/truetype;charset=utf-8;base64,{{ $interBase64 }}) format('truetype');
        font-weight: normal;
        font-style: normal;
    }
    @font-face {
        font-family: 'Signature';
        src: url(data:font/truetype;charset=utf-8;base64,{{ $scriptBase64 }}) format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    @page { margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', 'Helvetica', Arial, sans-serif; background-color: #F7F3EC; }

    .sheet { position: relative; width: 100%; padding: 16px; }

    .frame {
        border: 1.5px solid #D8CFBE;
        position: relative;
        background-color: #ffffff;
        overflow: hidden;
    }

    /* ── Header banner ─────────────────────────────────────────── */
    .header {
        position: relative;
        background-color: #1D4069;
        border-radius: 0 0 90px 90px;
        padding: 24px 60px 34px;
        text-align: center;
        overflow: hidden;
    }
    .header-accent {
        height: 8px;
        background-color: #F07F22;
        margin-top: -4px;
    }

    /* Small square watermark grid — same corner motif as the attendance
       card, so both artifacts read as the same product. */
    .watermark-square {
        position: absolute;
        width: 9px;
        height: 9px;
        background-color: rgba(255,255,255,0.10);
    }

    .org-eyebrow {
        position: relative;
        font-size: 11px; font-weight: normal; color: #F07F22;
        text-transform: uppercase; letter-spacing: 4px;
        margin-bottom: 8px;
    }
    .headline {
        position: relative;
        font-size: 32px; font-weight: bold; color: #ffffff;
        text-transform: uppercase; letter-spacing: 6px;
        line-height: 1;
        margin-bottom: 8px;
    }
    .subline {
        position: relative;
        font-size: 12px; font-weight: normal; color: #C9D6E5;
        text-transform: uppercase; letter-spacing: 5px;
    }

    /* ── Body ───────────────────────────────────────────────────── */
    .body-area { padding: 22px 70px 20px; text-align: center; }

    .presented-to {
        font-size: 12px; font-weight: normal; color: #94a3b8;
        text-transform: uppercase; letter-spacing: 4px;
        margin-bottom: 8px;
    }

    .recipient {
        font-family: 'Signature', cursive;
        font-size: 54px; color: #1D4069;
        line-height: 1;
        margin-bottom: 12px;
    }

    .body-text {
        font-size: 13px; font-weight: normal; color: #475569; line-height: 1.5;
        max-width: 620px; margin: 0 auto 18px;
    }
    .body-text b { color: #0f172a; font-weight: bold; }

    /* ── Seal ───────────────────────────────────────────────────── */
    .seal-row { margin-bottom: 14px; }
    .seal {
        display: block;
        width: 58px; height: 58px;
        line-height: 54px;
        border-radius: 29px;
        background-color: #F07F22;
        border: 2px solid #1D4069;
        text-align: center;
        margin: 0 auto;
        font-size: 8px; font-weight: bold; color: #ffffff;
        text-transform: uppercase; letter-spacing: 1px;
    }

    /* ── Info strip ─────────────────────────────────────────────── */
    .info-strip {
        border-top: 1px solid #E7E0D2;
        padding-top: 14px;
    }
    .info-table { display: table; width: 100%; }
    .info-cell { display: table-cell; width: 33.33%; text-align: center; vertical-align: middle; }
    .info-label {
        font-size: 10px; font-weight: bold; color: #94a3b8;
        text-transform: uppercase; letter-spacing: 2px; margin-bottom: 6px;
    }
    .info-value { font-size: 14px; font-weight: bold; color: #0f172a; }
    .info-small { font-size: 9px; font-weight: normal; color: #94a3b8; margin-top: 6px; }

    .footer-bar { height: 10px; background-color: #1D4069; }
</style>
</head>
<body>
    <div class="sheet">
        <div class="frame">

            <div class="header">
                {{-- Watermark square grid, top-left and top-right corners --}}
                @foreach([0, 1] as $cornerX)
                    @for($row = 0; $row < 5; $row++)
                        @for($col = 0; $col < 5 - $row; $col++)
                            <div class="watermark-square" style="
                                top: {{ 16 + $row * 20 }}px;
                                {{ $cornerX === 0 ? 'left' : 'right' }}: {{ 16 + $col * 20 }}px;
                            "></div>
                        @endfor
                    @endfor
                @endforeach

                <div class="org-eyebrow">{{ $certificate->organization->name }}</div>
                <div class="headline">Certificate</div>
                <div class="subline">Of Attendance</div>
            </div>
            <div class="header-accent"></div>

            <div class="body-area">
                <div class="presented-to">This certifies that</div>
                <div class="recipient">{{ $certificate->client->full_name }}</div>

                <div class="body-text">
                    participated in <b>{{ $certificate->programme->name }}</b>, hosted by
                    <b>{{ $certificate->organization->name }}</b>@if($certificate->programme->venue) at {{ $certificate->programme->venue }}@endif,
                    and is recognized for their attendance and contribution.
                </div>

                <div class="seal-row">
                    <div class="seal">Verified</div>
                </div>

                <div class="info-strip">
                    <div class="info-table">
                        <div class="info-cell">
                            <div class="info-label">Issued</div>
                            <div class="info-value">{{ $certificate->issued_at->format('d M Y') }}</div>
                        </div>
                        <div class="info-cell">
                            <img src="data:image/png;base64,{{ $qrBase64 }}" width="60" height="60">
                            <div class="info-small">Scan to verify</div>
                        </div>
                        <div class="info-cell">
                            <div class="info-label">Certificate No.</div>
                            <div class="info-value">{{ $certificate->certificate_number }}</div>
                            <div class="info-small">ventiq.co.ls/certify</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-bar"></div>
        </div>
    </div>
</body>
</html>
