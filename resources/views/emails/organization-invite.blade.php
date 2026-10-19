<x-mail::message>
# You've been invited to {{ $orgName }}

{{ $inviterName }} has invited you to join **{{ $orgName }}** on VENTIQ.

Once you accept, you'll help run {{ $orgName }}'s events on VENTIQ: attendees, payments, check-in and reports, depending on the access you've been given.

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

This invite link expires in 7 days.

Thanks,<br>
VENTIQ
</x-mail::message>