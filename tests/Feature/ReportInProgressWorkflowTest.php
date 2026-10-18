<?php

use App\Models\ActivityLog;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportInProgressNotification;
use App\Notifications\ReportRejectedNotification;
use App\Notifications\ReportResolvedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

function enableSqliteFieldFunctionForReportWorkflow(): void
{
    if (DB::getDriverName() !== 'sqlite') {
        return;
    }

    DB::connection()->getPdo()->sqliteCreateFunction('FIELD', function ($value, ...$values) {
        $position = array_search($value, $values, true);

        return $position === false ? 0 : $position + 1;
    });
}

function workflowReport(User $owner, array $attributes = []): Report
{
    $createdAt = $attributes['created_at'] ?? now();
    unset($attributes['created_at']);

    $report = Report::query()->create(array_merge([
        'user_id' => $owner->id,
        'location' => 'Workflow Location',
        'description' => 'Report workflow fixture',
        'status' => Report::STATUS_PENDING,
        'priority' => 'medium',
    ], $attributes));
    $report->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

    return $report->fresh();
}

beforeEach(function () {
    enableSqliteFieldFunctionForReportWorkflow();
});

test('new reports start pending and the model presents the canonical in progress state', function () {
    $owner = User::factory()->create();
    $report = Report::query()->create([
        'user_id' => $owner->id,
        'location' => 'Default Status Location',
        'description' => 'Uses the database default status.',
    ]);

    expect($report->fresh()->status)->toBe(Report::STATUS_PENDING)
        ->and($report->resolved_at)->toBeNull();

    $report->update(['status' => Report::STATUS_IN_PROGRESS]);

    expect($report->fresh()->status_label)->toBe('In Progress')
        ->and($report->fresh()->resolved_at)->toBeNull()
        ->and($report->fresh()->getStatusBadgeBgClass())->toContain('blue');
});

test('admin marks a pending report in progress with activity and owner and follower notifications', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $follower = User::factory()->create();
    $report = workflowReport($owner);
    $report->followers()->attach($follower);

    $this->actingAs($admin)->post(route('admin.reports.in-progress', $report))
        ->assertRedirect(route('admin.reports'))
        ->assertSessionHas('success');

    expect($report->fresh()->status)->toBe(Report::STATUS_IN_PROGRESS)
        ->and($report->fresh()->resolved_at)->toBeNull()
        ->and(ActivityLog::query()->where('action', 'report.in_progress')->where('model_id', $report->id)->exists())->toBeTrue();
    Notification::assertSentTo($owner, ReportInProgressNotification::class, fn ($notification) => ! $notification->isFollower);
    Notification::assertSentTo($follower, ReportInProgressNotification::class, fn ($notification) => $notification->isFollower);
});

test('in progress notification persists in inbox payload and respects report and email preferences', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create([
        'email_notifications' => false,
        'notification_preferences' => ['report_updates' => true],
    ]);
    $muted = User::factory()->create([
        'email_notifications' => true,
        'notification_preferences' => ['report_updates' => false],
    ]);
    $emailEnabled = User::factory()->create([
        'email_notifications' => true,
        'notification_preferences' => ['report_updates' => true],
    ]);
    $report = workflowReport($owner);

    expect((new ReportInProgressNotification($report))->via($owner))->toBe(['database'])
        ->and((new ReportInProgressNotification($report))->via($muted))->toBe([])
        ->and((new ReportInProgressNotification($report))->via($emailEnabled))->toBe(['database', 'mail']);

    $this->actingAs($admin)->post(route('admin.reports.in-progress', $report))->assertRedirect();

    $stored = $owner->notifications()->first();
    expect($stored)->not->toBeNull()
        ->and($stored->data['status'])->toBe(Report::STATUS_IN_PROGRESS)
        ->and($stored->data['title'])->toBe('Your report is in progress');

    $this->actingAs($owner)->get(route('notifications'))->assertOk()->assertSee('Your report is in progress');
    $this->actingAs($owner, 'sanctum')->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('notifications.0.title', 'Your report is in progress')
        ->assertJsonPath('notifications.0.action_route', '/tabs/report');
});

test('normal transition guards prevent bypasses no ops and duplicate notifications', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $pending = workflowReport($owner);

    $this->actingAs($admin)->post(route('admin.reports.resolve', $pending), ['admin_notes' => 'Premature'])
        ->assertSessionHas('error', 'Only reports in progress can be resolved.');
    expect($pending->fresh()->status)->toBe(Report::STATUS_PENDING);
    Notification::assertNothingSent();

    $this->actingAs($admin)->post(route('admin.reports.in-progress', $pending))->assertSessionHas('success');
    Notification::assertSentToTimes($owner, ReportInProgressNotification::class, 1);

    $this->actingAs($admin)->post(route('admin.reports.in-progress', $pending->fresh()))
        ->assertSessionHas('error', 'Only pending reports can be marked in progress.');
    Notification::assertSentToTimes($owner, ReportInProgressNotification::class, 1);

    $this->travelTo(now()->addHour());
    $this->actingAs($admin)->post(route('admin.reports.resolve', $pending), ['admin_notes' => 'Issue fixed'])
        ->assertSessionHas('success');

    expect($pending->fresh()->status)->toBe(Report::STATUS_RESOLVED)
        ->and($pending->fresh()->resolved_at->format('Y-m-d H:i:s'))->toBe(now()->format('Y-m-d H:i:s'));
    Notification::assertSentToTimes($owner, ReportResolvedNotification::class, 1);
});

test('admin can reject pending and in progress reports but not terminal reports', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $pending = workflowReport($owner);
    $inProgress = workflowReport($owner, ['status' => Report::STATUS_IN_PROGRESS]);

    foreach ([$pending, $inProgress] as $report) {
        $this->actingAs($admin)->post(route('admin.reports.reject', $report), [
            'rejection_reason' => 'This report does not meet moderation requirements.',
        ])->assertSessionHas('success');
        expect($report->fresh()->status)->toBe(Report::STATUS_REJECTED)
            ->and($report->fresh()->resolved_at)->toBeNull();
    }

    $this->actingAs($admin)->post(route('admin.reports.reject', $pending->fresh()), [
        'rejection_reason' => 'A second rejection should not be applied.',
    ])->assertSessionHas('error');
    Notification::assertSentToTimes($owner, ReportRejectedNotification::class, 2);
});

test('in progress remains on Home at any age while resolved Home retention remains exactly seventy two hours', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $inProgress = workflowReport($owner, [
        'status' => Report::STATUS_IN_PROGRESS,
        'created_at' => now()->subYear(),
    ]);
    $withinWindow = workflowReport($owner, ['status' => Report::STATUS_RESOLVED]);
    $withinWindow->forceFill(['resolved_at' => now()->subHours(71)->subMinutes(59)])->saveQuietly();
    $atBoundary = workflowReport($owner, ['status' => Report::STATUS_RESOLVED]);
    $atBoundary->forceFill(['resolved_at' => now()->subHours(72)])->saveQuietly();

    $webIds = $this->actingAs($viewer)->get(route('dashboard'))->assertOk()->viewData('recentReports')->pluck('id');
    expect($webIds)->toContain($inProgress->id, $withinWindow->id)->not->toContain($atBoundary->id);

    $apiIds = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/reports/home')->assertOk()->json('data.*.id');
    expect($apiIds)->toContain($inProgress->id, $withinWindow->id)->not->toContain($atBoundary->id);

    $communityIds = $this->actingAs($viewer)->get(route('community-reports'))->assertOk()->viewData('reports')->pluck('id');
    expect($communityIds)->toContain($inProgress->id, $withinWindow->id, $atBoundary->id);
});

test('resident web and mobile api render and serialize in progress reports', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $report = workflowReport($owner, ['status' => Report::STATUS_IN_PROGRESS]);

    $this->actingAs($viewer)->get(route('dashboard'))->assertOk()->assertSee('In Progress');
    $this->actingAs($viewer)->get(route('community-reports'))->assertOk()->assertSee('In Progress');
    $this->actingAs($owner)->get(route('profile'))->assertOk()->assertSee('In Progress');
    $this->actingAs($viewer)->getJson(route('reports.show', $report))
        ->assertOk()
        ->assertJsonPath('status', Report::STATUS_IN_PROGRESS)
        ->assertJsonPath('status_label', 'In Progress')
        ->assertJsonPath('timeline.1.label', 'In Progress');

    $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/reports')
        ->assertOk()
        ->assertJsonPath('data.0.status', Report::STATUS_IN_PROGRESS)
        ->assertJsonPath('data.0.status_label', 'In Progress');
});

test('admin filter summary and reporting analytics count in progress separately', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    workflowReport($owner, ['status' => Report::STATUS_PENDING, 'description' => 'Pending workflow report']);
    workflowReport($owner, ['status' => Report::STATUS_IN_PROGRESS, 'description' => 'Active workflow report']);
    workflowReport($owner, ['status' => Report::STATUS_RESOLVED, 'description' => 'Resolved workflow report']);

    $response = $this->actingAs($admin)->get(route('admin.reports', ['status' => Report::STATUS_IN_PROGRESS]))->assertOk();
    $response
        ->assertSee('Active workflow report')
        ->assertDontSee('Pending workflow report')
        ->assertDontSee('Resolved workflow report')
        ->assertSee('In Progress')
        ->assertViewHas('inProgressReports', 1)
        ->assertViewHas('topReporters', fn ($reporters) => (int) $reporters->first()->in_progress_reports_count === 1)
        ->assertViewHas('frequentLocations', fn ($locations) => (int) $locations->first()->in_progress_reports === 1);

    $this->actingAs($admin)->get(route('admin.reports.reporters'))
        ->assertOk()
        ->assertViewHas('reporters', fn ($reporters) => (int) $reporters->first()->in_progress_reports_count === 1);
    $this->actingAs($admin)->get(route('admin.reports.locations'))
        ->assertOk()
        ->assertViewHas('locations', fn ($locations) => (int) $locations->first()->in_progress_reports === 1);
});

test('bulk transitions process only eligible reports and report skipped records', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $pending = workflowReport($owner);
    $alreadyInProgress = workflowReport($owner, ['status' => Report::STATUS_IN_PROGRESS]);
    $resolved = workflowReport($owner, ['status' => Report::STATUS_RESOLVED]);

    $this->actingAs($admin)->post(route('admin.reports.bulk-in-progress'), [
        'report_ids' => [$pending->id, $alreadyInProgress->id, $resolved->id],
    ])->assertSessionHas('success', '1 report(s) marked in progress successfully. 2 ineligible report(s) skipped.');

    expect($pending->fresh()->status)->toBe(Report::STATUS_IN_PROGRESS)
        ->and($alreadyInProgress->fresh()->status)->toBe(Report::STATUS_IN_PROGRESS)
        ->and($resolved->fresh()->status)->toBe(Report::STATUS_RESOLVED);

    $this->actingAs($admin)->post(route('admin.reports.bulk-resolve'), [
        'report_ids' => [$pending->id, $alreadyInProgress->id, $resolved->id],
        'admin_notes' => 'Bulk workflow completed.',
    ])->assertSessionHas('success', '2 report(s) resolved successfully. 1 ineligible report(s) skipped.');

    expect($pending->fresh()->status)->toBe(Report::STATUS_RESOLVED)
        ->and($alreadyInProgress->fresh()->status)->toBe(Report::STATUS_RESOLVED)
        ->and($pending->fresh()->resolved_at)->not->toBeNull();
});

test('residents cannot invoke individual or bulk admin status transitions', function () {
    $resident = User::factory()->create(['is_admin' => false]);
    $report = workflowReport($resident);

    $this->actingAs($resident)->post(route('admin.reports.in-progress', $report))->assertForbidden();
    $this->actingAs($resident)->post(route('admin.reports.resolve', $report))->assertForbidden();
    $this->actingAs($resident)->post(route('admin.reports.reject', $report), [
        'rejection_reason' => 'Resident must not moderate reports.',
    ])->assertForbidden();
    $this->actingAs($resident)->post(route('admin.reports.bulk-in-progress'), [
        'report_ids' => [$report->id],
    ])->assertForbidden();

    expect($report->fresh()->status)->toBe(Report::STATUS_PENDING);
});
