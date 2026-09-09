{{ $appName }} — Profile Picture Removed

Hi {{ $name }},

Your profile picture has been removed by our moderation team because it did not meet our community standards.

@if($autoBanned)
ACCOUNT SUSPENDED — Strike {{ $strikeCount }} of 3

Your account has been suspended due to repeated violations. Please contact support to appeal.
@else
Strike {{ $strikeCount }} of 3

Repeated violations may result in your account being suspended. Please ensure your profile picture complies with our community standards.
@endif

If you believe this was a mistake, please contact our support team.

© {{ date('Y') }} {{ $appName }}. All rights reserved.
