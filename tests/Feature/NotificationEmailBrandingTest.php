<?php

use App\Models\Report;
use App\Models\Schedule;
use App\Models\ServiceZone;
use App\Models\User;
use App\Models\UserReport;
use App\Notifications\ReportInProgressNotification;
use App\Notifications\ReportRejectedNotification;
use App\Notifications\ReportResolvedNotification;
use App\Notifications\ScheduleCreatedNotification;
use App\Notifications\ScheduleUpdatedNotification;
use App\Notifications\ServiceAreaRequestStatusNotification;
use App\Notifications\TestEmailNotification;
use App\Notifications\UserReportReviewedNotification;
use Illuminate\Notifications\Notification;

function renderCleanifyNotification(Notification $notification, User $recipient): string
{
    $mail = $notification->toMail($recipient);

    expect($mail->view)->toBe('emails.cleanify-notification');

    return view($mail->view, $mail->viewData)->render();
}

test('every Cleanify email notification uses the shared branding while preserving mail preferences', function () {
    $recipient = User::factory()->create([
        'name' => 'Maria Resident',
        'email_notifications' => true,
        'notification_preferences' => [
            'report_updates' => true,
            'schedule_reminders' => true,
            'system' => true,
        ],
    ]);
    $emailDisabled = User::factory()->create([
        'email_notifications' => false,
        'notification_preferences' => [
            'report_updates' => true,
            'schedule_reminders' => true,
            'system' => true,
        ],
    ]);
    $admin = User::factory()->create(['is_admin' => true, 'name' => 'Cleanify Admin']);
    $reportedUser = User::factory()->create(['name' => 'Reported Resident']);

    $report = Report::query()->create([
        'user_id' => $recipient->id,
        'location' => 'Barangay One Market',
        'description' => 'Uncollected waste beside the market.',
        'status' => Report::STATUS_RESOLVED,
        'admin_notes' => 'Waste was collected.',
        'rejection_reason' => 'Insufficient location information.',
        'resolved_by' => $admin->id,
    ]);
    $schedule = Schedule::query()->create([
        'area' => 'Barangay One',
        'schedule_type' => 'specific_date',
        'specific_date' => '2026-11-03',
        'days' => null,
        'time_start' => '08:00',
        'time_end' => '09:00',
        'truck' => 'TRK-01',
        'status' => 'active',
    ]);
    $areaRequest = $recipient->serviceAreaRequests()->create([
        'area_name' => 'Purok Riverside',
        'normalized_area_name' => 'purok riverside',
        'barangay' => 'Barangay One',
    ]);
    $areaRequest->forceFill(['status' => 'approved'])->save();
    $zone = ServiceZone::query()->create([
        'name' => 'Purok Riverside',
        'barangay' => 'Barangay One',
        'status' => 'active',
    ]);
    $userReport = UserReport::query()->create([
        'reporter_id' => $recipient->id,
        'reported_user_id' => $reportedUser->id,
        'reason' => 'spam',
        'description' => 'Repeated unrelated comments.',
        'status' => 'reviewed',
        'admin_notes' => 'Evidence reviewed.',
        'reviewed_by' => $admin->id,
        'reviewed_at' => now(),
    ]);

    $notifications = [
        'report in progress' => [new ReportInProgressNotification($report), ['Report In Progress', 'Barangay One Market']],
        'report resolved' => [new ReportResolvedNotification($report), ['Report Resolved', 'Waste was collected.']],
        'report rejected' => [new ReportRejectedNotification($report), ['Report Status Update', 'Insufficient location information.']],
        'new schedule' => [new ScheduleCreatedNotification($schedule), ['New Garbage Collection Schedule', 'TRK-01']],
        'schedule updated' => [new ScheduleUpdatedNotification($schedule), ['Collection Schedule Updated', '8:00 AM - 9:00 AM']],
        'service area approved' => [new ServiceAreaRequestStatusNotification($areaRequest, ServiceAreaRequestStatusNotification::EVENT_APPROVED), ['Service Area Request Approved']],
        'service area rejected' => [new ServiceAreaRequestStatusNotification($areaRequest, ServiceAreaRequestStatusNotification::EVENT_REJECTED), ['Service Area Request Not Approved']],
        'service area created' => [new ServiceAreaRequestStatusNotification($areaRequest, ServiceAreaRequestStatusNotification::EVENT_ZONE_CREATED, $zone, true), ['Service Area Added', 'Purok Riverside - Barangay One']],
        'user report reviewed' => [new UserReportReviewedNotification($userReport), ['User Report Status Update', 'Reported Resident']],
        'admin test email' => [new TestEmailNotification, ['Test Email Notification', 'Cleanify Admin Panel']],
    ];

    foreach ($notifications as $label => [$notification, $expectedContent]) {
        $enabledChannels = $notification->via($recipient);
        $disabledChannels = $notification->via($emailDisabled);
        $rendered = renderCleanifyNotification($notification, $recipient);

        expect($enabledChannels, $label.' should include mail when enabled')->toContain('mail')
            ->and($disabledChannels, $label.' should omit mail when disabled')->not->toContain('mail')
            ->and($rendered, $label.' should use Cleanify branding')->toContain('CLEANIFY', 'One Click. One Report. One Clean Community.')
            ->and($rendered, $label.' should not expose Laravel branding')->not->toContain('Laravel');

        foreach ($expectedContent as $content) {
            expect($rendered, $label.' should preserve its content')->toContain($content);
        }
    }
});

test('database delivery and payload remain unchanged when notification email is disabled', function () {
    $recipient = User::factory()->create([
        'email_notifications' => false,
        'notification_preferences' => ['report_updates' => true],
    ]);
    $report = Report::query()->create([
        'user_id' => $recipient->id,
        'location' => 'Database Notification Location',
        'description' => 'Database notification description.',
        'status' => Report::STATUS_IN_PROGRESS,
    ]);

    $recipient->notify(new ReportInProgressNotification($report));

    $stored = $recipient->fresh()->notifications()->sole();
    expect($stored->data)->toMatchArray([
        'report_id' => $report->id,
        'status' => Report::STATUS_IN_PROGRESS,
        'category' => 'reports',
        'title' => 'Your report is in progress',
        'url' => '/community-reports',
    ]);
});
