<?php

use App\Exceptions\GmailDeliveryException;
use App\Models\User;
use App\Notifications\GmailResetPasswordNotification;
use App\Services\GmailApiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function configureGmailPasswordReset(): void
{
    config()->set('services.gmail', [
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
        'refresh_token' => 'test-refresh-token',
        'sender_email' => 'cleanify.sender@example.test',
    ]);
}

function decodeGmailRaw(string $raw): string
{
    $encoded = strtr($raw, '-_', '+/');
    $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);

    return base64_decode($encoded, true) ?: '';
}

test('forgot password sends a MIME reset email through Gmail API without changing the password broker', function () {
    configureGmailPasswordReset();
    $user = User::factory()->create(['email' => 'resident@example.test']);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'access-token-one',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
        'https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response([
            'id' => 'gmail-message-id',
        ]),
    ]);

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('status', __('passwords.sent'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['client_id'] === 'test-client-id'
            && $request['client_secret'] === 'test-client-secret'
            && $request['refresh_token'] === 'test-refresh-token'
            && $request['grant_type'] === 'refresh_token';
    });

    Http::assertSent(function (Request $request) use ($user): bool {
        if ($request->url() !== 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send'
            || ! $request->hasHeader('Authorization', 'Bearer access-token-one')) {
            return false;
        }

        $mime = decodeGmailRaw((string) $request['raw']);

        return str_contains($mime, 'From: cleanify.sender@example.test')
            && str_contains($mime, 'To: '.$user->email)
            && str_contains($mime, 'Subject: Reset Your Cleanify Password')
            && str_contains($mime, 'Content-Type: multipart/alternative')
            && str_contains($mime, '/reset-password/');
    });
});

test('Gmail access token is refreshed and delivery retried once after an unauthorized response', function () {
    configureGmailPasswordReset();
    $tokenAttempts = 0;
    $sendAttempts = 0;

    Http::fake(function (Request $request) use (&$tokenAttempts, &$sendAttempts) {
        if ($request->url() === 'https://oauth2.googleapis.com/token') {
            $tokenAttempts++;

            return Http::response(['access_token' => 'access-token-'.$tokenAttempts, 'expires_in' => 3600]);
        }

        $sendAttempts++;

        return $sendAttempts === 1
            ? Http::response(['error' => ['message' => 'Unauthorized']], 401)
            : Http::response(['id' => 'gmail-message-id']);
    });

    app(GmailApiService::class)->send(
        'resident@example.test',
        'Reset Your Cleanify Password',
        '<p>Reset your password.</p>',
        'Reset your password.'
    );

    expect($tokenAttempts)->toBe(2)
        ->and($sendAttempts)->toBe(2);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send'
        && $request->hasHeader('Authorization', 'Bearer access-token-2')
    );
});

test('invalid or revoked refresh tokens fail safely without calling Gmail send', function () {
    configureGmailPasswordReset();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'error' => 'invalid_grant',
            'error_description' => 'Token has been expired or revoked.',
        ], 400),
    ]);

    expect(fn () => app(GmailApiService::class)->send(
        'resident@example.test',
        'Reset Your Cleanify Password',
        '<p>Reset your password.</p>',
        'Reset your password.'
    ))->toThrow(GmailDeliveryException::class, 'Gmail authorization failed.');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/messages/send'));
});

test('Gmail API errors do not report password reset delivery success and remove the unsent token', function () {
    configureGmailPasswordReset();
    $user = User::factory()->create();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token-one']),
        'https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response([
            'error' => ['message' => 'Backend error'],
        ], 503),
    ]);

    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect(route('password.request'))
        ->assertSessionMissing('status')
        ->assertSessionHasErrors([
            'email' => 'We could not send the password reset email. Please try again later.',
        ]);

    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
});

test('mobile forgot password reports Gmail delivery failure without claiming a reset link was sent', function () {
    configureGmailPasswordReset();
    $user = User::factory()->create();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'We could not send the password reset email. Please try again later.')
        ->assertJsonMissing(['message' => __('passwords.sent')]);

    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
});

test('the user reset notification remains compatible with Laravel reset URL generation', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, GmailResetPasswordNotification::class, function ($notification) use ($user) {
        $resetUrl = $notification->toMail($user)->actionUrl;

        expect($notification->via($user))->toBe([App\Notifications\Channels\GmailApiChannel::class])
            ->and($resetUrl)->toStartWith(config('app.url'))
            ->toContain('/reset-password/'.$notification->token)
            ->toContain('email='.urlencode($user->email));

        return true;
    });
});
