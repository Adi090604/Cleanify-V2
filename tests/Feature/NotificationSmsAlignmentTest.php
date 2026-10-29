<?php

use App\Models\Report;
use App\Models\Schedule;
use App\Models\ServiceZone;
use App\Models\SmsNotification;
use App\Models\User;
use App\Models\UserReport;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\ReportInProgressNotification;
use App\Notifications\ReportRejectedNotification;
use App\Notifications\ReportResolvedNotification;
use App\Notifications\ScheduleCreatedNotification;
use App\Notifications\ScheduleUpdatedNotification;
use App\Notifications\ServiceAreaRequestStatusNotification;
use App\Notifications\TestEmailNotification;
use App\Notifications\UserReportReviewedNotification;
use App\Services\SmsNotificationService;
use Illuminate\Support\Facades\Mail;

class RecordingNotificationSmsService extends SmsNotificationService
{
    /** @var array<int, array{to: string, message: string}> */
    public array $attempts = [];

    public bool $fail = false;

    public function send(string $to, string $message): bool
    {
        $this->attempts[] = ['to' => $to, 'message' => $message];

        if ($this->fail) {
            throw new RuntimeException('Simulated provider failure.');
        }

        return true;
    }
}

function recordNotificationSms(): RecordingNotificationSmsService
{
    $service = new RecordingNotificationSmsService;
    app()->instance(SmsNotificationService::class, $service);

    return $service;
}

function smsAlignmentSchedule(array $overrides = []): Schedule
{
    return Schedule::query()->create(array_merge([
        'area' => 'Purok 1, Canlanipa',
        'schedule_type' => 'specific_date',
        'specific_date' => '2026-11-03',
        'days' => null,
        'time_start' => '08:00',
        'time_end' => '10:00',
        'truck' => 'TRK-01',
        'status' => 'active',
    ], $overrides));
}

function smsAlignmentReport(User $owner, array $overrides = []): Report
{
    return Report::query()->create(array_merge([
        'user_id' => $owner->id,
        'location' => 'Purok 1, Canlanipa',
        'description' => 'Uncollected waste beside the road.',
        'status' => Report::STATUS_PENDING,
    ], $overrides));
}

test('report status SMS follows global and report category preferences', function () {
    Mail::fake();
    $sms = recordNotificationSms();
    $eligible = User::factory()->create([
        'phone' => '09171234567',
        'email_notifications' => false,
        'sms_notifications' => true,
        'notification_preferences' => ['report_updates' => true],
    ]);
    $smsDisabled = User::factory()->create([
        'phone' => '09171234568',
        'email_notifications' => false,
        'sms_notifications' => false,
        'notification_preferences' => ['report_updates' => true],
    ]);
    $categoryDisabled = User::factory()->create([
        'phone' => '09171234569',
        'email_notifications' => false,
        'sms_notifications' => true,
        'notification_preferences' => ['report_updates' => false],
    ]);
    $report = smsAlignmentReport($eligible);

    $eligible->notify(new ReportInProgressNotification($report));
    $smsDisabled->notify(new ReportInProgressNotification($report));
    $categoryDisabled->notify(new ReportInProgressNotification($report));

    expect($sms->attempts)->toBe([[
        'to' => '+639171234567',
        'message' => 'Cleanify: Your report is now In Progress and is being reviewed. Check Cleanify for details.',
    ]])
        ->and($eligible->notifications()->count())->toBe(1)
        ->and($smsDisabled->notifications()->count())->toBe(1)
        ->and($categoryDisabled->notifications()->count())->toBe(0);
});

test('resolved and rejected reports send concise plain-text SMS without private notes', function () {
    Mail::fake();
    $sms = recordNotificationSms();
    $recipient = User::factory()->create([
        'phone' => '+639171234567',
        'email_notifications' => false,
        'sms_notifications' => true,
        'notification_preferences' => ['report_updates' => true],
    ]);
    $report = smsAlignmentReport($recipient, [
        'admin_notes' => 'Private resolution note.',
        'rejection_reason' => 'Private rejection detail.',
    ]);

    $recipient->notify(new ReportResolvedNotification($report));
    $recipient->notify(new ReportRejectedNotification($report));

    expect(array_column($sms->attempts, 'message'))->toBe([
        'Cleanify: Your report has been resolved. Check Cleanify for the resolution details.',
        'Cleanify: Your report was rejected. Check Cleanify for the reason and details.',
    ]);

    foreach ($sms->attempts as $attempt) {
        expect($attempt['message'])->toStartWith('Cleanify:')
            ->not->toContain('<', 'Private resolution note.', 'Private rejection detail.');
    }
});

test('new and updated schedules SMS only the correct eligible residents', function () {
    Mail::fake();
    $sms = recordNotificationSms();
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create([
        'phone' => '09171234567',
        'service_area' => 'Purok 1, Canlanipa',
        'email_notifications' => false,
        'sms_notifications' => true,
        'notification_preferences' => ['schedule_reminders' => true],
    ]);
    User::factory()->create([
        'phone' => '09171234568',
        'service_area' => 'Purok 1, Canlanipa',
        'sms_notifications' => false,
        'notification_preferences' => ['schedule_reminders' => true],
    ]);
    User::factory()->create([
        'phone' => '09171234569',
        'service_area' => 'Purok 1, Canlanipa',
        'sms_notifications' => true,
        'notification_preferences' => ['schedule_reminders' => false],
    ]);
    User::factory()->create([
        'phone' => '09171234560',
        'service_area' => 'Another Area',
        'sms_notifications' => true,
        'notification_preferences' => ['schedule_reminders' => true],
    ]);

    $this->actingAs($admin)->post(route('admin.schedule.store'), [
        'area' => 'Purok 1, Canlanipa',
        'schedule_type' => 'specific_date',
        'specific_date' => '2026-11-03',
        'time_start' => '08:00',
        'time_end' => '10:00',
        'truck' => 'TRK-01',
        'status' => 'active',
    ])->assertSessionHasNoErrors();

    $schedule = Schedule::query()->sole();
    $this->put(route('admin.schedule.update', $schedule), [
        'area' => 'Purok 1, Canlanipa',
        'schedule_type' => 'specific_date',
        'specific_date' => '2026-11-03',
        'time_start' => '08:00',
        'time_end' => '11:00',
        'truck' => 'TRK-01',
        'status' => 'active',
    ])->assertSessionHasNoErrors();

    expect($sms->attempts)->toHaveCount(2)
        ->and($sms->attempts[0]['message'])->toBe('Cleanify: New collection schedule for Purok 1, Canlanipa: Nov 03, 2026, 8:00 AM - 10:00 AM. Check Cleanify for details.')
        ->and($sms->attempts[1]['message'])->toBe('Cleanify: Your garbage collection schedule for Purok 1, Canlanipa was updated: Nov 03, 2026 at 8:00 AM - 11:00 AM. Check Cleanify for details.');
});

test('one-day reminder keeps one send and includes area date and time facts', function () {
    Mail::fake();
    $sms = recordNotificationSms();
    User::factory()->create([
        'phone' => '09171234567',
        'service_area' => 'Purok 1, Canlanipa',
        'email_notifications' => true,
        'sms_notifications' => true,
        'notification_preferences' => ['schedule_reminders' => true],
    ]);
    $schedule = smsAlignmentSchedule();

    $this->artisan('sms:send-schedule-reminders', ['--date' => '2026-11-03'])->assertSuccessful();
    $this->artisan('sms:send-schedule-reminders', ['--date' => '2026-11-03'])->assertSuccessful();

    expect($sms->attempts)->toBe([[
        'to' => '+639171234567',
        'message' => 'Cleanify: Collection for Purok 1, Canlanipa is tomorrow, Nov 3, 8:00 AM - 10:00 AM. Please prepare your waste before collection.',
    ]])
        ->and(SmsNotification::query()->where('schedule_id', $schedule->id)->count())->toBe(1);
});

test('service-area events use resident-safe area and Barangay SMS content', function () {
    Mail::fake();
    $sms = recordNotificationSms();
    $recipient = User::factory()->create([
        'phone' => '09171234567',
        'email_notifications' => false,
        'sms_notifications' => true,
        'notification_preferences' => ['system' => true],
    ]);
    $systemSmsDisabled = User::factory()->create([
        'phone' => '09171234568',
        'email_notifications' => false,
        'sms_notifications' => true,
        'notification_preferences' => ['system' => false],
    ]);
    $request = $recipient->serviceAreaRequests()->create([
        'area_name' => 'Purok Riverside',
        'normalized_area_name' => 'purok riverside',
        'barangay' => 'Canlanipa',
    ]);
    $zone = ServiceZone::query()->create([
        'name' => 'Purok Riverside',
        'barangay' => 'Canlanipa',
        'status' => 'active',
    ]);

    $recipient->notify(new ServiceAreaRequestStatusNotification($request, ServiceAreaRequestStatusNotification::EVENT_APPROVED));
    $recipient->notify(new ServiceAreaRequestStatusNotification($request, ServiceAreaRequestStatusNotification::EVENT_REJECTED));
    $recipient->notify(new ServiceAreaRequestStatusNotification($request, ServiceAreaRequestStatusNotification::EVENT_ZONE_CREATED, $zone, true));
    $systemSmsDisabled->notify(new ServiceAreaRequestStatusNotification($request, ServiceAreaRequestStatusNotification::EVENT_APPROVED));

    expect(array_column($sms->attempts, 'message'))->toBe([
        'Cleanify: Your service area request for Purok Riverside, Canlanipa was approved. Check Cleanify for details.',
        'Cleanify: Your service area request for Purok Riverside, Canlanipa was not approved. Check Cleanify for details.',
        'Cleanify: Your area, Purok Riverside, Canlanipa, has been added and is now your collection service area.',
    ])->and($systemSmsDisabled->notifications()->count())->toBe(1);
});

test('user report review sends resident-safe SMS while the admin test remains email only', function () {
    Mail::fake();
    $sms = recordNotificationSms();
    $reporter = User::factory()->create([
        'phone' => '09171234567',
        'email_notifications' => true,
        'sms_notifications' => true,
        'notification_preferences' => ['report_updates' => true],
    ]);
    $reportedUser = User::factory()->create();
    $userReport = UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $reportedUser->id,
        'reason' => 'spam',
        'status' => 'reviewed',
    ]);

    $reporter->notify(new UserReportReviewedNotification($userReport));
    $reporter->notify(new TestEmailNotification);

    expect($sms->attempts)->toBe([[
        'to' => '+639171234567',
        'message' => 'Cleanify: Your user report has been reviewed. Check Cleanify for details.',
    ]])
        ->and((new TestEmailNotification)->via($reporter))->toBe(['mail']);
});

test('invalid phones and provider failures never block database notifications or report transitions', function () {
    Mail::fake();
    $sms = recordNotificationSms();
    $invalidPhoneUser = User::factory()->create([
        'phone' => 'invalid',
        'email_notifications' => false,
        'sms_notifications' => true,
        'notification_preferences' => ['report_updates' => true],
    ]);
    $invalidReport = smsAlignmentReport($invalidPhoneUser);
    $invalidPhoneUser->notify(new ReportInProgressNotification($invalidReport));

    expect($sms->attempts)->toBe([])
        ->and($invalidPhoneUser->notifications()->count())->toBe(1);

    $sms->fail = true;
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create([
        'phone' => '09171234567',
        'email_notifications' => true,
        'sms_notifications' => true,
        'notification_preferences' => ['report_updates' => true],
    ]);
    $report = smsAlignmentReport($owner);

    $this->actingAs($admin)->post(route('admin.reports.in-progress', $report))
        ->assertSessionHas('success');

    expect($report->fresh()->status)->toBe(Report::STATUS_IN_PROGRESS)
        ->and($owner->notifications()->count())->toBe(1)
        ->and($sms->attempts)->toHaveCount(1)
        ->and((new ReportInProgressNotification($report))->via($owner))->toContain('mail');
});

test('SMS channel registration matches each event category preference', function () {
    $smsEnabled = User::factory()->create([
        'sms_notifications' => true,
        'notification_preferences' => [
            'report_updates' => true,
            'schedule_reminders' => true,
            'system' => true,
        ],
    ]);
    $report = smsAlignmentReport($smsEnabled);
    $schedule = smsAlignmentSchedule();
    $request = $smsEnabled->serviceAreaRequests()->create([
        'area_name' => 'Purok Riverside',
        'normalized_area_name' => 'purok riverside',
        'barangay' => 'Canlanipa',
    ]);

    expect((new ReportInProgressNotification($report))->via($smsEnabled))->toContain(SmsChannel::class)
        ->and((new ScheduleCreatedNotification($schedule))->via($smsEnabled))->toContain(SmsChannel::class)
        ->and((new ScheduleUpdatedNotification($schedule))->via($smsEnabled))->toContain(SmsChannel::class)
        ->and((new ServiceAreaRequestStatusNotification($request, ServiceAreaRequestStatusNotification::EVENT_APPROVED))->via($smsEnabled))->toContain(SmsChannel::class);
});
