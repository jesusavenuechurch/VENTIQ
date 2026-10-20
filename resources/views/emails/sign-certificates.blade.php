<x-mail::message>
# Your signature

Hi {{ $user->name }},

You can now sign certificates for {{ $organization }}. Draw your signature once in VENTIQ; it goes on the certificates you sign from then on, until you draw it again.

<x-mail::button :url="$link">
Draw my signature
</x-mail::button>

Thanks,<br>
{{ $organization }}
</x-mail::message>
