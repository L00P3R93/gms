<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Picture Violation — {{ $appName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#0a0a0a;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#0a0a0a;">
        <tr>
            <td align="center" style="padding:40px 16px;">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">
                    <tr>
                        <td align="center" style="padding-bottom:24px;">
                            <span style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:rgba(245,197,66,0.6);">
                                {{ $appName }} · Community Standards
                            </span>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;background-color:#1a1a1a;border:1px solid rgba(245,197,66,0.3);border-radius:12px;">
                    <tr>
                        <td style="padding:32px;">

                            <h1 style="margin:0 0 16px;font-size:22px;font-weight:700;color:#f5f5f0;">
                                Profile Picture Removed
                            </h1>

                            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#e5e5e5;">
                                Hi {{ $name }},
                            </p>

                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#e5e5e5;">
                                Your profile picture has been removed by our moderation team because it did not meet our community standards.
                            </p>

                            @if($autoBanned)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;background-color:#2a1010;border:1px solid rgba(220,38,38,0.4);border-radius:8px;">
                                <tr>
                                    <td style="padding:16px;">
                                        <p style="margin:0;font-size:14px;line-height:1.6;color:#f87171;font-weight:600;">
                                            ⚠️ Account Suspended
                                        </p>
                                        <p style="margin:8px 0 0;font-size:14px;line-height:1.6;color:#fca5a5;">
                                            This is your {{ $strikeCount }}{{ $strikeCount === 1 ? 'st' : ($strikeCount === 2 ? 'nd' : 'rd') }} violation. Your account has been suspended due to repeated violations. Please contact support to appeal.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            @else
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;background-color:#1a1500;border:1px solid rgba(245,197,66,0.3);border-radius:8px;">
                                <tr>
                                    <td style="padding:16px;">
                                        <p style="margin:0;font-size:14px;line-height:1.6;color:#fbbf24;font-weight:600;">
                                            Strike {{ $strikeCount }} of 3
                                        </p>
                                        <p style="margin:8px 0 0;font-size:14px;line-height:1.6;color:#fde68a;">
                                            Repeated violations may result in your account being suspended. Please ensure your profile picture complies with our community standards.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            @endif

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
                                <tr><td style="height:1px;background-color:rgba(245,197,66,0.2);font-size:0;line-height:0;">&nbsp;</td></tr>
                            </table>

                            <p style="margin:0;font-size:14px;line-height:1.6;color:#6b6b6b;">
                                If you believe this was a mistake, please contact our support team. Do not reupload a picture that violates our standards.
                            </p>

                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">
                    <tr>
                        <td align="center" style="padding-top:20px;">
                            <p style="margin:0;font-size:11px;color:#3a3a3a;line-height:1.6;">
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
