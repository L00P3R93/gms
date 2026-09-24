<?php

namespace App\Support;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

/**
 * Builds what the player app normally generates for a referral code: the
 * upper-case code, the signup link and a PNG QR code of that link as a data
 * URI. Used when an admin changes a code from the GMS.
 */
class ReferralCode
{
    public const PATTERN = '/^[A-Za-z0-9]{4,20}$/';

    public const QR_SIZE = 400;

    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    public static function link(string $code): string
    {
        return str_replace('{code}', rawurlencode(static::normalize($code)), (string) config('services.game_api.referral_link'));
    }

    /**
     * The link as a `data:image/png;base64,…` QR code.
     */
    public static function qrCodeDataUri(string $link): string
    {
        $png = (new Writer(new GDLibRenderer(self::QR_SIZE)))->writeString($link);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
