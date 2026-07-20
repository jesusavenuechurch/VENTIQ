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
        .container { max-width: 560px; margin: 0 auto; }
        .brand-row { text-align: center; margin-bottom: 24px; }
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
        .card { background: #ffffff; border-radius: 28px; padding: 40px; }
        .status-badge {
            display: inline-block;
            background-color: #f59e0b;
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
            font-size: 22px;
            font-weight: 900;
            letter-spacing: -0.5px;
            text-transform: uppercase;
            margin: 0 0 24px 0;
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
            margin: 0 0 18px 0;
        }
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 0 0 22px 0; }
        .method-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 18px;
            margin: 10px 0;
        }
        .method-label {
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0 0 8px 0;
        }
        .method-row {
            font-size: 13px;
            color: #334155;
            margin: 2px 0;
        }
        .ref-box {
            background: #0f172a;
            color: #ffffff;
            border-radius: 14px;
            padding: 14px 18px;
            margin-top: 20px;
            text-align: center;
        }
        .ref-box .label-light {
            color: rgba(255,255,255,0.5);
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 4px;
        }
        .ref-box .ref-value {
            font-size: 17px;
            font-weight: 900;
            letter-spacing: 2px;
        }
        .steps {
            margin: 24px 0 0 0;
            padding: 0;
            list-style: none;
        }
        .steps li {
            font-size: 13px;
            color: #334155;
            padding-left: 22px;
            margin-bottom: 10px;
            position: relative;
        }
        .steps li:before {
            content: "→";
            position: absolute;
            left: 0;
            color: #f59e0b;
            font-weight: 900;
        }
        .note { font-size: 12px; color: #64748b; line-height: 1.6; margin-top: 20px; }
        .footer { text-align: center; margin-top: 28px; }
        .footer p { color: rgba(255,255,255,0.3); font-size: 10px; margin: 4px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="brand-row">
            <div class="brand-name">Ventiq</div>
            <div class="brand-sub">Digital Fulfillment</div>
        </div>

        <div class="card">
            <div class="status-badge">Pending Payment</div>
            <h1 class="event-name">{{ $event->name }}</h1>

            <p style="font-size: 14px; color: #334155; margin: 0 0 24px 0;">
                Hi {{ $client->full_name }}, we've received your registration. Complete payment below to confirm your spot.
            </p>

            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="50%">
                        <div class="label">Tier</div>
                        <div class="value">{{ $tier->tier_name }}</div>
                    </td>
                    <td width="50%">
                        <div class="label">Amount Due</div>
                        <div class="value">{{ number_format($ticket->amount) }} LSL</div>
                    </td>
                </tr>
            </table>

            <hr class="divider">

            <div class="label" style="margin-bottom: 12px;">Payment Methods</div>

            @foreach($paymentMethods as $method)
                <div class="method-card">
                    <div class="method-label">{{ $method->label }}</div>

                    @if($method->payment_method !== 'cash')
                        <div class="method-row"><strong>{{ $method->getAccountFieldLabel() }}:</strong> {{ $method->account_number }}</div>
                        @if($method->account_name)
                            <div class="method-row"><strong>Account Name:</strong> {{ $method->account_name }}</div>
                        @endif
                    @else
                        <div class="method-row" style="color: #64748b;">Pay in person at the venue.</div>
                    @endif

                    @if($method->instructions)
                        <div class="method-row" style="color: #64748b; font-size: 12px; margin-top: 6px;">{{ $method->instructions }}</div>
                    @endif
                </div>
            @endforeach

            <div class="ref-box">
                <div class="label-light">Use This As Your Payment Reference</div>
                <div class="ref-value">{{ $ticket->ticket_number }}</div>
            </div>

            <ul class="steps">
                <li>Make your payment using the details above</li>
                <li>Our team verifies payment within 24 hours</li>
                <li>You'll get a confirmation email with your ticket</li>
                <li>Bring your ticket (digital or printed) to the entrance</li>
            </ul>

            <p class="note">
                Questions? Reach us at {{ $organization->contact_email ?? $organization->email }}.
            </p>
        </div>

        <div class="footer">
            <p>{{ $organization->name }} &middot; {{ date('Y') }}</p>
        </div>
    </div>
</body>
</html>