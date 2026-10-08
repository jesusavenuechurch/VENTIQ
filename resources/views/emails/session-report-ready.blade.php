<x-mail::message>
# Notes are ready for {{ $title }}

Hi {{ $name }},

The notes from **{{ $title }}**{{ $orgName ? " with {$orgName}" : '' }} have been finalized and reviewed.

Reach out to the organizer if you'd like a copy of anything discussed.

Thanks,<br>
{{ $orgName ?? 'VENTIQ' }}
</x-mail::message>
