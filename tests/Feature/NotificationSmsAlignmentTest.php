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
use Illuminate\Contracts\Mail\Factory as MailFactory;
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
        'specific_date' => now()->addDays(2)->toDateString(),
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

test('report in progress remains in app only and follows the report category preference', function () {
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

    expect($sms->attempts)->toBe([])
        ->and($eligible->notifications()->count())->toBe(1)
        ->and($smsDisabled->notifications()->count())->toBe(1)
        ->and($categoryDisabled->notifications()->count())->toBe(0);
});

test('only resolved owner reports send concise plain-text SMS without private notes', function () {
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
    ])->and($recipient->notifications()->count())->toBe(2);

    foreach ($sms->attempts as $attempt) {
        expect($attempt['message'])->toStartWith('Cleanify:')
            ->not->toContain('<', 'Private resolution note.', 'Private rejection detail.');
    }
});

test('resolved workflow sends one owner SMS while followers remain in app only', function () {
    Mail::fake();
    $sms = recordNotificationSms();
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create([
        'phone' => '09171234567',
        'email_notifications' => false,
        'sms_notifications' => true,
        'notification_preferences' => ['report_updates' => true],
    ]);
    $follower = User::factory()->create([
        'phone' => '09171234568',
        'email_notifications' => false,
        'sms_notifications' => true,
        'notification_preferences' => ['report_updates' => true],
    ]);
    $report = smsAlignmentReport($owner, ['status' => Report::STATUS_IN_PROGRESS]);
    $report->followers()->attach($follower);

    $this->actingAs($admin)->post(route('admin.reports.resolve', $report), [
        'admin_notes' => 'Collection completed.',
    ])->assertSessionHas('success');

    $this->post(route('admin.reports.resolve', $report), [
        'admin_notes' => 'Duplicate request.',
    ])->assertSessionHas('error');

    expect($sms->attempts)->toBe([[
        'to' => '+639171234567',
        'message' => 'Cleanify: Your report has been resolved. Check Cleanify for the resolution details.',
    ]])
        ->and($owner->notifications()->count())->toBe(1)
        ->and($follower->notifications()->count())->toBe(1);
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

    $collectionDate = now()->addDays(2);

    $this->actingAs($admin)->post(route('admin.schedule.store'), [
        'area' => 'Purok 1, Canlanipa',
        'schedule_type' => 'specific_date',
        'specific_date' => $collectionDate->toDateString(),
        'time_start' => '08:00',
        'time_end' => '10:00',
        'truck' => 'TRK-01',
        'status' => 'active',
    ])->assertSessionHasNoErrors();

    $schedule = Schedule::query()->sole();
    $this->put(route('admin.schedule.update', $schedule), [
        'area' => 'Purok 1, Canlanipa',
        'schedule_type' => 'specific_date',
        'specific_date' => $collectionDate->toDateString(),
        'time_start' => '08:00',
        'time_end' => '11:00',
        'truck' => 'TRK-01',
        'status' => 'active',
    ])->assertSessionHasNoErrors();

    expect($sms->attempts)->toHaveCount(1)
        ->and($sms->attempts[0]['message'])->toBe('Cleanify: Your garbage collection schedule for Purok 1, Canlanipa was updated: '.$collectionDate->format('M d, Y').' at 8:00 AM - 11:00 AM. Check Cleanify for details.');
});

test('truck-only schedule edits remain in app and do not send SMS', function () {
    Mail::fake();
    $sms = recordNotificationSms();
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create([
        'phone' => '09171234567',
        'service_area' => 'Purok 1, Canlanipa',
        'email_notifications' => false,
        'sms_notifications' => true,
        'notification_preferences' => ['schedule_reminders' => true],
    ]);
    $schedule = smsAlignmentSchedule();

    $this->actingAs($admin)->put(route('admin.schedule.update', $schedule), [
        'area' => $schedule->area,
        'schedule_type' => $schedule->schedule_type,
        'specific_date' => $schedule->specific_date->toDateString(),
        'time_start' => '08:00',
        'time_end' => '10:00',
        'truck' => 'TRK-02',
        'status' => 'active',
    ])->assertSessionHasNoErrors();

    expect($sms->attempts)->toBe([])
        ->and($resident->notifications()->count())->toBe(1);
});

test('schedule cancellation and active deletion send one clear SMS to affected residents', function () {
    Mail::fake();
    $sms = recordNotificationSms();
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create([
        'phone' => '09171234567',
        'service_area' => 'Purok 1, Canlanipa',
        'email_notifications' => false,
        'sms_notifications' => true,
        'notification_preferences' => ['schedule_reminders' => true],
    ]);
    $cancelled = smsAlignmentSchedule();
    $deleted = smsAlignmentSchedule(['specific_date' => now()->addDays(3)->toDateString()]);

    $this->actingAs($admin)->put(route('admin.schedule.update', $cancelled), [
        'area' => $cancelled->area,
        'schedule_type' => $cancelled->schedule_type,
        'specific_date' => $cancelled->specific_date->toDateString(),
        'time_start' => '08:00',
        'time_end' => '10:00',
        'truck' => 'TRK-01',
        'status' => 'inactive',
    ])->assertSessionHasNoErrors();

    $this->delete(route('admin.schedule.destroy', $deleted))->assertRedirect(route('admin.schedule'));

    expect($sms->attempts)->toHaveCount(2);
    foreach ($sms->attempts as $attempt) {
        expect($attempt['message'])->toContain('has been cancelled');
    }
    expect($resident->notifications()->count())->toBe(2);
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
    $collectionDate = $schedule->specific_date->toDateString();

    $this->artisan('sms:send-schedule-reminders', ['--date' => $collectionDate])->assertSuccessful();
    $this->artisan('sms:send-schedule-reminders', ['--date' => $collectionDate])->assertSuccessful();

    expect($sms->attempts)->toBe([[
        'to' => '+639171234567',
        'message' => 'Cleanify: Collection for Purok 1, Canlanipa is tomorrow, '.$schedule->specific_date->format('M j').', 8:00 AM - 10:00 AM. Please prepare your waste before collection.',
    ]])
        ->and(SmsNotification::query()->where('schedule_id', $schedule->id)->count())->toBe(1);
});

test('service-area events remain in app only', function () {
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

    expect($sms->attempts)->toBe([])
        ->and($recipient->notifications()->count())->toBe(3)
        ->and($systemSmsDisabled->notifications()->count())->toBe(1);
});

test('user report review remains in app while the admin test remains email only', function () {
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

    expect($sms->attempts)->toBe([])
        ->and($reporter->notifications()->count())->toBe(1)
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
    $report = smsAlignmentReport($owner, ['status' => Report::STATUS_IN_PROGRESS]);

    $this->actingAs($admin)->post(route('admin.reports.resolve', $report), ['admin_notes' => 'Resolved safely'])
        ->assertSessionHas('success');

    expect($report->fresh()->status)->toBe(Report::STATUS_RESOLVED)
        ->and($owner->notifications()->count())->toBe(1)
        ->and($sms->attempts)->toHaveCount(1)
        ->and((new ReportResolvedNotification($report))->via($owner))->toContain('mail');
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

    expect((new ReportInProgressNotification($report))->via($smsEnabled))->not->toContain(SmsChannel::class)
        ->and((new ReportResolvedNotification($report))->via($smsEnabled))->toContain(SmsChannel::class)
        ->and((new ScheduleCreatedNotification($schedule))->via($smsEnabled))->not->toContain(SmsChannel::class)
        ->and((new ScheduleUpdatedNotification($schedule, changedFields: ['time_end']))->via($smsEnabled))->toContain(SmsChannel::class)
        ->and((new ServiceAreaRequestStatusNotification($request, ServiceAreaRequestStatusNotification::EVENT_APPROVED))->via($smsEnabled))->not->toContain(SmsChannel::class);
});

test('approved notification SMS channels run before optional mail', function () {
    $recipient = User::factory()->create([
        'email_notifications' => true,
        'sms_notifications' => true,
        'notification_preferences' => [
            'report_updates' => true,
            'schedule_reminders' => true,
        ],
    ]);
    $report = smsAlignmentReport($recipient, ['status' => Report::STATUS_RESOLVED]);
    $schedule = smsAlignmentSchedule();

    expect((new ReportResolvedNotification($report))->via($recipient))
        ->toBe(['database', SmsChannel::class, 'mail'])
        ->and((new ScheduleUpdatedNotification($schedule, changedFields: ['time_start']))->via($recipient))
        ->toBe(['database', SmsChannel::class, 'mail'])
        ->and((new ReportResolvedNotification($report, true))->via($recipient))
        ->toBe(['database', 'mail']);
});

test('mail failure occurs only after an approved SMS attempt', function () {
    $sms = recordNotificationSms();
    $recipient = User::factory()->create([
        'phone' => '09171234567',
        'email_notifications' => true,
        'sms_notifications' => true,
        'notification_preferences' => ['report_updates' => true],
    ]);
    $report = smsAlignmentReport($recipient, ['status' => Report::STATUS_RESOLVED]);
    $mailer = Mockery::mock(MailFactory::class);
    $mailer->shouldReceive('mailer')->once()->andThrow(new RuntimeException('Simulated mail outage.'));
    app()->instance(MailFactory::class, $mailer);

    expect(fn () => $recipient->notify(new ReportResolvedNotification($report)))
        ->toThrow(RuntimeException::class, 'Simulated mail outage.');

    expect($sms->attempts)->toHaveCount(1)
        ->and($recipient->notifications()->count())->toBe(1);
});
