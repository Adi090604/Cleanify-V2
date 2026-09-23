<?php

use App\Models\ActivityLog;
use App\Models\Report;
use App\Models\ReportComment;
use App\Models\ReportLike;
use App\Models\User;
use App\Models\UserReport;
use App\Notifications\UserReportReviewedNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function evidenceCommunityReport(User $owner, array $overrides = []): Report
{
    return Report::query()->create(array_merge([
        'user_id' => $owner->id,
        'location' => 'Barangay Alang-Alang Market',
        'description' => 'Community Report evidence description.',
        'status' => 'pending',
        'priority' => 'medium',
    ], $overrides));
}

test('user report has an optional originating report relationship that nulls without deleting moderation history', function () {
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create();
    $communityReport = evidenceCommunityReport($reportedUser);
    $userReport = UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $reportedUser->id,
        'report_id' => $communityReport->id,
        'reason' => 'inappropriate_content',
        'status' => 'reviewed',
    ]);
    $legacy = UserReport::query()->create([
        'reporter_id' => User::factory()->create()->id,
        'reported_user_id' => $reportedUser->id,
        'report_id' => null,
        'reason' => 'other',
    ]);

    expect($userReport->report->is($communityReport))->toBeTrue()
        ->and($legacy->report)->toBeNull();

    $communityReport->delete();

    expect($userReport->fresh())->not->toBeNull()
        ->and($userReport->fresh()->report_id)->toBeNull()
        ->and($userReport->fresh()->status)->toBe('reviewed');
});

test('web report-user flow submits and stores the exact originating Community Report', function () {
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create(['name' => 'Evidence Owner']);
    $communityReport = evidenceCommunityReport($reportedUser);

    $this->actingAs($reporter)->get(route('community-reports'))
        ->assertOk()
        ->assertSee('name="report_id"', false)
        ->assertSee("openReportUserModal({$reportedUser->id}", false)
        ->assertSee((string) $communityReport->id);

    $this->actingAs($reporter)->postJson(route('users.report', $reportedUser), [
        'reason' => 'inappropriate_content',
        'description' => 'This exact post contains inappropriate material.',
        'report_id' => $communityReport->id,
    ])->assertOk();

    $this->assertDatabaseHas('user_reports', [
        'reporter_id' => $reporter->id,
        'reported_user_id' => $reportedUser->id,
        'report_id' => $communityReport->id,
        'reason' => 'inappropriate_content',
    ]);
    expect($communityReport->fresh())->not->toBeNull();
});

test('web and API reject an originating report owned by someone else', function () {
    $webReporter = User::factory()->create();
    $apiReporter = User::factory()->create();
    $reportedUser = User::factory()->create();
    $otherOwner = User::factory()->create();
    $spoofedReport = evidenceCommunityReport($otherOwner);

    $this->actingAs($webReporter)->postJson(route('users.report', $reportedUser), [
        'reason' => 'spam',
        'report_id' => $spoofedReport->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('report_id');

    $this->actingAs($apiReporter, 'sanctum')->postJson("/api/v1/users/{$reportedUser->id}/report", [
        'reason' => 'spam',
        'report_id' => $spoofedReport->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('report_id');

    $this->assertDatabaseCount('user_reports', 0);
});

test('dismissed web resubmission reuses the same evidence row and clears existing review metadata', function () {
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create();
    $reviewer = User::factory()->create(['is_admin' => true]);
    $communityReport = evidenceCommunityReport($reportedUser);
    $existing = UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $reportedUser->id,
        'report_id' => $communityReport->id,
        'reason' => 'spam',
        'status' => 'dismissed',
        'admin_notes' => 'Old notes',
        'reviewed_by' => $reviewer->id,
        'reviewed_at' => now()->subDay(),
    ]);

    $this->actingAs($reporter)->postJson(route('users.report', $reportedUser), [
        'reason' => 'harassment',
        'description' => 'New complaint.',
        'report_id' => $communityReport->id,
    ])->assertOk();

    $updated = $existing->fresh();
    expect(UserReport::query()->count())->toBe(1)
        ->and($updated->report_id)->toBe($communityReport->id)
        ->and($updated->reason)->toBe('harassment')
        ->and($updated->description)->toBe('New complaint.')
        ->and($updated->status)->toBe('pending')
        ->and($updated->admin_notes)->toBeNull()
        ->and($updated->reviewed_by)->toBeNull()
        ->and($updated->reviewed_at)->toBeNull();
});

test('web self-report and active duplicate behavior remain unchanged', function () {
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create();
    $communityReport = evidenceCommunityReport($reportedUser);

    $this->actingAs($reporter)->postJson(route('users.report', $reporter), [
        'reason' => 'other',
    ])->assertUnprocessable();

    $existing = UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $reportedUser->id,
        'report_id' => $communityReport->id,
        'reason' => 'spam',
        'status' => 'pending',
    ]);

    $this->actingAs($reporter)->postJson(route('users.report', $reportedUser), [
        'reason' => 'harassment',
        'report_id' => $communityReport->id,
    ])->assertUnprocessable()
        ->assertJsonPath('status', 'pending');

    expect(UserReport::query()->count())->toBe(1)
        ->and($existing->fresh()->reason)->toBe('spam')
        ->and($existing->fresh()->report_id)->toBe($communityReport->id);
});

test('API accepts exact evidence and remains backward compatible without report_id', function () {
    $reporter = User::factory()->create();
    $withEvidenceOwner = User::factory()->create();
    $legacyOwner = User::factory()->create();
    $communityReport = evidenceCommunityReport($withEvidenceOwner);

    $this->actingAs($reporter, 'sanctum')->postJson("/api/v1/users/{$withEvidenceOwner->id}/report", [
        'reason' => 'inappropriate_content',
        'report_id' => $communityReport->id,
    ])->assertOk();

    $this->actingAs($reporter, 'sanctum')->postJson("/api/v1/users/{$legacyOwner->id}/report", [
        'reason' => 'other',
    ])->assertOk();

    expect(UserReport::query()->where('reported_user_id', $withEvidenceOwner->id)->value('report_id'))
        ->toBe($communityReport->id)
        ->and(UserReport::query()->where('reported_user_id', $legacyOwner->id)->value('report_id'))
        ->toBeNull();
});

test('API dismissed resubmission reuses the originating report evidence', function () {
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create();
    $communityReport = evidenceCommunityReport($reportedUser);
    $existing = UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $reportedUser->id,
        'report_id' => $communityReport->id,
        'reason' => 'spam',
        'status' => 'dismissed',
    ]);

    $this->actingAs($reporter, 'sanctum')->postJson("/api/v1/users/{$reportedUser->id}/report", [
        'reason' => 'inappropriate_content',
        'report_id' => $communityReport->id,
    ])->assertOk();

    expect($existing->fresh()->report_id)->toBe($communityReport->id)
        ->and($existing->fresh()->status)->toBe('pending')
        ->and(UserReport::query()->count())->toBe(1);
});

test('admin detail modal exposes real evidence and safely handles legacy missing content', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create(['name' => 'Reported Resident']);
    $communityReport = evidenceCommunityReport($reportedUser, [
        'image_path' => 'reports/reported-content.jpg',
        'status' => 'rejected',
    ]);
    Storage::disk('public')->put('reports/reported-content.jpg', 'image');
    UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $reportedUser->id,
        'report_id' => $communityReport->id,
        'reason' => 'inappropriate_content',
    ]);
    UserReport::query()->create([
        'reporter_id' => User::factory()->create()->id,
        'reported_user_id' => $reportedUser->id,
        'report_id' => null,
        'reason' => 'other',
    ]);

    $this->actingAs($admin)->get(route('admin.user-reports'))
        ->assertOk()
        ->assertViewHas('reports', fn ($reports) => $reports->firstWhere('report_id', $communityReport->id)?->relationLoaded('report'))
        ->assertSee('Reported Content')
        ->assertSee('Community Report evidence description.')
        ->assertSee('Barangay Alang-Alang Market')
        ->assertSee('reported-content.jpg')
        ->assertSee('Original Community Report is no longer available.')
        ->assertSee('Delete Community Report');
});

test('admin moderation status updates and review notifications remain unchanged', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create();
    $communityReport = evidenceCommunityReport($reportedUser);
    $userReport = UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $reportedUser->id,
        'report_id' => $communityReport->id,
        'reason' => 'spam',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)->put(route('admin.user-reports.update', $userReport), [
        'status' => 'reviewed',
        'admin_notes' => 'Evidence reviewed.',
    ])->assertRedirect();

    expect($userReport->fresh()->status)->toBe('reviewed')
        ->and($userReport->fresh()->report_id)->toBe($communityReport->id)
        ->and($userReport->fresh()->reviewed_by)->toBe($admin->id);
    Notification::assertSentTo($reporter, UserReportReviewedNotification::class);
});

test('admin evidence deletion reuses full cleanup while retaining moderation state', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create();
    $other = User::factory()->create();
    $communityReport = evidenceCommunityReport($reportedUser, ['image_path' => 'reports/evidence-delete.jpg']);
    Storage::disk('public')->put($communityReport->image_path, 'image');
    ReportLike::query()->create(['report_id' => $communityReport->id, 'user_id' => $other->id]);
    ReportComment::query()->create([
        'report_id' => $communityReport->id,
        'user_id' => $other->id,
        'comment' => 'Dependent comment',
    ]);
    $communityReport->followers()->attach($other->id);
    $notification = DatabaseNotification::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\ReportResolvedNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $reportedUser->id,
        'data' => ['report_id' => $communityReport->id, 'category' => 'reports'],
    ]);
    $activity = ActivityLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'report.resolved',
        'model_type' => Report::class,
        'model_id' => $communityReport->id,
    ]);
    $userReport = UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $reportedUser->id,
        'report_id' => $communityReport->id,
        'reason' => 'inappropriate_content',
        'description' => 'Keep this moderation evidence summary.',
        'status' => 'reviewed',
        'admin_notes' => 'Keep these notes.',
        'reviewed_by' => $admin->id,
        'reviewed_at' => now()->subHour(),
    ]);

    $this->actingAs($other)
        ->delete(route('admin.user-reports.report.destroy', $userReport))
        ->assertForbidden();
    expect($communityReport->fresh())->not->toBeNull();

    $this->actingAs($admin)
        ->delete(route('admin.user-reports.report.destroy', $userReport))
        ->assertRedirect()
        ->assertSessionHas('success', 'Community Report permanently deleted. The user report was retained.');

    $retained = $userReport->fresh();
    expect($communityReport->fresh())->toBeNull()
        ->and($retained)->not->toBeNull()
        ->and($retained->report_id)->toBeNull()
        ->and($retained->status)->toBe('reviewed')
        ->and($retained->description)->toBe('Keep this moderation evidence summary.')
        ->and($retained->admin_notes)->toBe('Keep these notes.')
        ->and($retained->reviewed_by)->toBe($admin->id)
        ->and($retained->reviewed_at)->not->toBeNull();
    $this->assertDatabaseMissing('report_likes', ['report_id' => $communityReport->id]);
    $this->assertDatabaseMissing('report_comments', ['report_id' => $communityReport->id]);
    $this->assertDatabaseMissing('report_followers', ['report_id' => $communityReport->id]);
    $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    $this->assertDatabaseMissing('activity_logs', ['id' => $activity->id]);
    Storage::disk('public')->assertMissing('reports/evidence-delete.jpg');
});
