<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('an existing user can request the web password reset link through the mobile API', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => $user->email,
    ])->assertOk()
        ->assertJsonPath('message', __('passwords.sent'));

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $resetUrl = $notification->toMail($user)->actionUrl;

        expect($resetUrl)
            ->toContain('/reset-password/'.$notification->token)
            ->toContain('email='.urlencode($user->email));

        return true;
    });
});

test('the mobile password reset request requires an email', function () {
    $this->postJson('/api/v1/auth/forgot-password', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('the mobile password reset request requires a valid email', function () {
    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'not-an-email',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('the mobile password reset request preserves the existing unknown user response', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'missing@example.com',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email')
        ->assertJsonPath('errors.email.0', __('passwords.user'));

    Notification::assertNothingSent();
});
