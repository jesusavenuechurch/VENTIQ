<x-mail::message>
# A bank transfer to check

**{{ $organization }}** says it has paid for **{{ $label }}** by bank transfer.

<x-mail::table>
| | |
|:--|--:|
| Amount | **M{{ number_format((float) $payment->amount, 2) }}** |
| Reference | **{{ $payment->client_reference }}** |
| Paid on | {{ !empty($details['paid_on']) ? \Illuminate\Support\Carbon::parse($details['paid_on'])->format('j F Y') : '—' }} |
| Told us | {{ $details['claimed_by'] ?? '—' }} |
@if (!empty($details['note']))
| Their note | {{ $details['note'] }} |
@endif
</x-mail::table>

Look for it in the account. When it's there, confirm it and the plan or sessions are added straight away; if it isn't, say so and they're told.

<x-mail::button :url="$link">
Confirm or reject it
</x-mail::button>

VENTIQ
</x-mail::message>
