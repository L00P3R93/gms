<?php

use App\Services\GameApiService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.game_api.url' => 'https://game-api.test',
        'services.game_api.key' => 'test-api-key',
    ]);
});

it('sends a unique random idempotency key by default on non-GET requests', function () {
    Http::fake(['*' => Http::response(['data' => []], 200)]);

    $service = app(GameApiService::class);
    $service->createCustomer(['account_no' => null, 'name' => 'A', 'email' => 'a@example.com']);
    $service->getLeaderboard('2026-01-01', '2026-01-31');

    $keys = [];
    Http::assertSentCount(2);
    Http::assertSent(function ($request) use (&$keys) {
        $keys[] = $request->header('Idempotency-Key')[0] ?? null;

        return true;
    });

    expect($keys)->each->not->toBeNull();
    expect($keys[0])->not->toBe($keys[1]);
});

it('honors an explicitly passed idempotency key', function () {
    Http::fake(['*' => Http::response(['data' => []], 200)]);

    $service = app(GameApiService::class);
    $service->getLeaderboard('2026-01-01', '2026-01-31', 'my-explicit-key');

    Http::assertSent(fn ($request) => $request->header('Idempotency-Key')[0] === 'my-explicit-key');
});

it('sends the same deterministic idempotency key for createCustomer with the same account_no', function () {
    Http::fake(['*' => Http::response(['data' => []], 200)]);

    $service = app(GameApiService::class);
    $data = ['account_no' => 'ACC-123', 'name' => 'A', 'email' => 'a@example.com'];

    $service->createCustomer($data);
    $service->createCustomer($data);

    $keys = [];
    Http::assertSent(function ($request) use (&$keys) {
        $keys[] = $request->header('Idempotency-Key')[0] ?? null;

        return true;
    });

    expect($keys)->toHaveCount(2);
    expect($keys[0])->toBe($keys[1])
        ->and($keys[0])->toBe('customer-create-ACC-123');
});

it('does not send an idempotency key header on GET requests', function () {
    Http::fake(['*' => Http::response(['data' => []], 200)]);

    $service = app(GameApiService::class);
    $service->listCustomers();

    Http::assertSent(fn ($request) => $request->method() === 'GET' && ! $request->hasHeader('Idempotency-Key'));
});
