<?php

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function enableSqliteFieldFunctionForReportingInsights(): void
{
    if (DB::getDriverName() !== 'sqlite') {
        return;
    }

    DB::connection()->getPdo()->sqliteCreateFunction('FIELD', function ($value, ...$values) {
        $position = array_search($value, $values, true);

        return $position === false ? 0 : $position + 1;
    });
}

function insightReport(User $reporter, array $attributes = []): Report
{
    $createdAt = $attributes['created_at'] ?? now();
    unset($attributes['created_at']);

    $report = Report::query()->create(array_merge([
        'user_id' => $reporter->id,
        'location' => 'Location X',
        'description' => 'Reporting insight fixture',
        'status' => 'pending',
        'priority' => 'medium',
    ], $attributes));

    $report->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

    return $report->fresh();
}

beforeEach(function () {
    enableSqliteFieldFunctionForReportingInsights();
});

test('most active reporters are ordered with accurate status latest date and top location', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $active = User::factory()->create(['name' => 'Adrian Active', 'service_area' => 'Purok 5 - Don Julio']);
    $other = User::factory()->create(['name' => 'Bianca Reporter']);

    insightReport($active, ['location' => 'Location X', 'status' => 'pending', 'created_at' => '2026-10-10 08:00:00']);
    insightReport($active, ['location' => ' location x ', 'status' => 'pending', 'created_at' => '2026-10-11 08:00:00']);
    insightReport($active, ['location' => 'LOCATION X', 'status' => 'resolved', 'created_at' => '2026-10-12 08:00:00']);
    insightReport($active, ['location' => 'Location Y', 'status' => 'resolved', 'created_at' => '2026-10-13 08:00:00']);
    insightReport($active, ['location' => 'Location Y', 'status' => 'rejected', 'created_at' => '2026-10-18 08:00:00']);
    insightReport($other, ['created_at' => '2026-10-16 08:00:00']);
    insightReport($other, ['created_at' => '2026-10-17 08:00:00']);

    $response = $this->actingAs($admin)->get(route('admin.reports'))->assertOk();

    $response->assertSeeInOrder(['Adrian Active', 'Bianca Reporter'])
        ->assertSee('Current Service Area: Purok 5 - Don Julio')
        ->assertSee('Most Reported Location: LOCATION X');
    $response->assertViewHas('topReporters', function ($reporters) use ($active, $other) {
        $first = $reporters->first();

        return $reporters->pluck('id')->all() === [$active->id, $other->id]
            && $first->reports_count === 5
            && $first->pending_reports_count === 2
            && $first->resolved_reports_count === 2
            && $first->rejected_reports_count === 1
            && Carbon::parse($first->latest_report_at)->isSameDay('2026-10-18');
    });
    $response->assertViewHas('mostReportedLocations', fn ($locations) => $locations->get($active->id)?->location_key === 'location x'
        && $locations->get($active->id)?->total_reports === 3);
});

test('reporter filter uses reporter id and exposes the existing table as history', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $selected = User::factory()->create(['name' => 'Selected Resident']);
    $other = User::factory()->create(['name' => 'Other Resident']);
    insightReport($selected, ['description' => 'Selected report one']);
    insightReport($selected, ['description' => 'Selected report two']);
    insightReport($other, ['description' => 'Other report hidden']);

    $response = $this->actingAs($admin)->get(route('admin.reports', ['reporter_id' => $selected->id]));

    $response->assertOk()
        ->assertSee('Reporter History')
        ->assertSee('Selected report one')
        ->assertSee('Selected report two')
        ->assertDontSee('Other report hidden')
        ->assertSee('Clear Reporter Filter');
    $response->assertViewHas('reports', fn ($reports) => $reports->every(fn (Report $report) => $report->user_id === $selected->id));

    $this->actingAs($admin)->get(route('admin.reports'))
        ->assertOk()
        ->assertSee('Other report hidden');
});

test('free text locations are conservatively normalized and aggregate unique reporters', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $first = User::factory()->create();
    $second = User::factory()->create();
    insightReport($first, ['location' => 'Barangay  Nabago', 'status' => 'pending']);
    insightReport($first, ['location' => ' barangay nabago ', 'status' => 'resolved']);
    insightReport($second, ['location' => 'BARANGAY NABAGO', 'status' => 'resolved']);
    insightReport($second, ['location' => 'Barangay Nabago', 'status' => 'rejected']);
    insightReport($second, ['location' => 'Different Place']);

    $response = $this->actingAs($admin)->get(route('admin.reports'));

    $response->assertOk()->assertViewHas('frequentLocations', function ($locations) {
        $location = $locations->first();

        return $location->location_key === 'barangay nabago'
            && $location->total_reports === 4
            && $location->unique_reporters === 2
            && $location->pending_reports === 1
            && $location->resolved_reports === 2
            && $location->rejected_reports === 1;
    });
});

test('location filter returns only the normalized exact location group', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create();
    insightReport($reporter, ['location' => 'Barangay  Nabago', 'description' => 'First matching location']);
    insightReport($reporter, ['location' => ' barangay nabago ', 'description' => 'Second matching location']);
    insightReport($reporter, ['location' => 'Nabago Fire Station', 'description' => 'Distinct location']);

    $response = $this->actingAs($admin)->get(route('admin.reports', ['location' => 'BARANGAY NABAGO']));

    $response->assertOk()
        ->assertSee('Reported Location')
        ->assertSee('First matching location')
        ->assertSee('Second matching location')
        ->assertDontSee('Distinct location')
        ->assertSee('Clear Location Filter');
    $response->assertViewHas('reports', fn ($reports) => $reports->total() === 2);
});

test('reporter location and existing status filters combine server side', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $selected = User::factory()->create();
    $other = User::factory()->create();
    insightReport($selected, ['location' => 'Location X', 'status' => 'pending', 'description' => 'Selected pending']);
    insightReport($selected, ['location' => 'Location X', 'status' => 'resolved', 'description' => 'Selected resolved']);
    insightReport($other, ['location' => 'Location X', 'status' => 'resolved', 'description' => 'Other resolved']);

    $this->actingAs($admin)->get(route('admin.reports', [
        'reporter_id' => $selected->id,
        'status' => 'resolved',
    ]))->assertOk()
        ->assertSee('Selected resolved')
        ->assertDontSee('Selected pending')
        ->assertDontSee('Other resolved');

    $this->actingAs($admin)->get(route('admin.reports', [
        'location' => 'location x',
        'status' => 'pending',
    ]))->assertOk()
        ->assertSee('Selected pending')
        ->assertDontSee('Selected resolved')
        ->assertDontSee('Other resolved');
});

test('pagination preserves reporter location search status and priority filters', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create();
    foreach (range(1, 11) as $number) {
        insightReport($reporter, [
            'location' => 'Location X',
            'description' => "Needle report {$number}",
            'status' => 'pending',
            'priority' => 'high',
        ]);
    }

    $query = [
        'reporter_id' => $reporter->id,
        'location' => 'location x',
        'search' => 'Needle',
        'status' => 'pending',
        'priority' => 'high',
    ];
    $response = $this->actingAs($admin)->get(route('admin.reports', $query))->assertOk();

    $response->assertViewHas('reports', function ($reports) use ($query) {
        $nextPageUrl = $reports->nextPageUrl();
        parse_str((string) parse_url($nextPageUrl, PHP_URL_QUERY), $nextPageQuery);

        return $reports->total() === 11
            && collect($query)->every(fn ($value, $key) => (string) ($nextPageQuery[$key] ?? '') === (string) $value);
    });
});

test('empty insights render cleanly and non admins remain forbidden', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create(['is_admin' => false]);

    $this->actingAs($admin)->get(route('admin.reports'))
        ->assertOk()
        ->assertSee('No reporter activity yet.')
        ->assertSee('No reported locations yet.');

    $this->actingAs($resident)->get(route('admin.reports'))->assertForbidden();
});
