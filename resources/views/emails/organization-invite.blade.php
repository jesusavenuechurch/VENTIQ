<x-mail::message>
# You've been invited to {{ $orgName }}

{{ $inviterName }} has invited you to join **{{ $orgName }}** on VENTIQ.

Once you accept, you'll share the same workspace — sessions, notes, attendance, and reports — with the rest of the team.

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

This invite link expires in 7 days.

Thanks,<br>
VENTIQ
</x-mail::message>