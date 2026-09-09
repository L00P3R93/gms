<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email — {{ $appName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#0a0a0a;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#0a0a0a;">
        <tr>
            <td align="center" style="padding:40px 16px;">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">
                    <tr>
                        <td align="center" style="padding-bottom:24px;">
                            <span style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:rgba(245,197,66,0.6);">
                                {{ $appName }}
                            </span>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;background-color:#1a1a1a;border:1px solid rgba(245,197,66,0.3);border-radius:12px;">
                    <tr>
                        <td style="padding:32px;">

                            <h1 style="margin:0 0 16px;font-size:22px;font-weight:700;color:#f5f5f0;">
                                Verify your email address
                            </h1>

                            <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#e5e5e5;">
                                Hi {{ $user->name }}, thanks for signing up for
                                <strong style="color:#f5f5f0;">{{ $appName }}</strong>.
                                Click the button below to confirm your email address and activate your account.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
                                <tr><td style="height:1px;background-color:rgba(245,197,66,0.2);font-size:0;line-height:0;">&nbsp;</td></tr>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
                                <tr>
                                    <td align="left" style="border-radius:50px;background:linear-gradient(135deg,#f5c542 0%,#ffde74 50%,#f5c542 100%);">
                                        <a href="{{ $verificationUrl }}"
                                           target="_blank"
                                           style="display:inline-block;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#0a0a0a;text-decoration:none;border-radius:50px;">
                                            Verify Email
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 24px;font-size:13px;line-height:1.6;color:#6b6b6b;">
                                This link expires in <strong style="color:#e5e5e5;">60 minutes</strong>.
                                If it expires, you can request a new one from the login page.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
                                <tr><td style="height:1px;background-color:rgba(245,197,66,0.2);font-size:0;line-height:0;">&nbsp;</td></tr>
                            </table>

                            <p style="margin:0;font-size:12px;color:#6b6b6b;line-height:1.8;">
                                If the button above doesn't work, copy and paste this URL into your browser:<br>
                                <a href="{{ $verificationUrl }}"
                                   style="color:#f5c542;word-break:break-all;text-decoration:none;">
                                    {{ $verificationUrl }}
                                </a>
                            </p>

                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">
                    <tr>
                        <td align="center" style="padding-top:20px;">
                            <p style="margin:0;font-size:11px;color:#3a3a3a;line-height:1.6;">
                                If you did not create an account, you can safely ignore this email.<br>
                                &copy; {{ date('Y') }} {{ $appName }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

</body>
</html>
