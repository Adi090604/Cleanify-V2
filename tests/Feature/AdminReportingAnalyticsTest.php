<?php

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function enableSqliteFieldFunctionForReportingAnalytics(): void
{
    if (DB::getDriverName() !== 'sqlite') {
        return;
    }

    DB::connection()->getPdo()->sqliteCreateFunction('FIELD', function ($value, ...$values) {
        $position = array_search($value, $values, true);

        return $position === false ? 0 : $position + 1;
    });
}

function analyticsReport(User $reporter, array $attributes = []): Report
{
    $createdAt = $attributes['created_at'] ?? now();
    unset($attributes['created_at']);

    $report = Report::query()->create(array_merge([
        'user_id' => $reporter->id,
        'location' => 'Analytics Location',
        'description' => 'Analytics fixture',
        'status' => 'pending',
        'priority' => 'medium',
    ], $attributes));

    $report->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

    return $report->fresh();
}

beforeEach(function () {
    enableSqliteFieldFunctionForReportingAnalytics();
});

test('reports page keeps five-item summaries and links to both full analytics pages', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporters = collect(range(1, 6))->map(function (int $number) {
        $reporter = User::factory()->create(['name' => "Reporter {$number}"]);

        analyticsReport($reporter, [
            'location' => "Location {$number}",
            'created_at' => now()->subDays($number),
        ]);

        return $reporter;
    });

    $response = $this->actingAs($admin)->get(route('admin.reports'))->assertOk();

    $response
        ->assertViewHas('topReporters', fn ($items) => $items->count() === 5 && ! $items->contains('id', $reporters->last()->id))
        ->assertViewHas('frequentLocations', fn ($items) => $items->count() === 5)
        ->assertSee('View All Reporters')
        ->assertSee(route('admin.reports.reporters'), false)
        ->assertSee('View All Locations')
        ->assertSee(route('admin.reports.locations'), false);
});

test('reporter activity is server paginated twenty per page in deterministic order', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $expectedIds = collect();

    foreach (range(1, 22) as $number) {
        $reporter = User::factory()->create(['name' => sprintf('Reporter %02d', $number)]);
        analyticsReport($reporter, ['created_at' => now()->subMinutes($number)]);
        $expectedIds->push($reporter->id);
    }

    $firstPage = $this->actingAs($admin)->get(route('admin.reports.reporters'))->assertOk();
    $firstPage->assertViewHas('reporters', function ($reporters) use ($expectedIds) {
        return $reporters->perPage() === 20
            && $reporters->total() === 22
            && $reporters->currentPage() === 1
            && $reporters->pluck('id')->all() === $expectedIds->take(20)->all();
    });

    $this->actingAs($admin)->get(route('admin.reports.reporters', ['page' => 2]))
        ->assertOk()
        ->assertSee('Showing 21&ndash;22 of 22 reporters', false)
        ->assertViewHas('reporters', fn ($reporters) => $reporters->currentPage() === 2
            && $reporters->pluck('id')->all() === $expectedIds->slice(20)->values()->all());
});

test('reporter search ordering details and view reports action are correct', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $leader = User::factory()->create(['name' => 'Zulu Match', 'email' => 'zulu@example.test']);
    $alpha = User::factory()->create([
        'name' => 'Alpha Match',
        'email' => 'alpha@example.test',
        'service_area' => null,
    ]);
    $beta = User::factory()->create(['name' => 'Beta Match', 'email' => 'beta@example.test']);
    $unrelated = User::factory()->create(['name' => 'Unrelated Person']);

    analyticsReport($leader, ['created_at' => '2026-10-16 09:00:00']);
    analyticsReport($leader, ['created_at' => '2026-10-17 09:00:00']);
    analyticsReport($leader, ['created_at' => '2026-10-18 09:00:00']);
    analyticsReport($alpha, ['location' => ' Shared  Place ', 'status' => 'pending', 'created_at' => '2026-10-17 09:00:00']);
    analyticsReport($alpha, ['location' => 'shared place', 'status' => 'resolved', 'created_at' => '2026-10-18 09:00:00']);
    analyticsReport($beta, ['location' => null, 'status' => 'rejected', 'created_at' => '2026-10-18 09:00:00']);
    analyticsReport($beta, ['location' => null, 'status' => 'pending', 'created_at' => '2026-10-17 09:00:00']);
    analyticsReport($unrelated);

    $response = $this->actingAs($admin)->get(route('admin.reports.reporters', ['search' => 'Match']))->assertOk();

    $response
        ->assertViewHas('reporters', fn ($reporters) => $reporters->pluck('id')->all() === [$leader->id, $alpha->id, $beta->id])
        ->assertSeeInOrder(['Zulu Match', 'Alpha Match', 'Beta Match'])
        ->assertDontSee('Unrelated Person')
        ->assertSee('Not set')
        ->assertSee('Shared  Place')
        ->assertSee('No location data')
        ->assertSee(route('admin.reports', ['reporter_id' => $alpha->id]), false);

    $this->actingAs($admin)->get(route('admin.reports.reporters', ['search' => 'beta@example.test']))
        ->assertOk()
        ->assertSee('Beta Match')
        ->assertDontSee('Alpha Match');
});

test('reporter search query survives pagination', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    foreach (range(1, 21) as $number) {
        $reporter = User::factory()->create(['name' => sprintf('Needle Reporter %02d', $number)]);
        analyticsReport($reporter, ['created_at' => now()->subMinutes($number)]);
    }

    $this->actingAs($admin)->get(route('admin.reports.reporters', ['search' => 'Needle']))
        ->assertOk()
        ->assertViewHas('reporters', function ($reporters) {
            parse_str((string) parse_url($reporters->nextPageUrl(), PHP_URL_QUERY), $query);

            return $reporters->total() === 21 && ($query['search'] ?? null) === 'Needle';
        });
});

test('reported locations are normalized aggregated and ordered with accurate unique reporters', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $first = User::factory()->create();
    $second = User::factory()->create();
    $third = User::factory()->create();

    analyticsReport($first, ['location' => ' Barangay  Nabago ', 'status' => 'pending', 'created_at' => '2026-10-14 09:00:00']);
    analyticsReport($first, ['location' => 'barangay nabago', 'status' => 'resolved', 'created_at' => '2026-10-15 09:00:00']);
    analyticsReport($second, ['location' => 'BARANGAY NABAGO', 'status' => 'resolved', 'created_at' => '2026-10-16 09:00:00']);
    analyticsReport($second, ['location' => 'Barangay Nabago', 'status' => 'rejected', 'created_at' => '2026-10-17 09:00:00']);
    analyticsReport($third, ['location' => 'Barangay Nabago', 'status' => 'pending', 'created_at' => '2026-10-18 09:00:00']);
    analyticsReport($third, ['location' => 'Other Location', 'created_at' => '2026-10-18 10:00:00']);

    $response = $this->actingAs($admin)->get(route('admin.reports.locations'))->assertOk();

    $response->assertViewHas('locations', function ($locations) {
        $firstLocation = $locations->first();

        return $firstLocation->location_key === 'barangay nabago'
            && (int) $firstLocation->total_reports === 5
            && (int) $firstLocation->unique_reporters === 3
            && (int) $firstLocation->pending_reports === 2
            && (int) $firstLocation->resolved_reports === 2
            && (int) $firstLocation->rejected_reports === 1;
    });
    $response->assertSee(route('admin.reports', ['location' => 'barangay nabago']), false);
});

test('location activity is paginated twenty per page and normalized search is preserved', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create();

    foreach (range(1, 22) as $number) {
        analyticsReport($reporter, [
            'location' => sprintf('Needle Zone %02d', $number),
            'created_at' => now()->subMinutes($number),
        ]);
    }

    $response = $this->actingAs($admin)->get(route('admin.reports.locations', ['search' => '  NEEDLE   ZONE ']))->assertOk();
    $response->assertViewHas('locations', function ($locations) {
        parse_str((string) parse_url($locations->nextPageUrl(), PHP_URL_QUERY), $query);

        return $locations->perPage() === 20
            && $locations->total() === 22
            && count($locations->items()) === 20
            && ($query['search'] ?? null) === 'NEEDLE   ZONE';
    });

    $this->actingAs($admin)->get(route('admin.reports.locations', ['search' => 'Needle', 'page' => 2]))
        ->assertOk()
        ->assertSee('Showing 21&ndash;22 of 22 locations', false)
        ->assertViewHas('locations', fn ($locations) => $locations->currentPage() === 2 && count($locations->items()) === 2);

    $this->actingAs($admin)->get(route('admin.reports.locations', ['search' => 'Zone 22']))
        ->assertOk()
        ->assertSee('Needle Zone 22')
        ->assertDontSee('Needle Zone 21');
});

test('analytics pages have distinct empty and no-result states', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get(route('admin.reports.reporters'))
        ->assertOk()
        ->assertSee('No reporter activity yet.');
    $this->actingAs($admin)->get(route('admin.reports.locations'))
        ->assertOk()
        ->assertSee('No reported locations yet.');
    $this->actingAs($admin)->get(route('admin.reports.reporters', ['search' => 'missing']))
        ->assertOk()
        ->assertSee('No reporters found.');
    $this->actingAs($admin)->get(route('admin.reports.locations', ['search' => 'missing']))
        ->assertOk()
        ->assertSee('No locations found.');
});

test('analytics routes are admin only static get routes and do not alter report deletion', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create(['is_admin' => false]);
    $report = analyticsReport($resident);

    $this->actingAs($resident)->get(route('admin.reports.reporters'))->assertForbidden();
    $this->actingAs($resident)->get(route('admin.reports.locations'))->assertForbidden();
    $this->actingAs($admin)->get('/admin/reports/reporters')->assertOk()->assertSee('Reporter Activity');
    $this->actingAs($admin)->get('/admin/reports/locations')->assertOk()->assertSee('Reported Locations');

    $reporterRoute = Route::getRoutes()->getByName('admin.reports.reporters');
    $locationRoute = Route::getRoutes()->getByName('admin.reports.locations');
    $destroyRoute = Route::getRoutes()->getByName('admin.reports.destroy');

    expect($reporterRoute->uri())->toBe('admin/reports/reporters')
        ->and($reporterRoute->methods())->toContain('GET')
        ->and($locationRoute->uri())->toBe('admin/reports/locations')
        ->and($locationRoute->methods())->toContain('GET')
        ->and($destroyRoute->uri())->toBe('admin/reports/{report}')
        ->and($destroyRoute->methods())->toContain('DELETE');

    $this->actingAs($admin)->delete(route('admin.reports.destroy', $report))
        ->assertRedirect(route('admin.reports'))
        ->assertSessionHas('success', 'Report permanently deleted successfully.');
    $this->assertDatabaseMissing('reports', ['id' => $report->id]);
});
