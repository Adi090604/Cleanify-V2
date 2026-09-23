<?php

use App\Models\User;
use App\Models\UserReport;
use Illuminate\Support\Facades\Notification;

test('mobile user reporting requires Sanctum authentication', function () {
    $reportedUser = User::factory()->create();

    $this->postJson("/api/v1/users/{$reportedUser->id}/report", [
        'reason' => 'spam',
    ])->assertUnauthorized();

    $this->assertDatabaseCount('user_reports', 0);
});

test('an authenticated regular user can report another user', function () {
    Notification::fake();
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create();

    $this->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/users/{$reportedUser->id}/report", [
            'reason' => 'inappropriate_content',
            'description' => 'The report photo contains inappropriate material.',
        ])->assertOk()
        ->assertExactJson([
            'success' => true,
            'message' => 'User reported successfully. Our team will review this report.',
        ]);

    $this->assertDatabaseHas('user_reports', [
        'reporter_id' => $reporter->id,
        'reported_user_id' => $reportedUser->id,
        'reason' => 'inappropriate_content',
        'description' => 'The report photo contains inappropriate material.',
        'status' => 'pending',
    ]);
    expect($reportedUser->fresh()->isBanned())->toBeFalse();
    Notification::assertNothingSent();
});

test('a user cannot report themselves through the mobile API', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/users/{$user->id}/report", [
            'reason' => 'other',
        ])->assertUnprocessable()
        ->assertExactJson([
            'message' => 'You cannot report yourself.',
        ]);

    $this->assertDatabaseCount('user_reports', 0);
});

test('banned users cannot report users through the mobile API', function () {
    $reporter = User::factory()->create(['banned_at' => now()]);
    $reportedUser = User::factory()->create();

    $this->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/users/{$reportedUser->id}/report", [
            'reason' => 'spam',
        ])->assertForbidden()
        ->assertExactJson([
            'message' => 'Your account has been banned. Please contact an administrator.',
        ]);

    $this->assertDatabaseCount('user_reports', 0);
});

test('admin users cannot report users through the regular mobile API', function () {
    $reporter = User::factory()->create(['is_admin' => true]);
    $reportedUser = User::factory()->create();

    $this->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/users/{$reportedUser->id}/report", [
            'reason' => 'spam',
        ])->assertForbidden()
        ->assertExactJson([
            'message' => 'Admin accounts can only sign in through the web Admin Portal.',
        ]);

    $this->assertDatabaseCount('user_reports', 0);
});

test('every existing user report reason is accepted by the mobile API', function (string $reason) {
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create();

    $this->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/users/{$reportedUser->id}/report", [
            'reason' => $reason,
        ])->assertOk();

    $this->assertDatabaseHas('user_reports', [
        'reporter_id' => $reporter->id,
        'reported_user_id' => $reportedUser->id,
        'reason' => $reason,
        'description' => null,
    ]);
})->with(['spam', 'harassment', 'inappropriate_content', 'fake_account', 'other']);

test('mobile user report validation rejects invalid reasons', function () {
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create();

    $this->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/users/{$reportedUser->id}/report", [
            'reason' => 'made_up_reason',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    $this->assertDatabaseCount('user_reports', 0);
});

test('description is optional and accepts exactly 1000 characters', function () {
    $reporter = User::factory()->create();
    $withoutDescription = User::factory()->create();
    $withMaximumDescription = User::factory()->create();

    $this->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/users/{$withoutDescription->id}/report", [
            'reason' => 'spam',
        ])->assertOk();

    $this->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/users/{$withMaximumDescription->id}/report", [
            'reason' => 'other',
            'description' => str_repeat('a', 1000),
        ])->assertOk();

    expect(UserReport::query()->where('reported_user_id', $withoutDescription->id)->value('description'))->toBeNull()
        ->and(UserReport::query()->where('reported_user_id', $withMaximumDescription->id)->value('description'))->toHaveLength(1000);
});

test('description over 1000 characters is rejected', function () {
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create();

    $this->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/users/{$reportedUser->id}/report", [
            'reason' => 'other',
            'description' => str_repeat('a', 1001),
        ])->assertUnprocessable()
        ->assertJsonValidationErrors('description');

    $this->assertDatabaseCount('user_reports', 0);
});

test('report ownership comes from authentication and route binding', function () {
    $reporter = User::factory()->create();
    $spoofedReporter = User::factory()->create();
    $reportedUser = User::factory()->create();
    $spoofedReportedUser = User::factory()->create();
    $spoofedReviewer = User::factory()->create(['is_admin' => true]);

    $this->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/users/{$reportedUser->id}/report", [
            'reason' => 'harassment',
            'reporter_id' => $spoofedReporter->id,
            'reported_user_id' => $spoofedReportedUser->id,
            'status' => 'action_taken',
            'admin_notes' => 'Spoofed notes',
            'reviewed_by' => $spoofedReviewer->id,
            'reviewed_at' => now()->toIso8601String(),
        ])->assertOk();

    $report = UserReport::query()->sole();
    expect($report->reporter_id)->toBe($reporter->id)
        ->and($report->reported_user_id)->toBe($reportedUser->id)
        ->and($report->status)->toBe('pending')
        ->and($report->admin_notes)->toBeNull()
        ->and($report->reviewed_by)->toBeNull()
        ->and($report->reviewed_at)->toBeNull();
});

test('active duplicate user reports are rejected without creating a second row', function (string $status) {
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create();
    $existing = UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $reportedUser->id,
        'reason' => 'spam',
        'status' => $status,
    ]);

    $this->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/users/{$reportedUser->id}/report", [
            'reason' => 'harassment',
        ])->assertUnprocessable()
        ->assertExactJson([
            'message' => 'You have already reported this user. Please wait for admin review.',
            'status' => $status,
        ]);

    expect(UserReport::query()->count())->toBe(1)
        ->and($existing->fresh()->reason)->toBe('spam')
        ->and($existing->fresh()->status)->toBe($status);
})->with(['pending', 'reviewed', 'action_taken']);

test('a dismissed report is reused and reset for resubmission', function () {
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create();
    $reviewer = User::factory()->create(['is_admin' => true]);
    $existing = UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $reportedUser->id,
        'reason' => 'spam',
        'description' => 'Old description',
        'status' => 'dismissed',
        'admin_notes' => 'Previously dismissed',
        'reviewed_by' => $reviewer->id,
        'reviewed_at' => now()->subDay(),
    ]);

    $this->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/users/{$reportedUser->id}/report", [
            'reason' => 'fake_account',
            'description' => 'New evidence for review',
        ])->assertOk()
        ->assertExactJson([
            'success' => true,
            'message' => 'User reported successfully. Our team will review this report.',
        ]);

    $updated = UserReport::query()->sole();
    expect($updated->id)->toBe($existing->id)
        ->and($updated->reason)->toBe('fake_account')
        ->and($updated->description)->toBe('New evidence for review')
        ->and($updated->status)->toBe('pending')
        ->and($updated->admin_notes)->toBeNull()
        ->and($updated->reviewed_by)->toBeNull()
        ->and($updated->reviewed_at)->toBeNull();
});

test('the existing web user report flow remains available', function () {
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create();

    $this->actingAs($reporter)
        ->post(route('users.report', $reportedUser), [
            'reason' => 'spam',
            'description' => 'Submitted through the web route.',
        ])->assertRedirect()
        ->assertSessionHas('success', 'User reported successfully. Our team will review this report.');

    $this->assertDatabaseHas('user_reports', [
        'reporter_id' => $reporter->id,
        'reported_user_id' => $reportedUser->id,
        'reason' => 'spam',
    ]);
});

test('mobile-created reports appear in the existing admin user reports page', function () {
    $reporter = User::factory()->create();
    $reportedUser = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($reporter, 'sanctum')
        ->postJson("/api/v1/users/{$reportedUser->id}/report", [
            'reason' => 'other',
        ])->assertOk();

    $createdReport = UserReport::query()->sole();

    $this->actingAs($admin)
        ->get(route('admin.user-reports'))
        ->assertOk()
        ->assertViewHas('reports', fn ($reports) => $reports->contains('id', $createdReport->id));
});
