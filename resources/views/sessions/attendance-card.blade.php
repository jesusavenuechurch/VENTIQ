<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Attendance Card — {{ $participant->client->full_name }}</title>
<style>
    @page { margin: 0; size: A4 landscape; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Helvetica', Arial, sans-serif; background-color: #000000; padding: 60px; }

    .card-wrapper {
        width: 950px; margin: 0 auto; background-color: #ffffff;
        border-radius: 40px; overflow: hidden; position: relative;
    }
    .main-table { width: 100%; border-collapse: collapse; }

    .info-side {
        width: 68%; padding: 70px 60px;
        display: flex; flex-direction: column; justify-content: center;
    }

    .was-part-of {
        font-size: 12px; font-weight: 700; color: #94a3b8;
        text-transform: uppercase; letter-spacing: 3px;
        margin-bottom: 14px;
    }

    .session-title {
        font-size: 44px; font-weight: 900; color: #0f172a;
        letter-spacing: -1.5px; text-transform: uppercase;
        line-height: 1.08;
        border-bottom: 4px solid #F07F22;
        padding-bottom: 20px;
        display: inline-block;
    }

    .name {
        font-size: 21px; font-weight: 800; color: #475569;
        margin-top: 32px;
    }
    .name b { color: #0f172a; font-weight: 900; }

    .meta {
        font-size: 12px; font-weight: 600; color: #94a3b8;
        margin-top: 8px;
    }

    .host-side {
        width: 32%; background-color: #f8fafc;
        border-left: 2px dashed #e2e8f0;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 60px 30px; text-align: center; position: relative;
    }

    .host-label { font-size: 9px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 10px; }
    .host-name { font-size: 20px; font-weight: 900; color: #0f172a; letter-spacing: -0.5px; text-transform: uppercase; line-height: 1.2; }

    .brand-mark {
        position: absolute; bottom: 28px; right: 28px;
        font-size: 14px; font-weight: 900; color: #0f172a; letter-spacing: -0.3px;
    }
    .brand-mark span { color: #F07F22; }

    .hole { position: absolute; width: 36px; height: 36px; background-color: #000000; border-radius: 50%; right: 32%; z-index: 20; }
    .hole-top { top: -18px; } .hole-bottom { bottom: -18px; }
</style>
</head>
<body>
    <div class="card-wrapper">
        <div class="hole hole-top"></div>
        <div class="hole hole-bottom"></div>

        <table class="main-table">
            <tr>
                <td class="info-side">
                    <p class="was-part-of">Was part of</p>
                    <div class="session-title">{{ $session->resolved_title }}</div>

                    <p class="name"><b>{{ $participant->client->full_name }}</b></p>
                    @if($participant->institution || $participant->position)
                    <p class="meta" style="margin-top: 2px;">
                        {{ $participant->position ? $participant->position . ' · ' : '' }}{{ $participant->institution }}
                    </p>
                    @endif
                    <p class="meta">Hosted by {{ $orgName }} · {{ $session->date?->format('d F Y') ?? $session->created_at->format('d F Y') }}</p>
                </td>

                <td class="host-side">
                    <div class="host-label">Hosted by</div>
                    <div class="host-name">{{ $orgName }}</div>
                </td>
            </tr>
        </table>

        <div class="brand-mark">VENTI<span>Q</span></div>
    </div>
</body>
</html>