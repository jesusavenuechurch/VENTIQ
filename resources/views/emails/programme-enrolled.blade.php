<x-mail::message>
# You're enrolled

Hi {{ $name }},

You're on **{{ $programme }}**{{ $organization ? ' with ' . $organization : '' }}.

@if (count($days))
**The days**{{ $venue ? ', at ' . $venue : '' }}:

@foreach ($days as $day)
- {{ $day }}
@endforeach
@endif

On each day, check in when you arrive with the QR code or code shown in the room. That's how your attendance is counted.

@if ($rule)
Your certificate comes by email once you've attended {{ $rule }}.
@endif

Thanks,<br>
{{ $organization ?? 'VENTIQ' }}
</x-mail::message>
