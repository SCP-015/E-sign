<x-mail::message>
# Document Signing Invitation

Hello,

**{{ $senderName }}** has invited you to sign a document: **{{ $documentTitle }}**.

Please click the button below to register/login and access the document.

<x-mail::button :url="$url">
View and Sign Document
</x-mail::button>

Note: You will need to complete your KYC verification and setup your digital signature before you can sign the document.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
