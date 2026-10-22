<x-mail::message>
@if ($confirmed)
# Payment received

Thank you. Your bank transfer of **M{{ number_format((float) $payment->amount, 2) }}** (reference {{ $payment->client_reference }}) has come in, and **{{ $label }}** is now on your account.
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
