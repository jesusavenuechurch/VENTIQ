{{--
    A certificate, in one of the designer's layouts (CertificateDesign):
    banner, classic or minimal. $c holds everything (CertificateDesign::view()).
--}}
@php
    $interBase64 = base64_encode(file_get_contents(resource_path('fonts/Inter.ttf')));
    $scriptBase64 = base64_encode(file_get_contents(resource_path('fonts/GreatVibes-Regular.ttf')));
    $p = $c['primary'];
    $a = $c['accent'];
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Certificate: {{ $c['recipient'] }}</title>
<style>
    @font-face { font-family: 'Inter'; src: url(data:font/truetype;charset=utf-8;base64,{{ $interBase64 }}) format('truetype'); font-weight: normal; font-style: normal; }
    @font-face { font-family: 'Signature'; src: url(data:font/truetype;charset=utf-8;base64,{{ $scriptBase64 }}) format('truetype'); font-weight: normal; font-style: normal; }

    @page { margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { width: 100%; }
    body { font-family: 'Inter', 'Helvetica', Arial, sans-serif; color: #0f172a; }

    .recipient { font-family: 'Signature', cursive; color: {{ $p }}; line-height: 1; }
    .wording { color: #475569; line-height: 1.55; }
    .wording b { color: #0f172a; font-weight: bold; }

    /* The row at the foot: signatures either side of the QR code. */
    table.foot { width: 100%; border-collapse: collapse; }
    table.foot td { vertical-align: bottom; text-align: center; width: 33.33%; padding: 0 10px; }
    .sig-image { height: 58px; }
    .sig-image img { max-height: 58px; max-width: 220px; }
    .sig-line { border-top: 1px solid #94a3b8; width: 230px; margin: 2px auto 0; padding-top: 5px; }
    .sig-name { font-size: 14px; font-weight: bold; color: #0f172a; }
    .sig-title { font-size: 11px; color: #64748b; margin-top: 2px; }
    .fact-label { font-size: 9px; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 4px; }
    .fact-value { font-size: 13px; font-weight: bold; color: #0f172a; }
    .small { font-size: 9px; color: #94a3b8; margin-top: 4px; }
    .logo img { max-height: 60px; max-width: 220px; }

    /* Each layout fills the A4 landscape page (1122 × 793 px); the foot sits near the bottom. */

    /* ── Banner ─────────────────────────────────────────────── */
    .banner { background-color: #F7F3EC; padding: 18px; height: 757px; }
    .banner .frame { position: relative; border: 1.5px solid #D8CFBE; background-color: #fff; height: 752px; }
    .banner .header { background-color: {{ $p }}; border-radius: 0 0 110px 110px; padding: 34px 60px 42px; text-align: center; }
    .banner .accent { height: 9px; background-color: {{ $a }}; margin-top: -4px; }
    .banner .logo { margin-bottom: 12px; }
    .banner .org { font-size: 13px; color: {{ $a }}; text-transform: uppercase; letter-spacing: 5px; margin-bottom: 10px; }
    .banner .title { font-size: 42px; font-weight: bold; color: #fff; text-transform: uppercase; letter-spacing: 8px; line-height: 1; margin-bottom: 10px; }
    .banner .subtitle { font-size: 14px; color: #C9D6E5; text-transform: uppercase; letter-spacing: 6px; }
    .banner .body { padding: 38px 90px 0; text-align: center; }
    .banner .presented { font-size: 13px; color: #94a3b8; text-transform: uppercase; letter-spacing: 5px; margin-bottom: 12px; }
    .banner .recipient { font-size: 70px; margin-bottom: 18px; }
    .banner .wording { font-size: 16px; margin: 0 auto; width: 800px; }
    .banner .rule { position: absolute; left: 80px; right: 80px; bottom: 44px; border-top: 1px solid #E7E0D2; padding-top: 18px; }
    .banner .bottom { position: absolute; left: 0; right: 0; bottom: 0; height: 12px; background-color: {{ $p }}; }

    /* ── Classic ────────────────────────────────────────────── */
    .classic { padding: 30px; height: 733px; }
    .classic .outer { border: 7px double {{ $p }}; padding: 7px; height: 705px; }
    .classic .inner { position: relative; border: 1px solid {{ $a }}; padding: 44px 90px 0; height: 659px; text-align: center; }
    .classic .logo { margin-bottom: 12px; }
    .classic .org { font-size: 13px; color: #64748b; text-transform: uppercase; letter-spacing: 5px; margin-bottom: 18px; }
    .classic .title { font-family: 'DejaVu Serif', serif; font-size: 52px; color: {{ $p }}; text-transform: uppercase; letter-spacing: 10px; line-height: 1; }
    .classic .subtitle { font-family: 'DejaVu Serif', serif; font-size: 17px; font-style: italic; color: {{ $a }}; margin-top: 10px; }
    .classic .ornament { width: 140px; margin: 22px auto 20px; border-top: 2px solid {{ $a }}; }
    .classic .presented { font-family: 'DejaVu Serif', serif; font-size: 15px; font-style: italic; color: #64748b; margin-bottom: 8px; }
    .classic .recipient { font-size: 70px; margin-bottom: 16px; }
    .classic .wording { font-family: 'DejaVu Serif', serif; font-size: 15px; margin: 0 auto; width: 780px; }
    .classic .foot-wrap { position: absolute; left: 70px; right: 70px; bottom: 36px; }

    /* ── Minimal ────────────────────────────────────────────── */
    .minimal { position: relative; height: 793px; }
    .minimal .bar { position: absolute; left: 0; top: 0; bottom: 0; width: 22px; background-color: {{ $p }}; }
    .minimal .bar-accent { position: absolute; left: 22px; top: 0; bottom: 0; width: 6px; background-color: {{ $a }}; }
    .minimal .content { padding: 60px 100px 0 110px; }
    .minimal .top { width: 100%; border-collapse: collapse; margin-bottom: 46px; }
    .minimal .top td { vertical-align: middle; }
    .minimal .org { font-size: 13px; color: #64748b; text-transform: uppercase; letter-spacing: 5px; text-align: right; }
    .minimal .title { font-size: 48px; font-weight: bold; color: {{ $p }}; line-height: 1; }
    .minimal .subtitle { font-size: 15px; color: {{ $a }}; text-transform: uppercase; letter-spacing: 5px; margin-top: 10px; }
    .minimal .presented { font-size: 13px; color: #94a3b8; text-transform: uppercase; letter-spacing: 4px; margin: 46px 0 8px; }
    .minimal .recipient { font-size: 74px; margin-bottom: 18px; }
    .minimal .wording { font-size: 16px; width: 820px; }
    .minimal .foot-wrap { position: absolute; left: 110px; right: 100px; bottom: 60px; }
    .minimal table.foot td:first-child { text-align: left; padding-left: 0; }
    .minimal table.foot td:first-child .sig-line { margin-left: 0; }
</style>
</head>
<body>
@if ($c['layout'] === 'classic')
    <div class="classic"><div class="outer"><div class="inner">
        @if ($c['logo'])<div class="logo"><img src="{{ $c['logo'] }}" alt=""></div>@endif
        <div class="org">{{ $c['organization'] }}</div>
        <div class="title">{{ $c['title'] }}</div>
        @if ($c['subtitle'])<div class="subtitle">{{ $c['subtitle'] }}</div>@endif
        <div class="ornament"></div>
        <div class="presented">This certifies that</div>
        <div class="recipient">{{ $c['recipient'] }}</div>
        <div class="wording">{!! $c['wording'] !!}</div>
        <div class="foot-wrap">@include('certificates.partials.foot', ['c' => $c])</div>
    </div></div></div>
@elseif ($c['layout'] === 'minimal')
    <div class="minimal">
        <div class="bar"></div><div class="bar-accent"></div>
        <div class="content">
            <table class="top"><tr>
                <td>@if ($c['logo'])<span class="logo"><img src="{{ $c['logo'] }}" alt=""></span>@endif</td>
                <td class="org">{{ $c['organization'] }}</td>
            </tr></table>
            <div class="title">{{ $c['title'] }}</div>
            @if ($c['subtitle'])<div class="subtitle">{{ $c['subtitle'] }}</div>@endif
            <div class="presented">Presented to</div>
            <div class="recipient">{{ $c['recipient'] }}</div>
            <div class="wording">{!! $c['wording'] !!}</div>
        </div>
        <div class="foot-wrap">@include('certificates.partials.foot', ['c' => $c])</div>
    </div>
@else
    <div class="banner"><div class="frame">
        <div class="header">
            @if ($c['logo'])<div class="logo"><img src="{{ $c['logo'] }}" alt=""></div>@endif
            <div class="org">{{ $c['organization'] }}</div>
            <div class="title">{{ $c['title'] }}</div>
            @if ($c['subtitle'])<div class="subtitle">{{ $c['subtitle'] }}</div>@endif
        </div>
        <div class="accent"></div>
        <div class="body">
            <div class="presented">This certifies that</div>
            <div class="recipient">{{ $c['recipient'] }}</div>
            <div class="wording">{!! $c['wording'] !!}</div>
            <div class="rule">@include('certificates.partials.foot', ['c' => $c])</div>
        </div>
        <div class="bottom"></div>
    </div></div>
@endif
</body>
</html>
