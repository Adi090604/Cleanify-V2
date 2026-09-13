<?php

use App\Models\User;

test('a user can log in with valid mobile API credentials', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonMissingPath('user.password');

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
    $this->assertDatabaseCount('personal_access_tokens', 1);
});

test('a user cannot log in with invalid mobile API credentials', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'incorrect-password',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'The provided credentials are incorrect.');

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('a banned user cannot log in through the mobile API', function () {
    $user = User::factory()->create(['banned_at' => now()]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertForbidden()
        ->assertJsonPath('message', 'Your account has been banned. Please contact an administrator.');

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('the mobile API me endpoint requires a Sanctum token', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

test('the mobile API me endpoint returns the authenticated user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile-app');

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonMissingPath('user.password');
});

test('mobile API logout revokes only the current bearer token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile-app');

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logged out successfully.');

    expect($token->accessToken->fresh())->toBeNull();

    // Reset the test application's cached guard before simulating a new request.
    $this->app['auth']->forgetGuards();

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});
