<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

function validMobileRegistration(array $overrides = []): array
{
    return array_merge([
        'name' => 'Mobile User',
        'email' => 'mobile@example.com',
        'phone' => '09171234567',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $overrides);
}

test('a guest can register with the shared registration fields and receive a mobile token', function () {
    Event::fake([Registered::class]);

    $response = $this->postJson('/api/v1/auth/register', validMobileRegistration())
        ->assertCreated()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.name', 'Mobile User')
        ->assertJsonPath('user.email', 'mobile@example.com')
        ->assertJsonPath('user.is_admin', false)
        ->assertJsonMissingPath('user.password')
        ->assertJsonMissingPath('user.banned_at')
        ->assertJsonMissingPath('user.remember_token');

    $user = User::where('email', 'mobile@example.com')->firstOrFail();
    expect(Hash::check('password', $user->password))->toBeTrue()
        ->and($user->phone)->toBe('+639171234567')
        ->and($user->is_admin)->toBeFalse()
        ->and($user->service_area)->toBeNull();
    Event::assertDispatched(Registered::class, fn ($event) => $event->user->is($user));

    $this->withToken($response->json('token'))->getJson('/api/v1/me')
        ->assertOk()->assertJsonPath('user.id', $user->id);

    $this->withToken($response->json('token'))->getJson('/api/v1/settings')
        ->assertOk()->assertJsonPath('account.phone', '+639171234567');
});

test('registration enforces required fields and valid email', function () {
    $this->postJson('/api/v1/auth/register', [])
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'phone', 'password']);

    $this->postJson('/api/v1/auth/register', validMobileRegistration([
        'email' => 'not-an-email',
    ]))->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('registration rejects duplicate email and non-lowercase email exactly like web', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->postJson('/api/v1/auth/register', validMobileRegistration([
        'email' => 'existing@example.com',
    ]))->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson('/api/v1/auth/register', validMobileRegistration([
        'email' => 'UPPER@example.com',
    ]))->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('registration enforces the shared password rule and confirmation', function () {
    $this->postJson('/api/v1/auth/register', validMobileRegistration([
        'password' => 'short',
        'password_confirmation' => 'different',
    ]))->assertUnprocessable()->assertJsonValidationErrors('password');
});

test('api registration accepts and preserves the international Philippine format', function () {
    $this->postJson('/api/v1/auth/register', validMobileRegistration([
        'email' => 'international@example.com',
        'phone' => ' +639181234567 ',
    ]))->assertCreated();

    expect(User::where('email', 'international@example.com')->value('phone'))
        ->toBe('+639181234567');
});

test('api registration rejects invalid Philippine mobile numbers', function (?string $phone) {
    $payload = validMobileRegistration([
        'email' => 'invalid-phone@example.com',
        'phone' => $phone,
    ]);

    if ($phone === null) {
        unset($payload['phone']);
    }

    $this->postJson('/api/v1/auth/register', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
})->with([
    'missing' => null,
    'without Philippine prefix' => '9171234567',
    'country code without plus' => '639171234567',
    'too short' => '0917123456',
    'too long' => '091712345678',
    'invalid characters' => '0917-123-4567',
    'invalid mobile prefix' => '08171234567',
]);

test('existing users retain their saved phone format and remain usable', function () {
    $user = User::factory()->create(['phone' => '09181234567']);

    expect($user->fresh()->phone)->toBe('09181234567');

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()->assertJsonPath('token_type', 'Bearer');

    expect($user->fresh()->phone)->toBe('09181234567');
});
