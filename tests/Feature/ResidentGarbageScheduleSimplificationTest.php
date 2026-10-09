<?php

use App\Models\Schedule;
use App\Models\ServiceZone;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 08:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('resident garbage schedule keeps core schedule tools without secondary controls', function () {
    $zone = ServiceZone::create([
        'name' => 'Zone 1',
        'barangay' => 'Barangay Washington',
        'status' => 'active',
    ]);
    $user = User::factory()->create(['service_area' => $zone->display_name]);

    Schedule::create([
        'area' => $zone->display_name,
        'schedule_type' => 'specific_date',
        'specific_date' => '2026-10-12',
        'days' => null,
        'time_start' => '06:00',
        'time_end' => '09:00',
        'truck' => 'TRK-01',
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->get(route('garbage-schedule'));

    $response->assertOk()
        ->assertSee('Your zone this week')
        ->assertSee($zone->display_name)
        ->assertSee('October')
        ->assertSee('6:00 AM')
        ->assertSee('Truck TRK-01')
        ->assertSee('Upcoming pickups')
        ->assertSee('Filter by zone')
        ->assertSee('Search zone, truck, or day...')
        ->assertSee('id="calendar"', false)
        ->assertSee('id="prevMonth"', false)
        ->assertSee('id="nextMonth"', false)
        ->assertSee('Zone schedule directory')
        ->assertSee('applyScheduleFilters', false)
        ->assertSee("areaFilter.addEventListener('change'", false)
        ->assertSee("scheduleSearch.addEventListener('input'", false)
        ->assertDontSee('addToCalendarBtn', false)
        ->assertDontSee('reminder-toggle', false)
        ->assertDontSee('Reminder preferences')
        ->assertDontSee('Email reminders')
        ->assertDontSee('SMS reminders')
        ->assertDontSee('Push notifications')
        ->assertDontSee('printScheduleBtn', false)
        ->assertDontSee('downloadIcsBtn', false)
        ->assertDontSee('shareScheduleBtn', false)
        ->assertDontSee('type="checkbox"', false)
        ->assertDontSee('BEGIN:VCALENDAR', false)
        ->assertDontSee('window.print()', false)
        ->assertDontSee('navigator.share', false);
});

test('schedule service area and notification preference backends remain available', function () {
    $user = User::factory()->create([
        'service_area' => null,
        'email_notifications' => false,
        'sms_notifications' => false,
        'push_notifications' => false,
    ]);

    $this->actingAs($user)
        ->postJson(route('garbage-schedule.service-area'), ['service_area' => 'Zone 2'])
        ->assertOk()
        ->assertJsonPath('service_area', 'Zone 2');

    $this->postJson(route('garbage-schedule.notifications'), [
        'email_notifications' => true,
        'sms_notifications' => true,
        'push_notifications' => false,
    ])->assertOk();

    expect($user->fresh())
        ->service_area->toBe('Zone 2')
        ->email_notifications->toBeTrue()
        ->sms_notifications->toBeTrue()
        ->push_notifications->toBeFalse();
});

test('notification preferences remain on resident settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings'))
        ->assertOk()
        ->assertSee('Notification Preferences')
        ->assertSee('name="email_notifications"', false)
        ->assertSee('name="sms_notifications"', false)
        ->assertSee('name="preferences[report_updates]"', false)
        ->assertSee('name="preferences[schedule_reminders]"', false);
});

test('schedule reminder reference reflects the resident saved preferences', function (
    bool $scheduleReminders,
    bool $emailNotifications,
    string $expectedMessage,
    ?string $secondaryMessage,
) {
    $user = User::factory()->create([
        'email_notifications' => $emailNotifications,
        'notification_preferences' => ['schedule_reminders' => $scheduleReminders],
    ]);

    $response = $this->actingAs($user)
        ->get(route('garbage-schedule'))
        ->assertOk()
        ->assertSee($expectedMessage, false)
        ->assertSee('href="'.route('settings').'"', false);

    if ($secondaryMessage) {
        $response->assertSee($secondaryMessage);
    }

    if (! $scheduleReminders || ! $emailNotifications) {
        $response->assertDontSee("We'll remind you by email 1 day before your scheduled collection.");
    }
})->with([
    'reminders and email enabled' => [
        true,
        true,
        "We'll remind you by email 1 day before your scheduled collection.",
        'Manage reminder options in Settings',
    ],
    'schedule reminders disabled' => [
        false,
        true,
        'Schedule reminders are currently turned off.',
        'Manage reminder options in Settings',
    ],
    'reminders enabled but email disabled' => [
        true,
        false,
        'Schedule reminders are enabled.',
        'Manage your reminder options in Settings.',
    ],
]);

test('scheduled reminder command targets collections one day ahead by default', function () {
    Mail::fake();

    $user = User::factory()->create([
        'service_area' => 'Zone 1',
        'email_notifications' => true,
        'sms_notifications' => false,
        'notification_preferences' => ['schedule_reminders' => true],
    ]);
    $schedule = Schedule::create([
        'area' => 'Zone 1',
        'schedule_type' => 'specific_date',
        'specific_date' => '2026-10-11',
        'days' => null,
        'time_start' => '06:00',
        'time_end' => '09:00',
        'truck' => 'TRK-01',
        'status' => 'active',
    ]);

    $this->artisan('sms:send-schedule-reminders')->assertSuccessful();

    $this->assertDatabaseHas('email_notifications', [
        'user_id' => $user->id,
        'schedule_id' => $schedule->id,
        'collection_date' => '2026-10-11 00:00:00',
        'reminder_type' => 'one_day_before',
    ]);
});
