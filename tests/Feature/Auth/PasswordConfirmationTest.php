<?php

use App\Models\User;

beforeEach(function (): void {
    $this->skipIfFortifyRoutesIgnored();
});

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertOk();
});
