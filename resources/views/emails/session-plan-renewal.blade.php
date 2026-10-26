<x-mail::message>
@if ($stage === 'due')
# Your {{ $label }} plan renews soon

{{ $organization->name }}'s {{ $label }} plan comes to the end of its month on **{{ $renews }}**. To keep it for the next month, pay **M{{ number_format((float) $price, 2) }}** by EcoCash, M-Pesa or bank transfer.

<x-mail::button :url="$payUrl">
Renew the plan
</x-mail::button>

Paying by bank transfer? Tell us once you've paid and the plan keeps running while we confirm it, within 24 hours.
@elseif ($stage === 'grace')
# Your {{ $label }} plan is running on grace

The new month of {{ $organization->name }}'s {{ $label }} plan hasn't been paid yet. It keeps working until **{{ $graceBy }}**. Pay **M{{ number_format((float) $price, 2) }}** before then to keep it.

<x-mail::button :url="$payUrl">
Pay now
</x-mail::button>

After {{ $graceBy }} the plan goes back to Free. Nothing is deleted.
@else
# You're on the Free plan

The {{ $label }} plan for {{ $organization->name }} wasn't renewed, so it has gone back to Free. **Nothing has been deleted**: your sessions, reports, minutes and people are all still there.

You can take the {{ $label }} plan again at any time.

<x-mail::button :url="$planUrl">
See your plan
</x-mail::button>
@endif

Questions about paying? Reply to this email.

Thanks,<br>
VENTIQ
</x-mail::message>
