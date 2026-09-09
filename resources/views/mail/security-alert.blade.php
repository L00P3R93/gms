<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Alert — {{ $appName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#0a0a0a;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#0a0a0a;">
        <tr>
            <td align="center" style="padding:40px 16px;">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">
                    <tr>
                        <td align="center" style="padding-bottom:24px;">
                            <span style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:rgba(245,197,66,0.6);">
                                {{ $appName }} · Security
                            </span>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;background-color:#1a1a1a;border:1px solid rgba(245,197,66,0.3);border-radius:12px;">
                    <tr>
                        <td style="padding:32px;">

                            <h1 style="margin:0 0 16px;font-size:22px;font-weight:700;color:#f5f5f0;">
                                {{ __('Security alert') }}
                            </h1>

                            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#e5e5e5;">
                                {{ __('Hi :name,', ['name' => $user->name]) }}
                            </p>

                            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#e5e5e5;">
                                {{ __(':change on :when.', ['change' => $change, 'when' => $when]) }}
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
                                <tr><td style="height:1px;background-color:rgba(245,197,66,0.2);font-size:0;line-height:0;">&nbsp;</td></tr>
                            </table>

                            <p style="margin:0;font-size:14px;line-height:1.6;color:#6b6b6b;">
                                {{ __("If this was you, no action is needed. If you don't recognize this change, please sign in to your account, secure it immediately, and contact support.") }}
                            </p>

                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">
                    <tr>
                        <td align="center" style="padding-top:20px;">
                            <p style="margin:0;font-size:11px;color:#3a3a3a;line-height:1.6;">
                                {{ __('You are receiving this email because security settings changed for your account.') }}<br>
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
