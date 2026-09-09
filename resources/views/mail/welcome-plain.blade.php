{{ config('app.name') }}

Hi {{ $user->name }},

Welcome to {{ config('app.name') }} — your account is set up and ready to go.


PLAY NOW:
"{{ $appUrl }}/login"

---
You're receiving this email because you signed up for {{ config('app.name') }}.

© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
