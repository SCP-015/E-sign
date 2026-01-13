<x-mail::message>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
    <tr>
        <td align="center">
            <div style="display: inline-block; padding: 6px 12px; border: 1px solid #c7d2fe; background: #eef2ff; color: #4f46e5; font-size: 12px; font-weight: 600; letter-spacing: 0.12em; border-radius: 999px;">
                DOCUMENT SIGNING INVITATION
            </div>
            <h1 style="margin: 16px 0 8px; font-size: 24px; line-height: 1.2; color: #0f172a;">
                You are invited to sign
            </h1>
            <p style="margin: 0; color: #475569; font-size: 14px;">
                {{ $senderName }} invited you to sign a document.
            </p>
        </td>
    </tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 12px; margin-bottom: 24px;">
    <tr>
        <td style="padding: 16px 18px;">
            <p style="margin: 0 0 6px; font-size: 11px; letter-spacing: 0.16em; text-transform: uppercase; color: #64748b;">
                Document
            </p>
            <p style="margin: 0; font-size: 16px; font-weight: 600; color: #0f172a;">
                {{ $documentTitle }}
            </p>
        </td>
    </tr>
</table>

<div style="text-align: center; margin: 24px 0;">
    <a href="{{ $url }}" style="display: inline-block; background: #4f46e5; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 12px; font-weight: 600; font-size: 14px;">
        View and Sign Document
    </a>
</div>

<p style="margin: 0 0 10px; color: #475569; font-size: 14px;">
    Please complete KYC verification and set up your digital signature before signing. This invitation expires in 7 days.
</p>

<div style="border-top: 1px solid #e2e8f0; margin-top: 20px; padding-top: 16px;">
    <p style="margin: 0 0 6px; color: #64748b; font-size: 12px;">
        If the button does not work, copy and paste this link into your browser:
    </p>
    <p style="margin: 0; font-size: 12px;">
        <a href="{{ $url }}" style="color: #4f46e5; word-break: break-all;">{{ $url }}</a>
    </p>
</div>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
