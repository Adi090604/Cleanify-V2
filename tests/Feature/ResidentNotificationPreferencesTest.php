<?php

use App\Models\Report;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\ReportRejectedNotification;
use App\Notifications\ReportResolvedNotification;
use App\Notifications\ScheduleCreatedNotification;
use App\Notifications\ScheduleUpdatedNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function preferenceNotification(User $user, string $title, string $category): DatabaseNotification
{
    return DatabaseNotification::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\PreferenceTestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => ['title' => $title, 'category' => $category],
    ]);
}

test('resident settings show supported notification controls without fake options', function () {
    config()->set('sms.driver', 'log');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('settings'));

    $response->assertOk()
        ->assertSee('Email Notifications')
        ->assertSee('SMS Notifications')
        ->assertSee('SMS delivery is currently in simulation mode')
        ->assertSee('Push Notifications')
        ->assertSee('Push Notifications unavailable', false)
        ->assertSee('Browser push notifications are not currently supported')
        ->assertSee('Report Updates')
        ->assertSee('Schedule Reminders')
        ->assertDontSee('name="push_notifications"', false)
        ->assertDontSee('Community Posts')
        ->assertDontSee('Truck Tracking')
        ->assertDontSee('ETA updates and route changes');

    $html = $response->getContent();
    $supportedControls = [
        'email_notifications' => 'email_notifications',
        'sms_notifications' => 'sms_notifications',
        'pref_report_updates' => 'preferences[report_updates]',
        'pref_schedule_reminders' => 'preferences[schedule_reminders]',
    ];

    foreach ($supportedControls as $id => $name) {
        preg_match('/<input(?=[^>]*id="'.preg_quote($id, '/').'"[^>]*>)[^>]*>/', $html, $inputMatch);

        expect($inputMatch)->not->toBeEmpty()
            ->and($inputMatch[0])->not->toContain('disabled', 'aria-disabled')
            ->and($html)->toMatch('/<label[^>]*for="'.preg_quote($id, '/').'"[^>]*cursor-pointer[^>]*>.*?peer-checked:bg-green-600.*?<\/label>/s')
            ->and(substr_count($html, 'name="'.$name.'"'))->toBe(2);
    }

    expect($html)->toMatch('/<input[^>]*aria-label="Push Notifications unavailable"[^>]*disabled[^>]*>/');
});

test('resident can save supported notification preferences and reload their state', function () {
    $user = User::factory()->create([
        'email_notifications' => true,
        'sms_notifications' => true,
        'push_notifications' => true,
        'notification_preferences' => [
            'report_updates' => true,
            'schedule_reminders' => true,
            'system' => false,
        ],
    ]);

    $this->actingAs($user)->patch(route('settings.notifications'), [
        'email_notifications' => false,
        'sms_notifications' => false,
        'preferences' => [
            'report_updates' => false,
            'schedule_reminders' => false,
        ],
    ])->assertRedirect(route('settings'))
        ->assertSessionHas('success', 'Notification preferences updated successfully');

    $user->refresh();
    expect($user->email_notifications)->toBeFalse()
        ->and($user->sms_notifications)->toBeFalse()
        ->and($user->push_notifications)->toBeTrue()
        ->and($user->notification_preferences)->toMatchArray([
            'report_updates' => false,
            'schedule_reminders' => false,
            'system' => false,
        ]);

    $html = $this->get(route('settings'))->assertOk()->getContent();
    expect($html)
        ->not->toMatch('/id="email_notifications"[^>]*checked/')
        ->not->toMatch('/id="sms_notifications"[^>]*checked/')
        ->not->toMatch('/id="pref_report_updates"[^>]*checked/')
        ->not->toMatch('/id="pref_schedule_reminders"[^>]*checked/');

    $this->patch(route('settings.notifications'), [
        'email_notifications' => true,
        'sms_notifications' => true,
        'preferences' => [
            'report_updates' => true,
            'schedule_reminders' => true,
        ],
    ])->assertRedirect(route('settings'));

    $html = $this->get(route('settings'))->assertOk()->getContent();
    expect($html)
        ->toMatch('/id="email_notifications"[^>]*checked/')
        ->toMatch('/id="sms_notifications"[^>]*checked/')
        ->toMatch('/id="pref_report_updates"[^>]*checked/')
        ->toMatch('/id="pref_schedule_reminders"[^>]*checked/');
});

test('notification preference update is scoped to the authenticated resident', function () {
    $resident = User::factory()->create();
    $other = User::factory()->create([
        'email_notifications' => true,
        'sms_notifications' => true,
        'notification_preferences' => ['report_updates' => true, 'schedule_reminders' => true],
    ]);

    $this->actingAs($resident)->patch(route('settings.notifications'), [
        'email_notifications' => false,
        'sms_notifications' => false,
        'preferences' => ['report_updates' => false, 'schedule_reminders' => false],
        'user_id' => $other->id,
    ])->assertRedirect(route('settings'));

    $other->refresh();
    expect($other->email_notifications)->toBeTrue()
        ->and($other->sms_notifications)->toBeTrue()
        ->and($other->notification_preferences)->toBe([
            'report_updates' => true,
            'schedule_reminders' => true,
        ]);
});

test('notification preferences require supported server-side boolean values', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('settings.notifications'), [
        'email_notifications' => 'sometimes',
        'sms_notifications' => false,
        'preferences' => [
            'report_updates' => false,
            'schedule_reminders' => false,
            'truck_tracking' => true,
        ],
    ])->assertSessionHasErrors(['email_notifications', 'preferences']);
});

test('report and schedule notification channels respect supported preferences', function () {
    $reportMuted = User::factory()->create([
        'email_notifications' => true,
        'notification_preferences' => ['report_updates' => false],
    ]);
    $scheduleMuted = User::factory()->create([
        'email_notifications' => true,
        'notification_preferences' => ['schedule_reminders' => false],
    ]);
    $databaseOnly = User::factory()->create([
        'email_notifications' => false,
        'notification_preferences' => ['report_updates' => true, 'schedule_reminders' => true],
    ]);
    $report = new Report;
    $schedule = new Schedule;

    expect((new ReportResolvedNotification($report))->via($reportMuted))->toBe([])
        ->and((new ReportRejectedNotification($report))->via($reportMuted))->toBe([])
        ->and((new ScheduleCreatedNotification($schedule))->via($scheduleMuted))->toBe([])
        ->and((new ScheduleUpdatedNotification($schedule))->via($scheduleMuted))->toBe([])
        ->and((new ReportResolvedNotification($report))->via($databaseOnly))->toBe(['database'])
        ->and((new ScheduleUpdatedNotification($schedule))->via($databaseOnly))->toBe(['database']);
});

test('supported preference keys hide existing muted inbox categories', function () {
    $user = User::factory()->create([
        'notification_preferences' => ['report_updates' => false, 'schedule_reminders' => false],
    ]);
    preferenceNotification($user, 'Muted report update', 'reports');
    preferenceNotification($user, 'Muted schedule reminder', 'schedule');
    preferenceNotification($user, 'Visible system alert', 'system');

    $this->actingAs($user)->get(route('notifications'))
        ->assertOk()
        ->assertDontSee('Muted report update')
        ->assertDontSee('Muted schedule reminder')
        ->assertSee('Visible system alert');

    $this->get(route('notifications', ['include_muted' => 1]))
        ->assertOk()
        ->assertSee('Muted report update')
        ->assertSee('Muted schedule reminder');
});

test('schedule reminder preference suppresses scheduled email and sms delivery', function () {
    Mail::fake();

    $user = User::factory()->create([
        'phone' => '09123456789',
        'service_area' => 'Barangay One',
        'email_notifications' => true,
        'sms_notifications' => true,
        'notification_preferences' => ['schedule_reminders' => false],
    ]);
    $schedule = Schedule::create([
        'area' => 'Barangay One',
        'schedule_type' => 'specific_date',
        'specific_date' => '2026-09-16',
        'days' => null,
        'time_start' => '08:00',
        'time_end' => '09:00',
        'truck' => 'Truck 1',
        'status' => 'active',
    ]);

    $this->artisan('sms:send-schedule-reminders', ['--date' => '2026-09-16'])
        ->assertSuccessful();

    Mail::assertNothingSent();
    $this->assertDatabaseMissing('email_notifications', ['user_id' => $user->id, 'schedule_id' => $schedule->id]);
    $this->assertDatabaseMissing('sms_notifications', ['user_id' => $user->id, 'schedule_id' => $schedule->id]);
});
