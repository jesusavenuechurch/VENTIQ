<x-mail::message>
@if ($stage === 'ending')
# {{ $name }}'s pilot ends on {{ $ends }}

They've been on a Sessions pilot since {{ $since }} and have held **{{ $held }} {{ $held === 1 ? 'session' : 'sessions' }}** so far.

On {{ $ends }} the pilot ends by itself and they go back to their plan. Before then, you can renew it for another three months, or set the rate you've agreed with them.

<x-mail::button :url="$ratesUrl">
Open corporate rates
</x-mail::button>
@else
# Your pilot has ended

{{ $name }}'s VENTIQ Sessions pilot, which ran from {{ $since }} to {{ $ends }}, has ended. You held **{{ $held }} {{ $held === 1 ? 'session' : 'sessions' }}** on it.

**Nothing has been deleted.** Your sessions, minutes, reports and people are all still there. New sessions now use your Sessions plan.

To carry on as you were, reply to this email and we'll agree the next step with you.

<x-mail::button :url="$planUrl">
See your plan
</x-mail::button>
@endif

Thanks,<br>
VENTIQ
</x-mail::message>
