<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

test('a guest can register with the exact web registration fields and receive a mobile token', function () {
    Event::fake([Registered::class]);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Mobile User',
        'email' => 'mobile@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertCreated()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.name', 'Mobile User')
        ->assertJsonPath('user.email', 'mobile@example.com')
        ->assertJsonPath('user.is_admin', false)
        ->assertJsonMissingPath('user.password')
        ->assertJsonMissingPath('user.banned_at')
        ->assertJsonMissingPath('user.remember_token');

    $user = User::where('email', 'mobile@example.com')->firstOrFail();
    expect(Hash::check('password', $user->password))->toBeTrue()
        ->and($user->is_admin)->toBeFalse()
        ->and($user->service_area)->toBeNull();
    Event::assertDispatched(Registered::class, fn ($event) => $event->user->is($user));

    $this->withToken($response->json('token'))->getJson('/api/v1/me')
        ->assertOk()->assertJsonPath('user.id', $user->id);
});

test('registration enforces required fields and valid email', function () {
    $this->postJson('/api/v1/auth/register', [])
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Mobile User',
        'email' => 'not-an-email',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('registration rejects duplicate email and non-lowercase email exactly like web', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Mobile User',
        'email' => 'existing@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Mobile User',
        'email' => 'UPPER@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('registration enforces the shared password rule and confirmation', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Mobile User',
        'email' => 'mobile@example.com',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
});
