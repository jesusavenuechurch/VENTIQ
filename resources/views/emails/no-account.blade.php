<x-mail::message>
# There's no VENTIQ account for this email

Someone (hopefully you) asked to sign in to VENTIQ as **{{ $email }}**, but no account uses this address.

If you signed up with a different email, or with Google, sign in that way instead. If you're new, you can create an account:

<x-mail::button :url="$registerUrl">
Create an account
</x-mail::button>

If this wasn't you, you can ignore this email.

VENTIQ
</x-mail::message>
