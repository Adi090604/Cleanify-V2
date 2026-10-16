<?php

use App\Mail\GarbageCollectionReminder;
use App\Models\EmailNotification;
use App\Models\Schedule;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\ScheduleReminderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

function intervalSchedule(array $overrides = []): Schedule
{
    return Schedule::query()->create(array_merge([
        'area' => 'Purok 2 - Capitol',
        'schedule_type' => 'recurring',
        'specific_date' => null,
        'recurrence_start_date' => '2026-10-16',
        'recurrence_interval_days' => 3,
        'days' => null,
        'time_start' => '06:00',
        'time_end' => '09:00',
        'truck' => 'TRK-03',
        'status' => 'active',
    ], $overrides));
}

function adminIntervalPayload(array $overrides = []): array
{
    return array_merge([
        'area' => 'Purok 2 - Capitol',
        'schedule_type' => 'recurring',
        'recurrence_mode' => 'interval',
        'recurrence_start_date' => '2026-10-16',
        'recurrence_interval_days' => 3,
        'days' => null,
        'specific_date' => null,
        'time_start' => '06:00',
        'time_end' => '09:00',
        'truck' => 'TRK-03',
        'status' => 'active',
    ], $overrides);
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-16 05:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('every three days produces the expected dates across a month boundary', function () {
    $service = app(ScheduleReminderService::class);
    $schedule = intervalSchedule();
    $occurrences = $service
        ->nextOccurrences($schedule, Carbon::parse('2026-10-16 00:00:00'), 7)
        ->map->format('Y-m-d')
        ->all();

    expect($service->occursOn($schedule, Carbon::parse('2026-10-16')))->toBeTrue()
        ->and($occurrences)->toBe([
            '2026-10-16', '2026-10-19', '2026-10-22', '2026-10-25',
            '2026-10-28', '2026-10-31', '2026-11-03',
        ]);

    $yearBoundary = intervalSchedule(['recurrence_start_date' => '2026-12-29']);
    expect(app(ScheduleReminderService::class)
        ->nextOccurrences($yearBoundary, Carbon::parse('2026-12-29 00:00:00'), 3)
        ->map->format('Y-m-d')->all())
        ->toBe(['2026-12-29', '2027-01-01', '2027-01-04']);
});

test('daily and weekly intervals include their selected start date', function (int $interval, array $expected) {
    $dates = app(ScheduleReminderService::class)
        ->nextOccurrences(intervalSchedule(['recurrence_interval_days' => $interval]), Carbon::parse('2026-10-16 00:00:00'), count($expected))
        ->map->format('Y-m-d')
        ->all();

    expect($dates)->toBe($expected);
})->with([
    'every day' => [1, ['2026-10-16', '2026-10-17', '2026-10-18', '2026-10-19']],
    'every seven days' => [7, ['2026-10-16', '2026-10-23', '2026-10-30', '2026-11-06']],
]);

test('future recurrence starts on the selected date and never earlier', function () {
    $service = app(ScheduleReminderService::class);
    $schedule = intervalSchedule(['recurrence_start_date' => '2026-10-20']);

    expect($service->occursOn($schedule, Carbon::parse('2026-10-17')))->toBeFalse()
        ->and($service->nextOccurrences($schedule, Carbon::parse('2026-10-16'), 3)->map->format('Y-m-d')->all())
        ->toBe(['2026-10-20', '2026-10-23', '2026-10-26']);
});

test('next collection remains current through the end time and then advances', function () {
    $service = app(ScheduleReminderService::class);
    $schedule = intervalSchedule();

    $before = $service->nextOccurrence($schedule, Carbon::parse('2026-10-16 05:00:00'));
    $during = $service->nextOccurrence($schedule, Carbon::parse('2026-10-16 07:00:00'));
    $after = $service->nextOccurrence($schedule, Carbon::parse('2026-10-16 09:01:00'));

    expect($before?->format('Y-m-d H:i'))->toBe('2026-10-16 06:00')
        ->and($during?->format('Y-m-d H:i'))->toBe('2026-10-16 06:00')
        ->and($after?->format('Y-m-d H:i'))->toBe('2026-10-19 06:00');
});

test('interval matching continues correctly into the next calendar month', function () {
    $service = app(ScheduleReminderService::class);
    $schedule = intervalSchedule();

    expect($service->occursOn($schedule, Carbon::parse('2026-10-31')))->toBeTrue()
        ->and($service->occursOn($schedule, Carbon::parse('2026-11-01')))->toBeFalse()
        ->and($service->occursOn($schedule, Carbon::parse('2026-11-03')))->toBeTrue();
});

test('specific date schedules still occur exactly once', function () {
    $schedule = intervalSchedule([
        'schedule_type' => 'specific_date',
        'specific_date' => '2026-10-20',
        'recurrence_start_date' => null,
        'recurrence_interval_days' => null,
    ]);
    $service = app(ScheduleReminderService::class);

    expect($service->nextOccurrences($schedule, Carbon::parse('2026-10-16'), 5))->toHaveCount(1)
        ->and($service->occursOn($schedule, Carbon::parse('2026-10-20')))->toBeTrue()
        ->and($service->occursOn($schedule, Carbon::parse('2026-10-21')))->toBeFalse();
});

test('resident schedule endpoints isolate interval schedules by official service area', function () {
    ServiceZone::query()->create(['name' => 'Purok 2', 'barangay' => 'Capitol', 'status' => 'active']);
    $resident = User::factory()->create(['service_area' => 'Purok 2 - Capitol']);
    $matching = intervalSchedule();
    intervalSchedule(['area' => 'Purok 3 - Other', 'truck' => 'TRK-99']);

    $this->actingAs($resident, 'sanctum')->getJson('/api/v1/schedules')
        ->assertOk()
        ->assertJsonCount(1, 'schedules')
        ->assertJsonPath('schedules.0.id', $matching->id)
        ->assertJsonPath('schedules.0.recurrence_interval_days', 3)
        ->assertJsonPath('schedules.0.next_collection_at', '2026-10-16T06:00:00+00:00')
        ->assertJsonPath('upcoming_pickups.0.collection_at', '2026-10-16T06:00:00+00:00')
        ->assertJsonPath('upcoming_pickups.1.collection_at', '2026-10-19T06:00:00+00:00');

    $this->actingAs($resident)->get(route('garbage-schedule'))
        ->assertOk()
        ->assertSee('"recurrence_start_date":"2026-10-16"', false)
        ->assertSee('"recurrence_interval_days":3', false)
        ->assertSee('Oct 16')
        ->assertDontSee('TRK-99');
});

test('the calendar receives the start-date anchor even after that collection has finished', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00'));
    ServiceZone::query()->create(['name' => 'Purok 2', 'barangay' => 'Capitol', 'status' => 'active']);
    $resident = User::factory()->create(['service_area' => 'Purok 2 - Capitol']);
    intervalSchedule();

    $this->actingAs($resident)->get(route('garbage-schedule'))
        ->assertOk()
        ->assertSee('"recurrence_start_date":"2026-10-16"', false)
        ->assertSee('elapsedDays >= 0', false);
});

test('one day before an interval occurrence qualifies for reminder delivery', function () {
    Mail::fake();
    Carbon::setTestNow(Carbon::parse('2026-10-18 08:00:00'));
    $resident = User::factory()->create([
        'service_area' => 'Purok 2 - Capitol',
        'email_notifications' => true,
        'sms_notifications' => false,
        'notification_preferences' => ['schedule_reminders' => true],
    ]);
    $schedule = intervalSchedule();

    $this->artisan('sms:send-schedule-reminders')->assertSuccessful();

    $this->assertDatabaseHas('email_notifications', [
        'user_id' => $resident->id,
        'schedule_id' => $schedule->id,
        'collection_date' => '2026-10-19 00:00:00',
    ]);
    Mail::assertSent(GarbageCollectionReminder::class);
});

test('one day before the start-date occurrence qualifies for reminder delivery', function () {
    Mail::fake();
    Carbon::setTestNow(Carbon::parse('2026-10-15 08:00:00'));
    $resident = User::factory()->create([
        'service_area' => 'Purok 2 - Capitol',
        'email_notifications' => true,
        'sms_notifications' => false,
        'notification_preferences' => ['schedule_reminders' => true],
    ]);
    $schedule = intervalSchedule();

    $this->artisan('sms:send-schedule-reminders')->assertSuccessful();

    $this->assertDatabaseHas('email_notifications', [
        'user_id' => $resident->id,
        'schedule_id' => $schedule->id,
        'collection_date' => '2026-10-16 00:00:00',
    ]);
});

test('repeated reminder runs do not duplicate the same occurrence and channel', function () {
    Mail::fake();
    Carbon::setTestNow(Carbon::parse('2026-10-18 08:00:00'));
    User::factory()->create([
        'service_area' => 'Purok 2 - Capitol',
        'email_notifications' => true,
        'sms_notifications' => false,
        'notification_preferences' => ['schedule_reminders' => true],
    ]);
    intervalSchedule();

    $this->artisan('sms:send-schedule-reminders')->assertSuccessful();
    $this->artisan('sms:send-schedule-reminders')->assertSuccessful();

    expect(EmailNotification::query()->count())->toBe(1);
    Mail::assertSentCount(1);
});

test('admin can create one configurable interval definition and invalid intervals are rejected', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->post(route('admin.schedule.store'), adminIntervalPayload())
        ->assertRedirect(route('admin.schedule'));

    $this->assertDatabaseHas('schedules', [
        'area' => 'Purok 2 - Capitol',
        'recurrence_start_date' => '2026-10-16 00:00:00',
        'recurrence_interval_days' => 3,
        'days' => null,
    ]);
    expect(Schedule::query()->count())->toBe(1);

    $this->actingAs($admin)->post(route('admin.schedule.store'), adminIntervalPayload([
        'recurrence_interval_days' => 0,
    ]))->assertSessionHasErrors('recurrence_interval_days');
    $this->actingAs($admin)->post(route('admin.schedule.store'), adminIntervalPayload([
        'recurrence_interval_days' => 366,
    ]))->assertSessionHasErrors('recurrence_interval_days');
});

test('editing the start date immediately replaces the recurrence anchor', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $schedule = intervalSchedule();

    $this->actingAs($admin)->put(route('admin.schedule.update', $schedule), adminIntervalPayload([
        'recurrence_start_date' => '2026-10-17',
    ]))->assertRedirect(route('admin.schedule'));

    $dates = app(ScheduleReminderService::class)
        ->nextOccurrences($schedule->fresh(), Carbon::parse('2026-10-16 00:00:00'), 3)
        ->map->format('Y-m-d')
        ->all();

    expect($dates)->toBe(['2026-10-17', '2026-10-20', '2026-10-23']);
});

test('legacy weekday schedules without interval fields remain supported', function () {
    $schedule = intervalSchedule([
        'recurrence_start_date' => null,
        'recurrence_interval_days' => null,
        'days' => 'Monday & Thursday',
    ]);
    $service = app(ScheduleReminderService::class);

    expect($service->occursOn($schedule, Carbon::parse('2026-10-19')))->toBeTrue()
        ->and($service->occursOn($schedule, Carbon::parse('2026-10-20')))->toBeFalse()
        ->and($service->nextOccurrences($schedule, Carbon::parse('2026-10-16'), 5))->toHaveCount(2);
});
