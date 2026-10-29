<?php

use App\Models\Schedule;
use App\Models\SmsNotification;
use App\Models\User;
use App\Services\SmsNotificationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function configureTextBee(string $apiKey = 'textbee-test-key'): void
{
    config([
        'sms.driver' => 'textbee',
        'sms.textbee.api_key' => $apiKey,
        'sms.textbee.base_url' => 'https://api.textbee.dev/api/v1',
    ]);
}

function textBeeReminderSchedule(): Schedule
{
    return Schedule::query()->create([
        'area' => 'Barangay One',
        'schedule_type' => 'specific_date',
        'specific_date' => '2026-11-01',
        'days' => null,
        'time_start' => '08:00',
        'time_end' => '09:00',
        'truck' => 'Truck 1',
        'status' => 'active',
    ]);
}

test('TextBee sends the normalized recipient and message with server-side API authentication', function (string $phone) {
    configureTextBee();
    Http::fake([
        'https://api.textbee.dev/api/v1/gateway/send-sms' => Http::response([
            'data' => [
                'success' => true,
                'smsBatchId' => 'batch-123',
            ],
        ]),
    ]);

    expect(app(SmsNotificationService::class)->send($phone, 'Cleanify reminder'))->toBeTrue();

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.textbee.dev/api/v1/gateway/send-sms'
            && $request->hasHeader('x-api-key', 'textbee-test-key')
            && $request->data() === [
                'recipients' => ['+639171234567'],
                'message' => 'Cleanify reminder',
            ]
            && ! isset($request['deviceId'], $request['simSubscriptionId'], $request['scheduledAt']);
    });
})->with([
    'Philippine local format' => '09171234567',
    'already normalized format' => '+639171234567',
]);

test('missing TextBee configuration fails before making an HTTP request', function () {
    configureTextBee('');
    Http::fake();

    expect(fn () => app(SmsNotificationService::class)->send('09171234567', 'Cleanify reminder'))
        ->toThrow(RuntimeException::class, 'TextBee SMS configuration is incomplete.');

    Http::assertNothingSent();
});

test('TextBee provider errors fail with safe status-specific messages', function (int $status, string $message) {
    configureTextBee();
    Http::fake([
        '*' => Http::response(['error' => 'Provider rejected the request'], $status),
    ]);

    expect(fn () => app(SmsNotificationService::class)->send('09171234567', 'Cleanify reminder'))
        ->toThrow(RuntimeException::class, $message);
})->with([
    'bad request' => [400, 'TextBee rejected the SMS request (HTTP 400).'],
    'missing gateway device' => [404, 'The TextBee gateway device was not found (HTTP 404).'],
    'plan limit' => [429, 'The TextBee SMS plan or rate limit was reached (HTTP 429).'],
    'provider outage' => [503, 'TextBee is temporarily unavailable (HTTP 503).'],
]);

test('TextBee connection failures fail safely without escaping HTTP client details', function () {
    configureTextBee();
    Http::fake(Http::failedConnection('simulated timeout'));

    expect(fn () => app(SmsNotificationService::class)->send('09171234567', 'Cleanify reminder'))
        ->toThrow(RuntimeException::class, 'The TextBee SMS provider could not be reached.');
});

test('TextBee authentication failures do not expose the API key and do not crash the scheduler', function () {
    $apiKey = 'never-log-this-textbee-key';
    configureTextBee($apiKey);
    Log::spy();
    Http::fake([
        '*' => Http::response(['error' => "Invalid API key: {$apiKey}"], 401),
    ]);
    $user = User::factory()->create([
        'phone' => '09171234567',
        'service_area' => 'Barangay One',
        'email_notifications' => false,
        'sms_notifications' => true,
        'notification_preferences' => ['schedule_reminders' => true],
    ]);
    $schedule = textBeeReminderSchedule();

    $this->artisan('sms:send-schedule-reminders', ['--date' => '2026-11-01'])
        ->assertSuccessful();

    $notification = SmsNotification::query()->sole();
    expect($notification->user_id)->toBe($user->id)
        ->and($notification->schedule_id)->toBe($schedule->id)
        ->and($notification->status)->toBe('failed')
        ->and($notification->error_message)->toBe('TextBee authentication failed (HTTP 401).')
        ->and($notification->error_message)->not->toContain($apiKey);

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context): bool => ! str_contains($message.json_encode($context), $apiKey)
    );
    Log::shouldHaveReceived('error')->once()->withArgs(
        fn (string $message, array $context): bool => ! str_contains($message.json_encode($context), $apiKey)
    );
});

test('SMS preferences prevent TextBee requests', function () {
    configureTextBee();
    Http::fake();
    User::factory()->create([
        'phone' => '09171234567',
        'service_area' => 'Barangay One',
        'email_notifications' => false,
        'sms_notifications' => false,
        'notification_preferences' => ['schedule_reminders' => true],
    ]);
    textBeeReminderSchedule();

    $this->artisan('sms:send-schedule-reminders', ['--date' => '2026-11-01'])
        ->assertSuccessful();

    Http::assertNothingSent();
    expect(SmsNotification::query()->count())->toBe(0);
});

test('successful TextBee schedule reminders remain deduplicated', function () {
    configureTextBee();
    Http::fake([
        '*' => Http::response(['data' => ['success' => true, 'smsBatchId' => 'batch-123']]),
    ]);
    $user = User::factory()->create([
        'phone' => '09171234567',
        'service_area' => 'Barangay One',
        'email_notifications' => false,
        'sms_notifications' => true,
        'notification_preferences' => ['schedule_reminders' => true],
    ]);
    $schedule = textBeeReminderSchedule();

    $this->artisan('sms:send-schedule-reminders', ['--date' => '2026-11-01'])->assertSuccessful();
    $this->artisan('sms:send-schedule-reminders', ['--date' => '2026-11-01'])->assertSuccessful();

    Http::assertSentCount(1);
    expect(SmsNotification::query()->count())->toBe(1);
    $this->assertDatabaseHas('sms_notifications', [
        'user_id' => $user->id,
        'schedule_id' => $schedule->id,
        'status' => 'sent',
    ]);
});

test('existing Twilio and log drivers remain functional', function () {
    config([
        'sms.driver' => 'twilio',
        'sms.twilio.account_sid' => 'AC-test',
        'sms.twilio.auth_token' => 'twilio-test-token',
        'sms.twilio.from' => '+15551234567',
    ]);
    Http::fake(['*' => Http::response([], 201)]);
    $sms = app(SmsNotificationService::class);

    expect($sms->send('09171234567', 'Twilio reminder'))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC-test/Messages.json'
        && $request['To'] === '+639171234567'
        && $request['From'] === '+15551234567'
        && $request['Body'] === 'Twilio reminder');

    Http::fake();
    config(['sms.driver' => 'log']);
    expect($sms->send('09171234567', 'Simulated reminder'))->toBeFalse();
    Http::assertNothingSent();
});

test('clearly invalid recipients are rejected before any provider request', function () {
    configureTextBee();
    Http::fake();

    expect(fn () => app(SmsNotificationService::class)->send('not-a-phone', 'Cleanify reminder'))
        ->toThrow(RuntimeException::class, 'The SMS recipient phone number is invalid.');

    Http::assertNothingSent();
});
