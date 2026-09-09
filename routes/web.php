<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/email/preview/{type}', function (string $type) {
    $user = auth()->user() ?? User::first();

    return match ($type) {
        'verify' => view('mail.verify-email', [
            'user' => (object) ['name' => $user->name],
            'verificationUrl' => '#',
            'appName' => config('app.name'),
        ]),
        'welcome' => view('mail.welcome', [
            'user' => $user,
            'appName' => config('app.name'),
            'appUrl' => config('app.url'),
        ]),
        'security' => view('mail.security-alert', [
            'user' => $user,
            'change' => 'Email address changed',
            'when' => now()->format('j M Y, H:i T'),
            'appName' => config('app.name'),
            'appUrl' => config('app.url'),
        ]),
        default => abort(404),
    };
});
