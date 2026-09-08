<x-mail::message>
# New contact form submission

**From:** {{ $senderName }} ({{ $senderEmail }})

**Subject:** {{ $messageSubject }}

{{ $body }}

<x-mail::button :url="'mailto:' . $senderEmail">
Reply to {{ $senderName }}
</x-mail::button>
</x-mail::message>
