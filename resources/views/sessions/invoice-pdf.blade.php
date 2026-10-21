@php($org = $invoice->organization)
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 48px 56px; }
    body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 10.5px; line-height: 1.5; }
    table { border-collapse: collapse; }
    .top td { vertical-align: top; }
    h1 { font-size: 22px; color: #1D4069; margin: 0; letter-spacing: 1px; }
    .muted { color: #64748b; }
    .label { font-size: 8.5px; text-transform: uppercase; letter-spacing: 1.5px; color: #94a3b8; margin-bottom: 2px; }
    .box { border: 1px solid #e2e8f0; padding: 10px 12px; }
    table.lines { width: 100%; margin-top: 22px; }
    table.lines th { text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; border-bottom: 1.5px solid #1D4069; padding: 6px 6px 6px 0; }
    table.lines td { border-bottom: 1px solid #f1f5f9; padding: 6px 6px 6px 0; }
    .num, table.lines th.num { text-align: right; white-space: nowrap; }
    .total td { border-top: 1.5px solid #1D4069; border-bottom: none; font-weight: bold; font-size: 12px; padding-top: 8px; }
    .paid { color: #059669; font-weight: bold; }
</style>
</head>
<body>
    <table class="top" style="width: 100%;">
        <tr>
            <td>
                <img src="{{ $ventiqLogo }}" alt="VENTIQ" style="height: 34px;">
                <p class="muted" style="margin: 6px 0 0;">{{ config('ventiq.company.website') }} · {{ config('ventiq.billing.email') }}</p>
            </td>
            <td style="text-align: right;">
                <h1>INVOICE</h1>
                <p style="margin: 4px 0 0; font-size: 12px; font-weight: bold;">{{ $invoice->number }}</p>
                @if ($invoice->khoebo_reference)<p class="muted" style="margin: 2px 0 0;">Accounting reference {{ $invoice->khoebo_reference }}</p>@endif
                @if ($invoice->status === 'paid')<p class="paid" style="margin: 6px 0 0;">PAID {{ $invoice->paid_at?->format('j M Y') }}</p>@endif
            </td>
        </tr>
    </table>

    <table class="top" style="width: 100%; margin-top: 26px;">
        <tr>
            <td style="width: 50%; padding-right: 16px;">
                <div class="box">
                    <div class="label">Bill to</div>
                    @if ($orgLogo)<img src="{{ $orgLogo }}" alt="" style="max-height: 30px; max-width: 140px; margin: 2px 0 4px;"><br>@endif
                    <b>{{ $org->name }}</b>
                    @if ($billingEmail)<br><span class="muted">{{ $billingEmail }}</span>@endif
                </div>
            </td>
            <td style="width: 50%;">
                <table style="width: 100%;">
                    <tr><td class="label" style="padding: 2px 0;">Invoice date</td><td class="num">{{ $invoice->created_at->format('j F Y') }}</td></tr>
                    <tr><td class="label" style="padding: 2px 0;">For</td><td class="num">VENTIQ Sessions, {{ $invoice->periodLabel() }}</td></tr>
                    <tr><td class="label" style="padding: 2px 0;">Rate</td><td class="num">M{{ number_format((float) $invoice->rate, 2) }} a session, {{ $freePerMonth }} free a month</td></tr>
                    <tr><td class="label" style="padding: 2px 0;">Due by</td><td class="num"><b>{{ $invoice->due_date->format('j F Y') }}</b></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="lines">
        <tr><th style="width: 22px;">#</th><th>Session</th><th>Held</th><th class="num">Amount</th></tr>
        @foreach ($invoice->sessions as $i => $s)
            <tr>
                <td class="muted">{{ $i + 1 }}</td>
                <td>{{ $s->resolved_title }}@if ((float) $s->charge === 0.0) <span class="muted">(free session)</span>@endif</td>
                <td>{{ $s->counted_at->format('j M Y') }}</td>
                <td class="num">M{{ number_format((float) $s->charge, 2) }}</td>
            </tr>
        @endforeach
        <tr class="total"><td></td><td colspan="2">Total due</td><td class="num">M{{ number_format((float) $invoice->total, 2) }}</td></tr>
        @if ((float) $invoice->paid_amount > 0 && $invoice->status !== 'paid')
            <tr><td></td><td colspan="2">Paid so far</td><td class="num">M{{ number_format((float) $invoice->paid_amount, 2) }}</td></tr>
            <tr><td></td><td colspan="2"><b>Balance</b></td><td class="num"><b>M{{ number_format($invoice->outstanding(), 2) }}</b></td></tr>
        @endif
    </table>
    <p class="muted" style="margin-top: 6px;">VENTIQ is not VAT registered; no VAT is charged.</p>

    <div class="box" style="margin-top: 22px;">
        <div class="label">How to pay</div>
        {{ config('ventiq.billing.payment_instructions') }}<br>
        Quote <b>{{ $invoice->number }}</b> as the reference.
    </div>
</body>
</html>
