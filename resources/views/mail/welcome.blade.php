<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Welcome to {{ $appName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#0a0a0a;font-family:'Georgia',serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0a0a0a;padding:40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

                    {{-- Header --}}
                    <tr>
                        <td align="center" style="padding:32px 40px 24px;background-color:#111111;border-radius:16px 16px 0 0;border-top:2px solid #f5c542;border-left:1px solid rgba(245,197,66,0.2);border-right:1px solid rgba(245,197,66,0.2);">
                            <h1 style="margin:0;font-size:28px;font-weight:900;letter-spacing:0.15em;color:#f5c542;">♠ {{ strtoupper($appName) }}</h1>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:40px;background-color:#111111;border-left:1px solid rgba(245,197,66,0.2);border-right:1px solid rgba(245,197,66,0.2);">

                            {{-- Greeting --}}
                            <h2 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#f5f5f0;letter-spacing:0.05em;">Welcome to {{ strtoupper($appName) }}!</h2>
                            <p style="margin:0 0 28px;font-size:15px;color:#6b6b6b;line-height:1.6;font-family:Arial,sans-serif;">
                                Your account is ready. Your luck starts now — log in and play to win big.
                            </p>

                            {{-- Divider --}}
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
                                <tr>
                                    <td style="border-top:1px solid rgba(245,197,66,0.2);"></td>
                                </tr>
                            </table>

                            {{-- Account details --}}
                            <h3 style="margin:0 0 16px;font-size:12px;font-weight:700;letter-spacing:0.2em;text-transform:uppercase;color:rgba(245,197,66,0.7);font-family:Arial,sans-serif;">Your Account Details</h3>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:32px;border-radius:10px;overflow:hidden;border:1px solid rgba(245,197,66,0.15);">
                                <tr style="background-color:#1a1a1a;">
                                    <td style="padding:12px 16px;font-size:12px;color:#6b6b6b;font-family:Arial,sans-serif;width:40%;border-bottom:1px solid rgba(245,197,66,0.08);">Name</td>
                                    <td style="padding:12px 16px;font-size:13px;color:#f5f5f0;font-family:Arial,sans-serif;border-bottom:1px solid rgba(245,197,66,0.08);">{{ $user->name }}</td>
                                </tr>
                                <tr style="background-color:#111111;">
                                    <td style="padding:12px 16px;font-size:12px;color:#6b6b6b;font-family:Arial,sans-serif;border-bottom:1px solid rgba(245,197,66,0.08);">Email</td>
                                    <td style="padding:12px 16px;font-size:13px;color:#f5f5f0;font-family:Arial,sans-serif;border-bottom:1px solid rgba(245,197,66,0.08);">{{ $user->email }}</td>
                                </tr>
                                <tr style="background-color:#1a1a1a;">
                                    <td style="padding:12px 16px;font-size:12px;color:#6b6b6b;font-family:Arial,sans-serif;border-bottom:1px solid rgba(245,197,66,0.08);">Password</td>
                                    <td style="padding:12px 16px;font-size:13px;color:#f5c542;font-family:'Courier New',monospace;border-bottom:1px solid rgba(245,197,66,0.08);">{{ '••••••••' }}</td>
                                </tr>
                                {{--

                                <tr style="background-color:#111111;">
                                    <td style="padding:12px 16px;font-size:12px;color:#6b6b6b;font-family:Arial,sans-serif;">Account No</td>
                                    <td style="padding:12px 16px;font-size:13px;color:#f5c542;font-family:'Courier New',monospace;letter-spacing:0.05em;">{{ $user->account_no }}</td>
                                </tr>
                                --}}}
                            </table>

                            {{-- CTA Button --}}
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:32px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $appUrl }}/login"
                                           style="display:inline-block;padding:16px 48px;background:linear-gradient(135deg,#f5c542 0%,#ffde74 50%,#f5c542 100%);color:#0a0a0a;font-size:13px;font-weight:700;letter-spacing:0.15em;text-transform:uppercase;text-decoration:none;border-radius:50px;font-family:Arial,sans-serif;">
                                            PLAY NOW
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            {{-- Divider --}}
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                <tr>
                                    <td style="border-top:1px solid rgba(245,197,66,0.2);"></td>
                                </tr>
                            </table>

                            {{-- Security note --}}
                            <p style="margin:0;font-size:12px;color:#6b6b6b;line-height:1.6;font-family:Arial,sans-serif;">
                                For your security, please change your password after your first login. If you did not create this account, please ignore this email.
                            </p>

                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding:20px 40px;background-color:#0a0a0a;border-radius:0 0 16px 16px;border-bottom:1px solid rgba(245,197,66,0.15);border-left:1px solid rgba(245,197,66,0.2);border-right:1px solid rgba(245,197,66,0.2);">
                            <p style="margin:0 0 6px;font-size:11px;color:rgba(245,197,66,0.5);letter-spacing:0.2em;text-transform:uppercase;font-family:Arial,sans-serif;">♠ {{ strtoupper($appName) }}</p>
                            <p style="margin:0;font-size:11px;color:#3a3a3a;font-family:Arial,sans-serif;">&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
