<x-mail::message>
# Invoice {{ $invoice->number }}

Hello {{ $organization }},

Here is your VENTIQ Sessions invoice for **{{ $invoice->periodLabel() }}**: {{ $invoice->sessions_count }} {{ $invoice->sessions_count === 1 ? 'session' : 'sessions' }} held{{ $invoice->free_count ? ', ' . $invoice->free_count . ' of them free' : '' }}, at your agreed rate of M{{ number_format((float) $invoice->rate, 2) }} a session.

<x-mail::table>
| | |
|:--|--:|
| Amount due | **M{{ number_format((float) $invoice->total, 2) }}** |
| Due by | {{ $invoice->due_date->format('j F Y') }} |
@if ($invoice->khoebo_reference)
| Accounting reference | {{ $invoice->khoebo_reference }} |
@endif
</x-mail::table>

The invoice is attached, with the sessions it covers.

@if (\App\Support\BankDetails::configured())
**To pay by bank transfer or deposit:**

<x-mail::table>
| | |
|:--|:--|
@foreach (\App\Support\BankDetails::lines($invoice->number) as $l)
| {{ $l['label'] }} | **{{ $l['value'] }}** |
@endforeach
</x-mail::table>
@endif

Please quote **{{ $invoice->number }}** with your payment, and reply with the proof of payment.

<x-mail::button :url="$link">
See it in VENTIQ
</x-mail::button>

Questions about it? Reply to this email.

Thanks,<br>
VENTIQ
</x-mail::message>
