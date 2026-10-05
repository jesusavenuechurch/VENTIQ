<x-mail::message>
# Hi {{ $name }}, here's your sign-in code

<x-mail::panel>
<div style="font-size: 32px; font-weight: 900; letter-spacing: 8px; text-align: center; color: #1D4069;">{{ $code }}</div>
</x-mail::panel>

Type it on the VENTIQ sign-in screen, or tap the button to sign in on this device.

<x-mail::button :url="$link">
Sign in to VENTIQ
</x-mail::button>

The code and the button work once, for the next {{ $minutes }} minutes.

If you didn't try to sign in, you can ignore this email; nobody can get in without the code.

VENTIQ
</x-mail::message>
