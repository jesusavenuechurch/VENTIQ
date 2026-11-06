<x-mail::message>
@if ($confirmed)
# Payment received

@php($applied = $payment->callback_payload ?? [])
@if (($applied['applied'] ?? null) === 'extended')
Thank you. Your bank transfer of **M{{ number_format((float) $payment->amount, 2) }}** (reference {{ $payment->client_reference }}) has come in. It pays **{{ $label }}** for another month: your plan is now paid until **{{ \Illuminate\Support\Carbon::parse($applied['paid_until'])->format('j F Y') }}**.

This month's sessions carry on as they are. Your next {{ $applied['sessions'] ?? '' }} sessions start on {{ \Illuminate\Support\Carbon::parse($applied['next_month'])->format('j F') }}. Need more before then? Add sessions from your plan page.
@elseif (($applied['applied'] ?? null) === 'restarted')
Thank you. Your bank transfer of **M{{ number_format((float) $payment->amount, 2) }}** (reference {{ $payment->client_reference }}) has come in. Your sessions for the month had run out, so a new month of **{{ $label }}** starts today, with **{{ $applied['sessions'] ?? '' }} sessions** to use, paid until **{{ \Illuminate\Support\Carbon::parse($applied['paid_until'])->format('j F Y') }}**.
@else
Thank you. Your bank transfer of **M{{ number_format((float) $payment->amount, 2) }}** (reference {{ $payment->client_reference }}) has come in, and **{{ $label }}** is now on your account{{ isset($applied['sessions']) ? ', with ' . $applied['sessions'] . ' sessions to use this month' : '' }}.
@endif
@else
# We couldn't find your transfer

We looked for your bank transfer of **M{{ number_format((float) $payment->amount, 2) }}** with the reference **{{ $payment->client_reference }}**, for {{ $label }}, and couldn't match it in our account.

@if ($reason)
> {{ $reason }}
@endif

If you've paid, reply to this email with the proof of payment and we'll sort it out. Nothing has been added yet.
@endif

<x-mail::button :url="$link">
See your plan
</x-mail::button>

Thanks,<br>
VENTIQ
</x-mail::message>
