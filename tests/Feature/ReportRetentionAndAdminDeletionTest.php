<?php

use App\Models\ActivityLog;
use App\Models\Report;
use App\Models\ReportComment;
use App\Models\ReportLike;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function retentionReport(User $owner, string $status = 'pending', ?Carbon $resolvedAt = null): Report
{
    $report = Report::query()->create([
        'user_id' => $owner->id,
        'location' => 'Retention test location',
        'description' => "{$status} retention test report",
        'status' => $status,
        'priority' => 'medium',
    ]);

    if ($resolvedAt) {
        $report->forceFill(['resolved_at' => $resolvedAt])->saveQuietly();
    }

    return $report->fresh();
}

function enableSqliteFieldFunctionForAdminReports(): void
{
    if (DB::getDriverName() !== 'sqlite') {
        return;
    }

    DB::connection()->getPdo()->sqliteCreateFunction('FIELD', function ($value, ...$values) {
        $position = array_search($value, $values, true);

        return $position === false ? 0 : $position + 1;
    });
}

test('web and API community feeds share the 72 hour public visibility rule', function () {
    $this->travelTo(Carbon::parse('2026-09-15 12:00:00'));
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    $pending = retentionReport($owner);
    $rejected = retentionReport($owner, 'rejected');
    $newlyResolved = retentionReport($owner, 'resolved');
    $withinWindow = retentionReport($owner, 'resolved', now()->subHours(71)->subMinutes(59));
    $atBoundary = retentionReport($owner, 'resolved', now()->subHours(72));
    $expired = retentionReport($owner, 'resolved', now()->subHours(73));

    $visibleIds = [$pending->id, $rejected->id, $newlyResolved->id, $withinWindow->id];
    $hiddenIds = [$atBoundary->id, $expired->id];

    $this->actingAs($viewer)->get('/community-reports')
        ->assertOk()
        ->assertViewHas('reports', function ($reports) use ($visibleIds, $hiddenIds) {
            $ids = $reports->getCollection()->pluck('id');

            return collect($visibleIds)->every(fn ($id) => $ids->contains($id))
                && collect($hiddenIds)->every(fn ($id) => ! $ids->contains($id));
        });

    $apiIds = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/reports')
        ->assertOk()
        ->json('data.*.id');

    expect($apiIds)
        ->toEqualCanonicalizing($visibleIds)
        ->and(Report::query()->whereKey($hiddenIds)->count())->toBe(2);
});

test('old resolved reports remain in owner history but not another users public feed', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $oldResolved = retentionReport($owner, 'resolved', now()->subHours(73));

    $this->actingAs($owner, 'sanctum')->getJson('/api/v1/me/reports')
        ->assertOk()
        ->assertJsonPath('data.0.id', $oldResolved->id);

    $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/reports')
        ->assertOk()
        ->assertJsonMissing(['id' => $oldResolved->id]);

    $this->actingAs($owner)->get('/profile')
        ->assertOk()
        ->assertViewHas('userReports', fn ($reports) => $reports->contains('id', $oldResolved->id));
});

test('report status transitions maintain a dedicated resolution timestamp', function () {
    $owner = User::factory()->create();
    $report = retentionReport($owner);

    $this->travelTo(Carbon::parse('2026-09-15 08:00:00'));
    $report->update(['status' => 'resolved']);
    expect($report->fresh()->resolved_at->equalTo(now()))->toBeTrue();

    $report->update(['status' => 'pending']);
    expect($report->fresh()->resolved_at)->toBeNull();

    $this->travelTo(Carbon::parse('2026-09-16 14:30:00'));
    $report->update(['status' => 'resolved']);
    expect($report->fresh()->resolved_at->equalTo(now()))->toBeTrue();

    $report->update(['status' => 'rejected']);
    expect($report->fresh()->resolved_at)->toBeNull();
});

test('admin reports retain old resolved reports and expose permanent deletion', function () {
    enableSqliteFieldFunctionForAdminReports();
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $oldResolved = retentionReport($owner, 'resolved', now()->subMonths(6));

    $this->actingAs($admin)->get('/admin/reports')
        ->assertOk()
        ->assertViewHas('reports', fn ($reports) => $reports->contains('id', $oldResolved->id))
        ->assertSee(route('admin.reports.destroy', $oldResolved), false)
        ->assertSee('Permanently delete this report? This action cannot be undone.', false);
});

test('only admins can permanently delete reports and all stored dependencies are cleaned up', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $report = retentionReport($owner);
    $report->update(['image_path' => 'reports/permanent-delete.jpg']);
    Storage::disk('public')->put($report->image_path, 'report image');
    ReportLike::query()->create(['report_id' => $report->id, 'user_id' => $other->id]);
    ReportComment::query()->create(['report_id' => $report->id, 'user_id' => $other->id, 'comment' => 'Related comment']);
    $report->followers()->attach($other->id);
    $notification = DatabaseNotification::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\ReportResolvedNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $owner->id,
        'data' => ['report_id' => $report->id, 'category' => 'reports'],
    ]);
    $activity = ActivityLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'report.resolved',
        'model_type' => Report::class,
        'model_id' => $report->id,
    ]);

    $this->actingAs($other)->delete(route('admin.reports.destroy', $report))->assertForbidden();
    expect(Report::find($report->id))->not->toBeNull();
    Storage::disk('public')->assertExists($report->image_path);

    $this->actingAs($admin)->delete(route('admin.reports.destroy', $report))
        ->assertRedirect(route('admin.reports'))
        ->assertSessionHas('success', 'Report permanently deleted successfully.');

    $this->assertDatabaseMissing('reports', ['id' => $report->id]);
    $this->assertDatabaseMissing('report_likes', ['report_id' => $report->id]);
    $this->assertDatabaseMissing('report_comments', ['report_id' => $report->id]);
    $this->assertDatabaseMissing('report_followers', ['report_id' => $report->id]);
    $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    $this->assertDatabaseMissing('activity_logs', ['id' => $activity->id]);
    Storage::disk('public')->assertMissing('reports/permanent-delete.jpg');
});
