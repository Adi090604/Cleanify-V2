<?php

use App\Models\Report;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\ReportRejectedNotification;
use App\Notifications\ReportResolvedNotification;
use App\Notifications\ScheduleUpdatedNotification;
use Illuminate\Support\Facades\Notification;

function scheduleUpdatePayload(Schedule $schedule, array $overrides = []): array
{
    return array_merge([
        'area' => $schedule->area,
        'schedule_type' => $schedule->schedule_type,
        'specific_date' => $schedule->specific_date?->format('Y-m-d'),
        'days' => $schedule->days,
        'time_start' => $schedule->time_start->format('H:i'),
        'time_end' => $schedule->time_end->format('H:i'),
        'truck' => $schedule->truck,
        'status' => $schedule->status,
    ], $overrides);
}

function recurringSchedule(array $overrides = []): Schedule
{
    return Schedule::query()->create(array_merge([
        'area' => 'Zone 1 - Barangay Alang-Alang',
        'schedule_type' => 'recurring',
        'specific_date' => null,
        'days' => 'Monday',
        'time_start' => '08:00',
        'time_end' => '09:00',
        'truck' => 'TRK-01',
        'status' => 'active',
    ], $overrides));
}

test('meaningful recurring schedule fields each send an update notification', function (array $change) {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create([
        'service_area' => 'Zone 1 - Barangay Alang-Alang',
        'email_notifications' => true,
    ]);
    $schedule = recurringSchedule();

    $this->actingAs($admin)
        ->put(route('admin.schedule.update', $schedule), scheduleUpdatePayload($schedule, $change))
        ->assertRedirect(route('admin.schedule'));

    Notification::assertSentTo(
        $resident,
        ScheduleUpdatedNotification::class,
        fn (ScheduleUpdatedNotification $notification, array $channels) => in_array(array_key_first($change), $notification->changedFields, true)
            && $channels === ['database', 'mail']
    );
})->with([
    'collection day' => [['days' => 'Tuesday']],
    'start time' => [['time_start' => '07:30']],
    'end time' => [['time_end' => '09:30']],
    'assigned truck' => [['truck' => 'TRK-02']],
]);

test('changing a specific collection date sends an update notification', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create(['service_area' => 'Zone 1 - Barangay Alang-Alang']);
    $schedule = recurringSchedule([
        'schedule_type' => 'specific_date',
        'days' => null,
        'specific_date' => now()->addDays(2)->format('Y-m-d'),
    ]);

    $this->actingAs($admin)->put(
        route('admin.schedule.update', $schedule),
        scheduleUpdatePayload($schedule, ['specific_date' => now()->addDays(3)->format('Y-m-d')])
    )->assertRedirect(route('admin.schedule'));

    Notification::assertSentTo($resident, ScheduleUpdatedNotification::class);
});

test('only eligible users in the affected zone receive one notification each', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true, 'service_area' => 'Zone 1 - Barangay Alang-Alang']);
    $first = User::factory()->create(['service_area' => 'Zone 1 - Barangay Alang-Alang']);
    $second = User::factory()->create(['service_area' => 'Zone 1 - Barangay Alang-Alang']);
    $unrelated = User::factory()->create(['service_area' => 'Zone 2 - Barangay Bagacay']);
    $banned = User::factory()->create([
        'service_area' => 'Zone 1 - Barangay Alang-Alang',
        'banned_at' => now(),
    ]);
    $muted = User::factory()->create([
        'service_area' => 'Zone 1 - Barangay Alang-Alang',
        'notification_preferences' => ['schedule' => false],
    ]);
    $schedule = recurringSchedule();

    $this->actingAs($admin)->put(
        route('admin.schedule.update', $schedule),
        scheduleUpdatePayload($schedule, ['days' => 'Wednesday'])
    );

    expect(Notification::sent($first, ScheduleUpdatedNotification::class))->toHaveCount(1)
        ->and(Notification::sent($second, ScheduleUpdatedNotification::class))->toHaveCount(1);
    Notification::assertNotSentTo([$admin, $unrelated, $banned, $muted], ScheduleUpdatedNotification::class);
});

test('schedule update creates structured unread database notification data', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create([
        'service_area' => 'Zone 1 - Barangay Alang-Alang',
        'email_notifications' => false,
    ]);
    $schedule = recurringSchedule();

    $this->actingAs($admin)->put(
        route('admin.schedule.update', $schedule),
        scheduleUpdatePayload($schedule, [
            'days' => 'Friday',
            'time_start' => '10:00',
            'time_end' => '11:30',
            'truck' => 'TRK-09',
        ])
    );

    $notification = $resident->fresh()->notifications()->sole();

    expect($notification->read_at)->toBeNull()
        ->and($notification->data)->toMatchArray([
            'notification_type' => 'schedule_updated',
            'schedule_id' => $schedule->id,
            'service_zone' => 'Zone 1 - Barangay Alang-Alang',
            'schedule_type' => 'recurring',
            'days' => 'Friday',
            'specific_date' => null,
            'time_start' => '10:00',
            'time_end' => '11:30',
            'time_range' => '10:00 AM - 11:30 AM',
            'assigned_truck' => 'TRK-09',
            'status' => 'active',
            'category' => 'schedule',
            'title' => 'Collection Schedule Updated',
            'url' => '/garbage-schedule',
        ])
        ->and($notification->data['schedule_updated_at'])->not->toBeNull();
});

test('schedule update email contains the real collection details', function () {
    $resident = User::factory()->create([
        'name' => 'Maria Resident',
        'email_notifications' => true,
    ]);
    $schedule = recurringSchedule([
        'days' => 'Thursday',
        'time_start' => '06:30',
        'time_end' => '08:00',
        'truck' => 'TRK-07',
    ]);
    $notification = new ScheduleUpdatedNotification($schedule);
    $mail = $notification->toMail($resident);

    expect($notification->via($resident))->toBe(['database', 'mail'])
        ->and($mail->subject)->toBe('Cleanify Collection Schedule Updated')
        ->and($mail->greeting)->toBe('Hello Maria Resident!')
        ->and($mail->introLines)->toContain(
            'Zone 1 - Barangay Alang-Alang',
            'Thursday',
            '6:30 AM - 8:00 AM',
            'TRK-07',
            'Active'
        );
});

test('saving without meaningful changes sends nothing', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create(['service_area' => 'Zone 1 - Barangay Alang-Alang']);
    $schedule = recurringSchedule();

    $this->actingAs($admin)
        ->put(route('admin.schedule.update', $schedule), scheduleUpdatePayload($schedule))
        ->assertRedirect(route('admin.schedule'));

    Notification::assertNothingSent();
});

test('validation failure leaves the schedule unchanged and sends nothing', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create(['service_area' => 'Zone 1 - Barangay Alang-Alang']);
    $schedule = recurringSchedule();

    $this->actingAs($admin)->put(
        route('admin.schedule.update', $schedule),
        scheduleUpdatePayload($schedule, ['time_start' => '10:00', 'time_end' => '09:00'])
    )->assertSessionHasErrors('time_end');

    expect($schedule->fresh()->time_start->format('H:i'))->toBe('08:00');
    Notification::assertNothingSent();
});

test('zone reassignment notifies old and new zone users with distinct neutral messages', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $oldZoneUser = User::factory()->create(['service_area' => 'Zone 1 - Barangay Alang-Alang']);
    $newZoneUser = User::factory()->create(['service_area' => 'Zone 2 - Barangay Bagacay']);
    $unrelated = User::factory()->create(['service_area' => 'Zone 3 - Barangay Caibaan']);
    $schedule = recurringSchedule();

    $this->actingAs($admin)->put(
        route('admin.schedule.update', $schedule),
        scheduleUpdatePayload($schedule, ['area' => 'Zone 2 - Barangay Bagacay'])
    );

    Notification::assertSentTo($oldZoneUser, ScheduleUpdatedNotification::class, function ($notification) {
        $data = $notification->toArray($notification);

        return $notification->changeKind === ScheduleUpdatedNotification::CHANGE_REMOVED_FROM_AREA
            && str_contains($data['message'], 'no longer applies');
    });
    Notification::assertSentTo($newZoneUser, ScheduleUpdatedNotification::class, fn ($notification) => $notification->changeKind === ScheduleUpdatedNotification::CHANGE_ASSIGNED_TO_AREA
    );
    expect(Notification::sent($oldZoneUser, ScheduleUpdatedNotification::class))->toHaveCount(1)
        ->and(Notification::sent($newZoneUser, ScheduleUpdatedNotification::class))->toHaveCount(1);
    Notification::assertNotSentTo($unrelated, ScheduleUpdatedNotification::class);
});

test('new schedule notification works in web inbox badge read flow and mobile api', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create([
        'service_area' => 'Zone 1 - Barangay Alang-Alang',
        'email_notifications' => false,
    ]);
    $schedule = recurringSchedule();

    $this->actingAs($admin)->put(
        route('admin.schedule.update', $schedule),
        scheduleUpdatePayload($schedule, ['truck' => 'TRK-03'])
    );

    $notification = $resident->fresh()->unreadNotifications()->sole();

    $this->actingAs($resident)->get(route('notifications'))
        ->assertOk()
        ->assertSee('Collection Schedule Updated')
        ->assertSee('1 unread');

    $this->actingAs($resident, 'sanctum')->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('notifications.0.title', 'Collection Schedule Updated')
        ->assertJsonPath('notifications.0.category', 'schedule')
        ->assertJsonPath('notifications.0.action_route', '/tabs/schedule')
        ->assertJsonPath('unread_count', 1);

    $this->actingAs($resident)->post(route('notifications.mark-read', $notification->id))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('existing resolved and rejected report notifications retain their channels and data', function () {
    Notification::fake();

    $owner = User::factory()->create(['email_notifications' => true]);
    $report = Report::query()->create([
        'user_id' => $owner->id,
        'location' => 'Barangay Alang-Alang',
        'description' => 'Uncollected waste near the market.',
        'status' => 'pending',
    ]);

    $owner->notify(new ReportResolvedNotification($report));
    $owner->notify(new ReportRejectedNotification($report));

    Notification::assertSentTo($owner, ReportResolvedNotification::class, fn ($notification, $channels) => $channels === ['database', 'mail']
        && $notification->toArray($owner)['status'] === 'resolved'
    );
    Notification::assertSentTo($owner, ReportRejectedNotification::class, fn ($notification, $channels) => $channels === ['database', 'mail']
        && $notification->toArray($owner)['status'] === 'rejected'
    );
});
