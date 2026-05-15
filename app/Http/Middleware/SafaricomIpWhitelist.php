<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SafaricomIpWhitelist
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    /**
     * Safaricom production IP ranges for B2C callbacks.
     * Source: https://developer.safaricom.co.ke/docs#ip-addresses
     */
    private const ALLOWED_IPS = [
        '196.201.214.200',
        '196.201.214.206',
        '196.201.213.100',
        '196.201.214.207',
        '196.201.214.208',
        '196.201.213.109',
        '196.201.214.214',
        '196.201.214.216',
        '196.201.214.217',
        '196.201.214.218',
        '196.201.214.219',
        '196.201.214.220',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction() && ! in_array($request->ip(), self::ALLOWED_IPS, strict: true)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
