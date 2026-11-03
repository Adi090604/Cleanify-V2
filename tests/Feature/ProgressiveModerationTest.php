<?php

use App\Models\ModerationAction;
use App\Models\Report;
use App\Models\User;
use App\Models\UserReport;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\ModerationActionNotification;
use App\Notifications\UserReportReviewedNotification;
use App\Services\ModerationService;
use Illuminate\Support\Facades\Notification;

function moderationReport(User $reportedUser, ?User $reporter = null, array $attributes = []): UserReport
{
    return UserReport::query()->create(array_merge([
        'reporter_id' => ($reporter ?? User::factory()->create())->id,
        'reported_user_id' => $reportedUser->id,
        'reason' => 'harassment',
        'description' => 'Evidence supplied for administrator review.',
        'status' => 'pending',
    ], $attributes));
}

function moderationCommunityReport(?User $author = null): Report
{
    return Report::query()->create([
        'user_id' => ($author ?? User::factory()->create())->id,
        'barangay' => 'Barangay Washington',
        'location' => 'Covered court',
        'description' => 'A cleanliness report used by moderation tests.',
        'status' => 'pending',
        'priority' => 'medium',
    ]);
}

function applyModerationDecision($test, User $admin, UserReport $report, array $decision): void
{
    $test->actingAs($admin)
        ->put(route('admin.user-reports.update', $report), $decision)
        ->assertRedirect();
}

test('raw and dismissed resident reports never punish the reported resident', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $reported = User::factory()->create();

    moderationReport($reported);
    moderationReport($reported);
    $dismissed = moderationReport($reported);

    expect($reported->confirmedViolationCount())->toBe(0)
        ->and($reported->activeCommunityRestriction())->toBeNull()
        ->and($reported->activeReportSubmissionRestriction())->toBeNull()
        ->and($reported->isSuspended())->toBeFalse();

    applyModerationDecision($this, $admin, $dismissed, [
        'decision' => 'dismiss',
        'admin_notes' => 'Unsubstantiated allegation.',
    ]);

    expect($dismissed->fresh()->status)->toBe('dismissed')
        ->and($reported->confirmedViolationCount())->toBe(0)
        ->and(ModerationAction::query()->count())->toBe(0);
});

test('a warning is recorded and leaves all resident capabilities available', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $reported = User::factory()->create();
    $reporter = User::factory()->create();
    $case = moderationReport($reported, $reporter);
    $communityReport = moderationCommunityReport();

    applyModerationDecision($this, $admin, $case, [
        'decision' => 'warning',
        'resident_reason' => 'Please keep community discussions respectful.',
        'admin_notes' => 'Private witness identity and investigation notes.',
    ]);

    $action = ModerationAction::query()->sole();
    expect($case->fresh()->status)->toBe('action_taken')
        ->and($action->action_type)->toBe(ModerationAction::WARNING)
        ->and($action->admin_notes)->toContain('Private witness')
        ->and($reported->confirmedViolationCount())->toBe(1)
        ->and($reported->fresh()->isSuspended())->toBeFalse();

    $this->actingAs($reported)
        ->post(route('reports.store'), [
            'barangay' => 'Barangay Washington',
            'location' => 'Public market',
            'description' => 'Valid waste concern after a warning.',
        ])->assertRedirect(route('dashboard'));

    $this->actingAs($reported)->postJson(route('reports.comment', $communityReport), [
        'comment' => 'A respectful comment.',
    ])->assertOk();
    $this->actingAs($reported)->postJson(route('reports.like', $communityReport))->assertOk();
    $this->actingAs($reported)->postJson(route('reports.follow', $communityReport))->assertOk();

    Notification::assertSentTo($reported, ModerationActionNotification::class, function ($notification) use ($reported): bool {
        $payload = $notification->toArray($reported);

        return str_contains($payload['message'], 'warning')
            && ! str_contains(json_encode($payload), 'Private witness');
    });
    Notification::assertSentTo($reporter, UserReportReviewedNotification::class);
});

test('community restriction makes Community Reports read only while preserving other services and expires at runtime', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    $case = moderationReport($resident);
    $communityReport = moderationCommunityReport();

    applyModerationDecision($this, $admin, $case, [
        'decision' => 'community_restriction',
        'duration' => '3',
    ]);

    $message = 'Community Reports access is temporarily read-only until';
    $this->actingAs($resident)->postJson(route('reports.comment', $communityReport), ['comment' => 'Blocked'])
        ->assertForbidden()
        ->assertJsonPath('message', fn ($value) => str_contains($value, $message));
    $this->actingAs($resident)->postJson(route('reports.like', $communityReport))->assertForbidden();
    $this->actingAs($resident)->postJson(route('reports.follow', $communityReport))->assertForbidden();
    $this->actingAs($resident, 'sanctum')->postJson("/api/v1/reports/{$communityReport->id}/comment", ['comment' => 'Blocked'])->assertForbidden();

    $this->actingAs($resident)->from(route('dashboard'))->post(route('reports.store'), [
        'barangay' => 'Barangay Washington',
        'location' => 'Riverside',
        'description' => 'A new report is blocked while Community Reports is read only.',
    ])->assertRedirect(route('dashboard'))->assertSessionHas('error');
    $this->actingAs($resident)->get(route('garbage-schedule'))->assertOk();
    $this->actingAs($resident)->get(route('tracker'))->assertOk();

    $action = ModerationAction::query()->sole();
    $action->update(['expires_at' => now()->subMinute()]);

    $this->actingAs($resident)->postJson(route('reports.comment', $communityReport), ['comment' => 'Allowed after expiration'])->assertOk();
    expect($resident->activeCommunityRestriction())->toBeNull();
});

test('report submission restriction blocks only new web and API reports and expires at runtime', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    $case = moderationReport($resident);
    $existing = moderationCommunityReport($resident);
    $otherReport = moderationCommunityReport();

    applyModerationDecision($this, $admin, $case, [
        'decision' => 'report_submission_restriction',
        'duration' => '7',
    ]);

    $payload = [
        'barangay' => 'Barangay Washington',
        'location' => 'Riverside',
        'description' => 'This new report should be blocked.',
    ];
    $this->actingAs($resident)->from(route('dashboard'))->post(route('reports.store'), $payload)
        ->assertRedirect(route('dashboard'))->assertSessionHas('error');
    $this->actingAs($resident, 'sanctum')->postJson('/api/v1/reports', $payload)
        ->assertForbidden()->assertJsonPath('message', fn ($message) => str_contains($message, 'report submission'));

    $this->actingAs($resident)->postJson(route('reports.comment', $otherReport), ['comment' => 'Community access remains available.'])->assertOk();
    $this->actingAs($resident)->postJson(route('reports.like', $otherReport))->assertOk();
    $this->actingAs($resident, 'sanctum')->getJson("/api/v1/reports/{$existing->id}")->assertOk();

    ModerationAction::query()->sole()->update(['expires_at' => now()->subMinute()]);
    $this->actingAs($resident, 'sanctum')->postJson('/api/v1/reports', $payload)->assertCreated();
    expect($resident->activeReportSubmissionRestriction())->toBeNull();
});

test('confirmed case counting drives recommendations without overriding admin judgment', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    $moderation = app(ModerationService::class);

    expect($moderation->recommendedAction($resident)['label'])->toBe('Warning');

    $first = moderationReport($resident);
    applyModerationDecision($this, $admin, $first, ['decision' => 'warning']);
    expect($resident->confirmedViolationCount())->toBe(1)
        ->and($moderation->recommendedAction($resident)['label'])->toBe('3 Day Community Restriction');

    $dismissed = moderationReport($resident);
    applyModerationDecision($this, $admin, $dismissed, ['decision' => 'dismiss']);
    expect($resident->confirmedViolationCount())->toBe(1);

    $second = moderationReport($resident);
    applyModerationDecision($this, $admin, $second, ['decision' => 'warning']);
    expect($resident->confirmedViolationCount())->toBe(2)
        ->and($moderation->recommendedAction($resident)['label'])->toBe('7 Day Community Restriction');

    $this->actingAs($admin)->put(route('admin.user-reports.update', $second), ['decision' => 'warning'])
        ->assertSessionHasErrors('decision');
    expect($resident->confirmedViolationCount())->toBe(2);

    $severeResident = User::factory()->create();
    $severe = moderationReport($severeResident);
    applyModerationDecision($this, $admin, $severe, ['decision' => 'suspension']);
    expect($severeResident->fresh()->isSuspended())->toBeTrue()
        ->and($severeResident->confirmedViolationCount())->toBe(1);
});

test('suspension blocks web and mobile sessions revokes tokens and can be lifted without deleting history', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    $content = moderationCommunityReport($resident);
    $case = moderationReport($resident);
    $token = $resident->createToken('mobile-app')->plainTextToken;

    applyModerationDecision($this, $admin, $case, ['decision' => 'suspension']);

    expect($resident->fresh()->isSuspended())->toBeTrue();
    $this->assertDatabaseCount('personal_access_tokens', 0);
    auth()->guard('web')->logout();
    $this->post('/login', ['email' => $resident->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->postJson('/api/v1/auth/login', ['email' => $resident->email, 'password' => 'password'])
        ->assertForbidden();
    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    $this->actingAs($resident->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    $this->actingAs($admin)->get(route('admin.user-reports'))->assertOk();

    $action = ModerationAction::query()->where('action_type', ModerationAction::SUSPENSION)->sole();
    $this->actingAs($admin)->post(route('admin.moderation-actions.lift', $action))->assertRedirect();

    expect($resident->fresh()->isSuspended())->toBeFalse()
        ->and(ModerationAction::query()->where('action_type', ModerationAction::UNSUSPENDED)->exists())->toBeTrue()
        ->and($content->fresh())->not->toBeNull();
    $this->postJson('/api/v1/auth/login', ['email' => $resident->email, 'password' => 'password'])->assertOk();
});

test('moderation notifications respect system email and sms preferences and never expose admin notes', function () {
    $smsEnabled = User::factory()->create([
        'email_notifications' => false,
        'sms_notifications' => true,
        'notification_preferences' => ['system' => true],
    ]);
    $muted = User::factory()->create([
        'email_notifications' => true,
        'sms_notifications' => true,
        'notification_preferences' => ['system' => false],
    ]);
    $databaseOnly = User::factory()->create([
        'email_notifications' => false,
        'sms_notifications' => false,
        'notification_preferences' => ['system' => true],
    ]);
    $emailEnabled = User::factory()->create([
        'email_notifications' => true,
        'sms_notifications' => false,
        'notification_preferences' => ['system' => true],
    ]);
    $admin = User::factory()->create(['is_admin' => true]);
    $case = moderationReport($smsEnabled);
    $action = ModerationAction::query()->create([
        'user_id' => $smsEnabled->id,
        'user_report_id' => $case->id,
        'action_type' => ModerationAction::COMMUNITY_RESTRICTION,
        'resident_reason' => 'Repeated abusive comments.',
        'admin_notes' => 'Private investigator details.',
        'applied_by' => $admin->id,
        'starts_at' => now(),
        'expires_at' => now()->addDays(3),
    ]);
    $notification = new ModerationActionNotification($action);
    $mail = $notification->toMail($smsEnabled);
    $renderedMail = view($mail->view, $mail->viewData)->render();

    expect($notification->via($smsEnabled))->toContain('database')
        ->toContain(SmsChannel::class)
        ->not->toContain('mail')
        ->and($notification->via($muted))->toBe([])
        ->and($notification->via($emailEnabled))->toContain('database')->toContain('mail')->not->toContain(SmsChannel::class)
        ->and(json_encode($notification->toArray($smsEnabled)))->not->toContain('Private investigator')
        ->and($notification->residentMessage())->toContain('Community Reports access is temporarily read-only')
        ->and($renderedMail)->toContain('CLEANIFY', 'Community Restriction', 'Repeated abusive comments.')
        ->not->toContain('Private investigator')
        ->and($notification->toSms($smsEnabled))->toContain('Community Reports is read-only', $action->expires_at->format('M d, Y'))
        ->not->toContain('Private investigator');

    $databaseCase = moderationReport($databaseOnly);
    $databaseAction = ModerationAction::query()->create([
        'user_id' => $databaseOnly->id,
        'user_report_id' => $databaseCase->id,
        'action_type' => ModerationAction::WARNING,
        'admin_notes' => 'Never expose this private note.',
        'applied_by' => $admin->id,
        'starts_at' => now(),
    ]);
    $databaseOnly->notify(new ModerationActionNotification($databaseAction));

    expect($databaseOnly->notifications)->toHaveCount(1)
        ->and(json_encode($databaseOnly->notifications->first()->data))->not->toContain('Never expose');
});

test('admin can explicitly lift an active temporary restriction while preserving its history', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    $case = moderationReport($resident);

    applyModerationDecision($this, $admin, $case, [
        'decision' => 'community_restriction',
        'duration' => '7',
    ]);
    $restriction = ModerationAction::query()->sole();

    $this->actingAs($admin)
        ->post(route('admin.moderation-actions.lift', $restriction), ['admin_notes' => 'Appeal approved.'])
        ->assertRedirect();

    expect($restriction->fresh()->lifted_at)->not->toBeNull()
        ->and($resident->activeCommunityRestriction())->toBeNull()
        ->and(ModerationAction::query()->where('action_type', ModerationAction::RESTRICTION_LIFTED)->exists())->toBeTrue()
        ->and(ModerationAction::query()->count())->toBe(2);
});

test('admin review UI separates safe reasons from private notes and presents guidance', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    moderationReport(User::factory()->create());

    $this->actingAs($admin)
        ->get(route('admin.user-reports'))
        ->assertOk()
        ->assertSee('Previous Confirmed Violations')
        ->assertSee('Recommended Action')
        ->assertSee('Resident Safe Reason')
        ->assertSee('Internal Admin Notes')
        ->assertSee('Community Restriction')
        ->assertDontSee('>Report Submission Restriction</option>', false)
        ->assertSee('Suspend Account');
});

test('residents cannot call administrator moderation endpoints', function () {
    $resident = User::factory()->create();
    $reported = User::factory()->create();
    $case = moderationReport($reported);

    $this->actingAs($resident)
        ->put(route('admin.user-reports.update', $case), ['decision' => 'warning'])
        ->assertForbidden();

    expect($case->fresh()->status)->toBe('pending')
        ->and(ModerationAction::query()->count())->toBe(0);
});

test('legacy admin user controls use suspension semantics and preserve moderation history', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    $resident->createToken('mobile-app');

    $this->actingAs($admin)->post(route('admin.users.ban', $resident))->assertRedirect(route('admin.users'));

    expect($resident->fresh()->isSuspended())->toBeTrue()
        ->and(ModerationAction::query()->where('action_type', ModerationAction::SUSPENSION)->exists())->toBeTrue();
    $this->assertDatabaseCount('personal_access_tokens', 0);

    $this->actingAs($admin)->post(route('admin.users.unban', $resident))->assertRedirect(route('admin.users'));

    expect($resident->fresh()->isSuspended())->toBeFalse()
        ->and(ModerationAction::query()->where('action_type', ModerationAction::UNSUSPENDED)->exists())->toBeTrue();
});
