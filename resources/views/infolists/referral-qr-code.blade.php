@php($source = $getState())

@if ($source)
    <img src="{{ $source }}" alt="Referral QR code" style="width: 10rem; height: 10rem; image-rendering: pixelated; background: #fff; padding: 0.5rem; border-radius: 0.5rem;" />
@else
    <span class="fi-in-placeholder">—</span>
@endif
