<?php

use App\Models\ModerationAction;
use App\Models\User;
use App\Models\UserReport;
use App\Notifications\UserReportReviewedNotification;
use Illuminate\Notifications\DatabaseNotification;

function notificationVisibilityCase(User $reporter, User $reported): UserReport
{
    return UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $reported->id,
        'reason' => 'harassment',
        'description' => 'Evidence for a notification visibility test.',
        'status' => 'pending',
    ]);
}

test('dismissed user report creates a safe database notification visible in mobile and web inboxes', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create([
        'email_notifications' => false,
        'sms_notifications' => false,
        'notification_preferences' => ['report_updates' => true],
    ]);
    $case = notificationVisibilityCase($reporter, User::factory()->create());

    $this->actingAs($admin)
        ->put(route('admin.user-reports.update', $case), [
            'decision' => 'dismiss',
            'admin_notes' => 'Private dismissal notes.',
        ])
        ->assertRedirect();

    $notification = DatabaseNotification::query()
        ->where('notifiable_id', $reporter->id)
        ->where('type', UserReportReviewedNotification::class)
        ->sole();

    expect($notification->data)->toMatchArray([
        'user_report_id' => $case->id,
        'status' => 'dismissed',
        'category' => 'reports',
        'title' => 'Your user report was dismissed',
    ])->and(json_encode($notification->data))->not->toContain('Private dismissal notes.');

    $this->actingAs($reporter, 'sanctum')
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'notifications')
        ->assertJsonPath('notifications.0.id', $notification->id)
        ->assertJsonPath('notifications.0.title', 'Your user report was dismissed')
        ->assertJsonPath('notifications.0.category', 'reports')
        ->assertJsonPath('notifications.0.action_route', '/tabs/report')
        ->assertJsonPath('notifications.0.is_read', false)
        ->assertJsonPath('unread_count', 1)
        ->assertJsonMissingPath('notifications.0.data');

    $this->actingAs($reporter)
        ->get(route('notifications'))
        ->assertOk()
        ->assertSee('Your user report was dismissed')
        ->assertDontSee('Private dismissal notes.');
});

test('action taken notification gives the reporter only the safe case result', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create([
        'email_notifications' => false,
        'sms_notifications' => false,
        'notification_preferences' => ['report_updates' => true],
    ]);
    $reported = User::factory()->create([
        'email_notifications' => false,
        'sms_notifications' => false,
        'notification_preferences' => ['system' => false],
    ]);
    $case = notificationVisibilityCase($reporter, $reported);

    $this->actingAs($admin)
        ->put(route('admin.user-reports.update', $case), [
            'decision' => 'community_restriction',
            'duration' => '7',
            'resident_reason' => 'Safe reason for the affected resident.',
            'admin_notes' => 'Private investigation notes.',
        ])
        ->assertRedirect();

    $notification = DatabaseNotification::query()
        ->where('notifiable_id', $reporter->id)
        ->where('type', UserReportReviewedNotification::class)
        ->sole();
    $encoded = json_encode($notification->data);

    expect($notification->data['title'])->toBe('Action taken on your user report')
        ->and($notification->data['status'])->toBe('action_taken')
        ->and($encoded)->not->toContain(
            ModerationAction::COMMUNITY_RESTRICTION,
            'Safe reason for the affected resident.',
            'Private investigation notes.',
            'previous',
        )
        ->and($notification->data)->not->toHaveKeys(['expires_at', 'duration', 'previous_violations', 'moderation_action']);
});

test('disabled report update preference intentionally suppresses the reporter notification', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create([
        'email_notifications' => false,
        'sms_notifications' => false,
        'notification_preferences' => ['report_updates' => false],
    ]);
    $case = notificationVisibilityCase($reporter, User::factory()->create());

    $this->actingAs($admin)
        ->put(route('admin.user-reports.update', $case), ['decision' => 'dismiss'])
        ->assertRedirect();

    expect($reporter->notifications()->where('type', UserReportReviewedNotification::class)->exists())->toBeFalse();
});
