<x-mail::message>
# Thank you for attending {{ $title }}

Hi {{ $name }},

Thank you for being part of **{{ $title }}**{{ $orgName ? " with {$orgName}" : '' }}.

Here's your attendance card — share it on your socials to show you were there. It's attached to this email, or you can view it below.

<x-mail::button :url="$cardUrl">
View & Share Your Card
</x-mail::button>

Thanks,<br>
{{ $orgName ?? 'VENTIQ' }}
</x-mail::message>