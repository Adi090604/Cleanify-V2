<?php

use App\Models\Report;
use App\Models\User;
use App\Services\ReportGeographyAnalytics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function enableSqliteFieldFunctionForGeographyAnalytics(): void
{
    if (DB::getDriverName() !== 'sqlite') {
        return;
    }

    DB::connection()->getPdo()->sqliteCreateFunction('FIELD', function ($value, ...$values) {
        $position = array_search($value, $values, true);

        return $position === false ? 0 : $position + 1;
    });
}

function geographyReport(User $reporter, array $attributes = []): Report
{
    $createdAt = $attributes['created_at'] ?? now();
    unset($attributes['created_at']);

    $report = Report::query()->create(array_merge([
        'user_id' => $reporter->id,
        'barangay' => 'Nabago',
        'location' => 'Near Fire Station',
        'description' => 'Geographic analytics fixture',
        'status' => Report::STATUS_PENDING,
        'priority' => 'medium',
    ], $attributes));

    $report->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

    return $report->fresh();
}

beforeEach(function () {
    enableSqliteFieldFunctionForGeographyAnalytics();
});

test('barangays normalize conservatively and expose accurate ordered status metrics', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $first = User::factory()->create();
    $second = User::factory()->create();

    geographyReport($first, ['barangay' => ' Nabago ', 'status' => 'pending', 'created_at' => '2026-10-10 08:00:00']);
    geographyReport($first, ['barangay' => 'NABAGO', 'status' => 'in_progress', 'created_at' => '2026-10-11 08:00:00']);
    geographyReport($second, ['barangay' => 'Nabago', 'status' => 'resolved', 'created_at' => '2026-10-12 08:00:00']);
    geographyReport($second, ['barangay' => ' nabago ', 'status' => 'rejected', 'created_at' => '2026-10-18 08:00:00']);
    geographyReport($first, ['barangay' => 'Other Barangay', 'status' => 'pending', 'created_at' => '2026-10-17 08:00:00']);
    geographyReport($first, ['barangay' => null]);

    $response = $this->actingAs($admin)->get(route('admin.reports.barangays'))->assertOk();

    $response->assertViewHas('barangays', function ($barangays) {
        $nabago = $barangays->first();

        return $nabago->barangay_key === 'nabago'
            && (int) $nabago->qualifying_reports === 3
            && (int) $nabago->total_reports === 4
            && (int) $nabago->unique_reporters === 2
            && (int) $nabago->pending_reports === 1
            && (int) $nabago->in_progress_reports === 1
            && (int) $nabago->resolved_reports === 1
            && (int) $nabago->rejected_reports === 1
            && $barangays->pluck('barangay_key')->all() === ['nabago', 'other barangay'];
    });

    expect(Report::where('barangay', ' Nabago ')->exists())->toBeTrue();
});

test('main reports shows five barangays while preserving both existing analytics links', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create();

    foreach (range(1, 6) as $number) {
        geographyReport($reporter, [
            'barangay' => "Barangay {$number}",
            'location' => "Landmark {$number}",
            'created_at' => now()->subDays($number),
        ]);
    }

    $this->actingAs($admin)->get(route('admin.reports'))
        ->assertOk()
        ->assertViewHas('frequentBarangays', fn ($barangays) => $barangays->count() === 5)
        ->assertViewHas('frequentLocations', fn ($locations) => $locations->count() === 5)
        ->assertSee('Frequently Reported Barangays')
        ->assertSee('View All Reporters')
        ->assertSee('View All Barangays')
        ->assertSee('View All Locations');
});

test('barangay activity paginates twenty and preserves normalized server search', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create();

    foreach (range(1, 22) as $number) {
        geographyReport($reporter, [
            'barangay' => sprintf('Needle  Barangay %02d', $number),
            'created_at' => now()->subMinutes($number),
        ]);
    }

    $response = $this->actingAs($admin)->get(route('admin.reports.barangays', ['search' => ' needle   barangay ']))->assertOk();
    $response->assertViewHas('barangays', function ($barangays) {
        parse_str((string) parse_url($barangays->nextPageUrl(), PHP_URL_QUERY), $query);

        return $barangays->perPage() === 20
            && $barangays->total() === 22
            && ($query['search'] ?? null) === 'needle   barangay';
    });

    $this->actingAs($admin)->get(route('admin.reports.barangays', ['search' => 'Needle', 'page' => 2]))
        ->assertOk()
        ->assertSee('Showing 21&ndash;22 of 22 Barangays', false);
});

test('gps clustering uses distance connected components and is retrieval-order independent', function () {
    $reporter = User::factory()->create();
    $first = geographyReport($reporter, ['latitude' => 9.7800, 'longitude' => 125.4900, 'location' => 'Alpha']);
    $second = geographyReport($reporter, ['latitude' => 9.7808, 'longitude' => 125.4900, 'location' => 'Alpha']);
    $third = geographyReport($reporter, ['latitude' => 9.7816, 'longitude' => 125.4900, 'location' => 'Beta']);
    $far = geographyReport($reporter, ['latitude' => 9.7840, 'longitude' => 125.4900, 'location' => 'Far Site']);
    $identical = geographyReport($reporter, ['latitude' => 9.7840, 'longitude' => 125.4900, 'location' => 'Far Site']);

    $service = app(ReportGeographyAnalytics::class);
    $ordered = $service->hotspots(collect([$first, $second, $third, $far, $identical]));
    $reversed = $service->hotspots(collect([$identical, $far, $third, $second, $first]));

    expect($ordered)->toHaveCount(2)
        ->and($ordered->pluck('id')->all())->toBe($reversed->pluck('id')->all())
        ->and($ordered->map->report_ids->all())->toBe($reversed->map->report_ids->all())
        ->and($ordered->firstWhere('id', 'gps-'.$first->id)->report_ids)->toBe([$first->id, $second->id, $third->id])
        ->and($ordered->firstWhere('id', 'gps-'.$far->id)->report_ids)->toBe([$far->id, $identical->id]);
});

test('mixed gps and landmark reports reindex the gps subset before positional clustering', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create();
    $gpsOne = geographyReport($reporter, ['barangay' => 'Canlanipa', 'location' => 'Shared Site', 'latitude' => 9.7800, 'longitude' => 125.4900]);
    $fallback = geographyReport($reporter, ['barangay' => 'Canlanipa', 'location' => 'Fallback Site']);
    $gpsTwo = geographyReport($reporter, ['barangay' => 'Canlanipa', 'location' => 'Shared Site', 'latitude' => 9.7802, 'longitude' => 125.4900]);
    $gpsThree = geographyReport($reporter, ['barangay' => 'Canlanipa', 'location' => 'Shared Site', 'latitude' => 9.7804, 'longitude' => 125.4900]);

    $orderedReports = collect([$gpsOne, $fallback, $gpsTwo, $gpsThree])->sortBy('id')->values();
    [$gpsSubset, $fallbackSubset] = $orderedReports->partition(
        fn (Report $report) => $report->latitude !== null && $report->longitude !== null
    );

    expect($gpsSubset->keys()->all())->toBe([0, 2, 3])
        ->and($fallbackSubset->keys()->all())->toBe([1]);

    $hotspots = app(ReportGeographyAnalytics::class)->hotspots($orderedReports);
    $gpsHotspot = $hotspots->firstWhere('basis', 'gps');
    $landmarkHotspot = $hotspots->firstWhere('basis', 'landmark');

    expect($hotspots)->toHaveCount(2)
        ->and($hotspots->sum('total_reports'))->toBe(4)
        ->and($gpsHotspot->report_ids)->toBe([$gpsOne->id, $gpsTwo->id, $gpsThree->id])
        ->and($landmarkHotspot->report_ids)->toBe([$fallback->id]);

    $this->actingAs($admin)->get(route('admin.reports.hotspots', ['barangay' => 'canlanipa']))
        ->assertOk()
        ->assertViewHas('hotspots', fn ($items) => $items->count() === 2
            && $items->sum('total_reports') === 4
            && $items->firstWhere('basis', 'gps')->report_ids === [$gpsOne->id, $gpsTwo->id, $gpsThree->id]
            && $items->firstWhere('basis', 'landmark')->report_ids === [$fallback->id]);
});

test('gps groups stay inside a barangay and valid gps reports are never duplicated as landmarks', function () {
    $reporter = User::factory()->create();
    $nabagoGps = geographyReport($reporter, ['barangay' => 'Nabago', 'location' => 'Shared Landmark', 'latitude' => 9.7800, 'longitude' => 125.4900]);
    $nabagoFallback = geographyReport($reporter, ['barangay' => 'Nabago', 'location' => 'Shared Landmark']);
    geographyReport($reporter, ['barangay' => 'Washington', 'location' => 'Shared Landmark', 'latitude' => 9.7800, 'longitude' => 125.4900]);

    $service = app(ReportGeographyAnalytics::class);
    $hotspots = $service->hotspots($service->reportsForBarangay('nabago'));

    expect($hotspots)->toHaveCount(2)
        ->and($hotspots->sum('total_reports'))->toBe(2)
        ->and($hotspots->firstWhere('basis', 'gps')->report_ids)->toBe([$nabagoGps->id])
        ->and($hotspots->firstWhere('basis', 'landmark')->report_ids)->toBe([$nabagoFallback->id]);
});

test('gps hotspot landmark label has a deterministic text tie break', function () {
    $reporter = User::factory()->create();
    $beta = geographyReport($reporter, ['location' => 'Beta Site', 'latitude' => 9.7800, 'longitude' => 125.4900, 'created_at' => '2026-10-18 08:00:00']);
    $alpha = geographyReport($reporter, ['location' => 'Alpha Site', 'latitude' => 9.7801, 'longitude' => 125.4900, 'created_at' => '2026-10-18 08:00:00']);

    $hotspot = app(ReportGeographyAnalytics::class)->hotspots(collect([$beta, $alpha]))->sole();

    expect($hotspot->label)->toBe('Alpha Site')
        ->and($hotspot->report_ids)->toBe([$beta->id, $alpha->id]);
});

test('invalid gps falls back to exact landmark normalization without fuzzy matching', function () {
    $reporter = User::factory()->create();
    $first = geographyReport($reporter, ['location' => ' In front of  Fire Station ', 'latitude' => 91, 'longitude' => 181]);
    $second = geographyReport($reporter, ['location' => 'IN FRONT OF FIRE STATION']);
    $different = geographyReport($reporter, ['location' => 'Near Fire Station']);

    $hotspots = app(ReportGeographyAnalytics::class)->hotspots(collect([$first, $second, $different]));

    expect($hotspots)->toHaveCount(2)
        ->and($hotspots->where('basis', 'gps'))->toBeEmpty()
        ->and($hotspots->firstWhere('label_key', 'in front of fire station')->report_ids)->toBe([$first->id, $second->id])
        ->and($hotspots->firstWhere('label_key', 'near fire station')->report_ids)->toBe([$different->id])
        ->and($first->fresh()->location)->toBe(' In front of  Fire Station ');
});

test('hotspot metrics frequency and gps label selection exclude rejected rank inflation', function () {
    $firstReporter = User::factory()->create();
    $secondReporter = User::factory()->create();
    $created = collect([
        geographyReport($firstReporter, ['location' => 'Alpha Site', 'latitude' => 9.7800, 'longitude' => 125.4900, 'status' => 'pending', 'created_at' => '2026-10-10 08:00:00']),
        geographyReport($secondReporter, ['location' => 'Beta Site', 'latitude' => 9.7801, 'longitude' => 125.4900, 'status' => 'in_progress', 'created_at' => '2026-10-11 08:00:00']),
        geographyReport($secondReporter, ['location' => 'Beta Site', 'latitude' => 9.7802, 'longitude' => 125.4900, 'status' => 'resolved', 'created_at' => '2026-10-12 08:00:00']),
        geographyReport($firstReporter, ['location' => 'Alpha Site', 'latitude' => 9.7803, 'longitude' => 125.4900, 'status' => 'rejected', 'created_at' => '2026-10-13 08:00:00']),
    ]);
    $singleQualifying = geographyReport($firstReporter, ['location' => 'Rejected Place', 'status' => 'pending', 'created_at' => '2026-10-14 08:00:00']);
    $rejectedOnly = geographyReport($firstReporter, ['location' => 'Rejected Place', 'status' => 'rejected', 'created_at' => '2026-10-15 08:00:00']);

    $hotspots = app(ReportGeographyAnalytics::class)->hotspots($created->push($singleQualifying, $rejectedOnly));
    $gps = $hotspots->firstWhere('basis', 'gps');
    $landmark = $hotspots->firstWhere('basis', 'landmark');

    expect($gps->label)->toBe('Alpha Site')
        ->and($gps->total_reports)->toBe(4)
        ->and($gps->qualifying_reports)->toBe(3)
        ->and($gps->unique_reporters)->toBe(2)
        ->and($gps->pending_reports)->toBe(1)
        ->and($gps->in_progress_reports)->toBe(1)
        ->and($gps->resolved_reports)->toBe(1)
        ->and($gps->rejected_reports)->toBe(1)
        ->and($gps->latest_report_at->isSameDay('2026-10-13'))->toBeTrue()
        ->and($gps->is_frequent)->toBeTrue()
        ->and($landmark->total_reports)->toBe(2)
        ->and($landmark->qualifying_reports)->toBe(1)
        ->and($landmark->is_frequent)->toBeFalse();
});

test('hotspot drilldowns return exact gps and landmark members only', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create();
    $gpsOne = geographyReport($reporter, ['description' => 'GPS member one', 'latitude' => 9.7800, 'longitude' => 125.4900]);
    $gpsTwo = geographyReport($reporter, ['description' => 'GPS member two', 'latitude' => 9.7802, 'longitude' => 125.4900]);
    $fallback = geographyReport($reporter, ['location' => 'Fallback Site', 'description' => 'Fallback member']);
    geographyReport($reporter, ['barangay' => 'Washington', 'description' => 'Other Barangay member', 'latitude' => 9.7801, 'longitude' => 125.4900]);

    $service = app(ReportGeographyAnalytics::class);
    $hotspots = $service->hotspots($service->reportsForBarangay('nabago'));
    $gps = $hotspots->firstWhere('basis', 'gps');
    $landmark = $hotspots->firstWhere('basis', 'landmark');

    $this->actingAs($admin)->get(route('admin.reports.hotspots', ['barangay' => ' NABAGO ']))
        ->assertOk()
        ->assertViewHas('hotspots', fn ($items) => $items->sum('total_reports') === 3);

    $this->actingAs($admin)->get(route('admin.reports.hotspots.reports', ['barangay' => 'nabago', 'hotspot' => $gps->id]))
        ->assertOk()
        ->assertSee('GPS member one')
        ->assertSee('GPS member two')
        ->assertDontSee('Fallback member')
        ->assertDontSee('Other Barangay member')
        ->assertViewHas('reports', fn ($reports) => $reports->pluck('id')->sort()->values()->all() === [$gpsOne->id, $gpsTwo->id]);

    $this->actingAs($admin)->get(route('admin.reports.hotspots.reports', ['barangay' => 'nabago', 'hotspot' => $landmark->id]))
        ->assertOk()
        ->assertSee('Fallback member')
        ->assertDontSee('GPS member one')
        ->assertViewHas('reports', fn ($reports) => $reports->pluck('id')->all() === [$fallback->id]);
});

test('geography routes are admin only request safe and do not replace workflow actions', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create(['is_admin' => false]);
    $report = geographyReport($resident);

    $this->actingAs($resident)->get(route('admin.reports.barangays'))->assertForbidden();
    $this->actingAs($resident)->get(route('admin.reports.hotspots', ['barangay' => 'nabago']))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.reports.hotspots'))->assertNotFound();
    $this->actingAs($admin)->get(route('admin.reports.hotspots.reports', ['barangay' => 'nabago', 'hotspot' => '../bad']))->assertNotFound();

    expect(Route::getRoutes()->getByName('admin.reports.barangays')->uri())->toBe('admin/reports/barangays')
        ->and(Route::getRoutes()->getByName('admin.reports.hotspots')->methods())->toContain('GET')
        ->and(Route::getRoutes()->getByName('admin.reports.destroy')->methods())->toContain('DELETE');

    $this->actingAs($admin)->post(route('admin.reports.in-progress', $report))
        ->assertRedirect(route('admin.reports'));
    expect($report->fresh()->status)->toBe(Report::STATUS_IN_PROGRESS);
});

test('empty and null barangay history render safely without fake frequency groups', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    geographyReport(User::factory()->create(), ['barangay' => null]);

    $this->actingAs($admin)->get(route('admin.reports.barangays'))
        ->assertOk()
        ->assertSee('No reported Barangays yet.')
        ->assertViewHas('barangays', fn ($barangays) => $barangays->total() === 0);
});
