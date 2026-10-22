<x-mail::message>
# Your action points

Hi {{ $name }},

These are on your name at {{ $organization }}:

@foreach ($points as $point)
- **{{ $point['task'] }}**{{ $point['overdue'] ? ' (overdue, ' . $point['when'] . ')' : ' (' . $point['when'] . ')' }}<br>
  <span style="color:#94a3b8">From {{ $point['from'] }}</span>
@endforeach

@if ($link)
<x-mail::button :url="$link">
See them in VENTIQ
</x-mail::button>
@endif

If one is done, or isn't yours, tell the organizer and they'll update it.

Thanks,<br>
{{ $organization }}
</x-mail::message>
