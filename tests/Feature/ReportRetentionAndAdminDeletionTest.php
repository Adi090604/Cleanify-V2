<?php

use App\Models\ActivityLog;
use App\Models\Report;
use App\Models\ReportComment;
use App\Models\ReportLike;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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

test('the seventy two hour rule applies only to web and mobile Home feeds', function () {
    $this->travelTo(Carbon::parse('2026-09-15 12:00:00'));
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    $pending = retentionReport($owner);
    $pending->forceFill(['created_at' => now()->subYear(), 'updated_at' => now()->subYear()])->saveQuietly();
    $rejected = retentionReport($owner, 'rejected');
    $newlyResolved = retentionReport($owner, 'resolved');
    $withinWindow = retentionReport($owner, 'resolved', now()->subHours(71)->subMinutes(59));
    $atBoundary = retentionReport($owner, 'resolved', now()->subHours(72));
    $expired = retentionReport($owner, 'resolved', now()->subHours(73));

    $visibleIds = [$pending->id, $rejected->id, $newlyResolved->id, $withinWindow->id];
    $hiddenIds = [$atBoundary->id, $expired->id];

    $this->actingAs($viewer)->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('recentReports', function ($reports) use ($visibleIds, $hiddenIds) {
            $ids = $reports->pluck('id');

            return collect($visibleIds)->every(fn ($id) => $ids->contains($id))
                && collect($hiddenIds)->every(fn ($id) => ! $ids->contains($id));
        });

    $communityIds = $this->actingAs($viewer)->get(route('community-reports'))
        ->assertOk()
        ->viewData('reports')
        ->getCollection()
        ->pluck('id')
        ->all();
    expect($communityIds)->toEqualCanonicalizing([...$visibleIds, ...$hiddenIds]);

    $communityApiIds = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/reports')
        ->assertOk()
        ->json('data.*.id');
    expect($communityApiIds)->toEqualCanonicalizing([...$visibleIds, ...$hiddenIds]);

    $homeApiIds = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/reports/home')
        ->assertOk()
        ->json('data.*.id');

    expect($homeApiIds)
        ->toEqualCanonicalizing($visibleIds)
        ->and(Report::query()->whereKey($hiddenIds)->count())->toBe(2);
});

test('old resolved reports remain in profile community and storage but not Home', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $oldResolved = retentionReport($owner, 'resolved', now()->subHours(73));

    $this->actingAs($owner, 'sanctum')->getJson('/api/v1/me/reports')
        ->assertOk()
        ->assertJsonPath('data.0.id', $oldResolved->id);

    $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/reports')
        ->assertOk()
        ->assertJsonFragment(['id' => $oldResolved->id]);

    $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/reports/home')
        ->assertOk()
        ->assertJsonMissing(['id' => $oldResolved->id]);

    $this->actingAs($viewer)->get(route('community-reports'))
        ->assertOk()
        ->assertViewHas('reports', fn ($reports) => $reports->contains('id', $oldResolved->id));

    $this->actingAs($owner)->get('/profile')
        ->assertOk()
        ->assertViewHas('userReports', fn ($reports) => $reports->contains('id', $oldResolved->id));

    $this->assertDatabaseHas('reports', ['id' => $oldResolved->id]);
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

    $response = $this->actingAs($admin)->get('/admin/reports');
    $response
        ->assertOk()
        ->assertViewHas('reports', fn ($reports) => $reports->contains('id', $oldResolved->id))
        ->assertViewHas('topReporters', fn ($reporters) => $reporters->firstWhere('id', $owner->id)?->reports_count === 1)
        ->assertViewHas('frequentLocations', fn ($locations) => $locations->first()?->total_reports === 1)
        ->assertSee('data-delete-report-url="'.route('admin.reports.destroy', $oldResolved).'"', false)
        ->assertSee('openDeleteReportModal(this.dataset.deleteReportUrl)', false)
        ->assertSee('Are you sure you want to permanently delete this report? This action cannot be undone.', false)
        ->assertSee('id="deleteReportForm"', false)
        ->assertSee('id="confirmDeleteReportButton" class="admin-btn-danger" disabled', false)
        ->assertSee("form.setAttribute('action', deleteUrl)", false)
        ->assertSee("this.dataset.submitting === 'true'", false)
        ->assertDontSee('confirm(', false);

    expect(preg_match_all('/<script[^>]+src="[^"]*\/assets\/app-[^"]+\.js"/', $response->getContent()))
        ->toBe(1);
});

test('admin report destroy route uses delete and consistent report model binding', function () {
    $route = Route::getRoutes()->getByName('admin.reports.destroy');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('admin/reports/{report}')
        ->and($route->methods())->toContain('DELETE')
        ->and($route->getActionName())->toBe('App\\Http\\Controllers\\Admin\\ReportsController@destroy');
});

test('only admins can permanently delete reports and all stored dependencies are cleaned up', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $report = retentionReport($owner);
    $unrelatedReport = retentionReport($owner);
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

    $response = $this->actingAs($admin)->delete(route('admin.reports.destroy', $report));
    $response
        ->assertRedirect(route('admin.reports'))
        ->assertSessionHas('success', 'Report permanently deleted successfully.');

    $this->assertDatabaseMissing('reports', ['id' => $report->id]);
    $this->assertDatabaseMissing('report_likes', ['report_id' => $report->id]);
    $this->assertDatabaseMissing('report_comments', ['report_id' => $report->id]);
    $this->assertDatabaseMissing('report_followers', ['report_id' => $report->id]);
    $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    $this->assertDatabaseMissing('activity_logs', ['id' => $activity->id]);
    $this->assertDatabaseHas('reports', ['id' => $unrelatedReport->id]);
    Storage::disk('public')->assertMissing('reports/permanent-delete.jpg');

    enableSqliteFieldFunctionForAdminReports();
    $this->actingAs($admin)->get(route('admin.reports'))
        ->assertOk()
        ->assertSee((string) $unrelatedReport->id)
        ->assertDontSee('data-delete-report-url="'.route('admin.reports.destroy', $report).'"', false);
});
