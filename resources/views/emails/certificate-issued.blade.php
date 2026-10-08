<x-mail::message>
# Your certificate is ready

Hi {{ $name }},

You've been issued a certificate for participating in **{{ $programmeName }}**{{ $orgName ? " with {$orgName}" : '' }}. It's attached to this email as a PDF.

You can also verify it any time, or add it straight to your LinkedIn profile as a certification.

<x-mail::button :url="$linkedInUrl">
Add to LinkedIn Profile
</x-mail::button>

<x-mail::button :url="$verifyUrl">
View & Verify Certificate
</x-mail::button>

Thanks,<br>
{{ $orgName ?? 'VENTIQ' }}
</x-mail::message>
