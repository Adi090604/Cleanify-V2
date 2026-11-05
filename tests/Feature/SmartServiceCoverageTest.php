<?php

use App\Models\ActivityLog;
use App\Models\Schedule;
use App\Models\ServiceAreaRequest;
use App\Models\ServiceZone;
use App\Models\Truck;
use App\Models\User;
use App\Notifications\ServiceAreaRequestStatusNotification;
use App\Services\ExistingServiceCoverageDetector;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Config::set('cleanify.smart_service_coverage.enabled', true);
    Config::set('cleanify.smart_service_coverage.stale_after_hours', 24);
});

function coverageRequest(array $overrides = []): ServiceAreaRequest
{
    $resident = $overrides['user'] ?? User::factory()->create();
    unset($overrides['user']);

    $attributes = array_merge([
        'area_name' => 'Coverage Request Area',
        'normalized_area_name' => 'coverage request area',
        'barangay' => 'Coverage Barangay',
        'latitude' => 9.787,
        'longitude' => 125.4928,
        'details' => 'Please review coverage.',
    ], $overrides);

    return $resident->serviceAreaRequests()->create($attributes);
}

function coverageService(array $truckOverrides = [], bool $withSchedule = true, array $zoneOverrides = []): array
{
    $zone = ServiceZone::create(array_merge([
        'name' => 'Coverage Zone',
        'barangay' => 'Coverage Barangay',
        'status' => 'active',
    ], $zoneOverrides));
    $truck = Truck::create(array_merge([
        'code' => 'TRK-COVERAGE',
        'driver' => 'Coverage Driver',
        'route' => $zone->display_name,
        'status' => 'active',
        'latitude' => 9.787,
        'longitude' => 125.4928,
        'last_updated' => now(),
        'coverage_radius_meters' => 500,
    ], $truckOverrides));
    $schedule = $withSchedule ? Schedule::create([
        'area' => $zone->display_name,
        'schedule_type' => 'recurring',
        'days' => 'Monday',
        'time_start' => '08:00',
        'time_end' => '10:00',
        'truck' => $truck->code,
        'status' => 'active',
    ]) : null;

    return [$zone, $truck, $schedule];
}

test('coverage detection includes inside locations and excludes outside locations', function () {
    coverageService();
    $inside = coverageRequest();
    $outside = coverageRequest(['area_name' => 'Outside', 'normalized_area_name' => 'outside', 'latitude' => 9.8]);
    $detector = app(ExistingServiceCoverageDetector::class);

    expect($detector->detect($inside)['candidates'])->toHaveCount(1)
        ->and($detector->detect($outside)['candidates'])->toBeEmpty();
});

test('coverage detection treats the configured radius boundary as covered', function () {
    [, $truck] = coverageService(['latitude' => 0, 'longitude' => 0, 'coverage_radius_meters' => 500]);
    $detector = app(ExistingServiceCoverageDetector::class);
    $boundaryLatitude = rad2deg(500 / 6371008.8);
    $request = coverageRequest(['latitude' => $boundaryLatitude, 'longitude' => 0]);

    $distance = $detector->distanceMeters((float) $request->latitude, 0, (float) $truck->latitude, 0);
    expect($distance)->toBeLessThanOrEqual(500.01)
        ->and($detector->detect($request)['candidates'])->toHaveCount(1);
});

test('missing or invalid truck data and unresolved routes are ignored', function (array $overrides) {
    coverageService($overrides);
    $result = app(ExistingServiceCoverageDetector::class)->detect(coverageRequest());

    expect($result['candidates'])->toBeEmpty();
})->with([
    'missing latitude' => [['latitude' => null]],
    'missing longitude' => [['longitude' => null]],
    'missing radius' => [['coverage_radius_meters' => null]],
    'invalid latitude' => [['latitude' => 91]],
    'unknown route' => [['route' => 'Unmanaged Route']],
]);

test('missing resident coordinates retains manual review', function () {
    coverageService();
    $result = app(ExistingServiceCoverageDetector::class)->detect(coverageRequest([
        'latitude' => null,
        'longitude' => null,
    ]));

    expect($result['status'])->toBe('location_unavailable')
        ->and($result['candidates'])->toBeEmpty();
});

test('inactive service zones are not eligible', function () {
    coverageService([], true, ['status' => 'inactive']);

    expect(app(ExistingServiceCoverageDetector::class)->detect(coverageRequest())['candidates'])->toBeEmpty();
});

test('matching coverage without a truck-specific active schedule remains an advisory warning', function () {
    coverageService([], false);
    $candidate = app(ExistingServiceCoverageDetector::class)->detect(coverageRequest())['candidates']->sole();

    expect($candidate['has_active_schedule'])->toBeFalse()
        ->and($candidate['recommendation_status'])->toBe('review_no_active_schedule');
});

test('stale location is identified without discarding the geographic match', function () {
    coverageService(['last_updated' => now()->subDays(2)]);
    $candidate = app(ExistingServiceCoverageDetector::class)->detect(coverageRequest())['candidates']->sole();

    expect($candidate['is_location_stale'])->toBeTrue()
        ->and($candidate['recommendation_status'])->toBe('review_stale_location');
});

test('multiple matches rank active schedules first then distance deterministically', function () {
    coverageService(['code' => 'TRK-FAR', 'latitude' => 9.7875], true, ['name' => 'Far Zone']);
    coverageService(['code' => 'TRK-CLOSE-NO-SCHEDULE', 'latitude' => 9.7871], false, ['name' => 'Close Zone']);
    coverageService(['code' => 'TRK-CLOSE', 'latitude' => 9.7872], true, ['name' => 'Closer Active Zone']);
    $candidates = app(ExistingServiceCoverageDetector::class)->detect(coverageRequest())['candidates'];

    expect($candidates)->toHaveCount(3)
        ->and($candidates[0]['truck_code'])->toBe('TRK-CLOSE')
        ->and($candidates[1]['truck_code'])->toBe('TRK-FAR')
        ->and($candidates[2]['truck_code'])->toBe('TRK-CLOSE-NO-SCHEDULE');
});

test('zero and international boundary coordinates are valid inputs', function () {
    coverageService(['latitude' => 0, 'longitude' => 0]);
    $zero = coverageRequest(['latitude' => 0, 'longitude' => 0]);
    $detector = app(ExistingServiceCoverageDetector::class);

    expect($detector->detect($zero)['candidates'])->toHaveCount(1)
        ->and($detector->distanceMeters(90, 180, 90, -180))->toBeLessThan(0.01);
});

test('admin can atomically assign a pending request to an eligible existing service', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create(['service_area' => null]);
    [$zone] = coverageService();
    $request = coverageRequest(['user' => $resident]);

    $this->actingAs($admin)->post(route('admin.service-area-requests.assign-existing', $request), [
        'service_zone_id' => $zone->id,
        'admin_notes' => 'Confirmed against the current service.',
    ])->assertRedirect()->assertSessionHas('success');

    $request->refresh();
    expect($request->status)->toBe('approved')
        ->and($request->service_zone_id)->toBe($zone->id)
        ->and($request->reviewed_by)->toBe($admin->id)
        ->and($request->reviewed_at)->not->toBeNull()
        ->and($resident->fresh()->service_area)->toBe($zone->display_name)
        ->and(ActivityLog::where('action', 'service_area_request.existing_service_assigned')->count())->toBe(1);
    Notification::assertSentTo($resident, ServiceAreaRequestStatusNotification::class, fn ($notification) => $notification->eventType === ServiceAreaRequestStatusNotification::EVENT_EXISTING_SERVICE_ASSIGNED
    );
});

test('assignment recalculates current truck coverage and rolls back when it changed', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create(['service_area' => 'Original Area']);
    [$zone, $truck] = coverageService();
    $request = coverageRequest(['user' => $resident]);
    $truck->update(['latitude' => 10.5, 'last_updated' => now()]);

    $this->actingAs($admin)->from(route('admin.service-area-requests'))
        ->post(route('admin.service-area-requests.assign-existing', $request), ['service_zone_id' => $zone->id])
        ->assertRedirect(route('admin.service-area-requests'))
        ->assertSessionHasErrors('service_zone_id');

    expect($request->fresh()->status)->toBe('pending')
        ->and($request->fresh()->service_zone_id)->toBeNull()
        ->and($resident->fresh()->service_area)->toBe('Original Area')
        ->and(ActivityLog::where('action', 'service_area_request.existing_service_assigned')->count())->toBe(0);
    Notification::assertNothingSent();
});

test('manipulated zone ids and repeated processing cannot assign twice', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    [$eligibleZone] = coverageService();
    $unrelatedZone = ServiceZone::create(['name' => 'Unrelated', 'barangay' => 'Elsewhere', 'status' => 'active']);
    $request = coverageRequest();

    $this->actingAs($admin)->post(route('admin.service-area-requests.assign-existing', $request), [
        'service_zone_id' => $unrelatedZone->id,
    ])->assertSessionHasErrors('service_zone_id');
    expect($request->fresh()->status)->toBe('pending');

    $this->actingAs($admin)->post(route('admin.service-area-requests.assign-existing', $request), [
        'service_zone_id' => $eligibleZone->id,
    ])->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.service-area-requests.assign-existing', $request), [
        'service_zone_id' => $eligibleZone->id,
    ])->assertSessionHasErrors('assignment');

    expect(ActivityLog::where('action', 'service_area_request.existing_service_assigned')->count())->toBe(1);
    Notification::assertSentToTimes($request->user, ServiceAreaRequestStatusNotification::class, 1);
});

test('non admins cannot use the assignment endpoint', function () {
    $resident = User::factory()->create();
    [$zone] = coverageService();
    $request = coverageRequest();

    $this->actingAs($resident)->post(route('admin.service-area-requests.assign-existing', $request), [
        'service_zone_id' => $zone->id,
    ])->assertForbidden();

    expect($request->fresh()->status)->toBe('pending');
});

test('mobile submission adds only an optional preliminary recommendation', function () {
    $resident = User::factory()->create();
    coverageService();

    $this->actingAs($resident, 'sanctum')->postJson('/api/v1/service-area-requests', [
        'area_name' => 'Mobile Coverage Area',
        'barangay' => 'Coverage Barangay',
        'latitude' => 9.787,
        'longitude' => 125.4928,
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('coverage_recommendation.possible_existing_service', true)
        ->assertJsonPath('coverage_recommendation.status', 'recommended')
        ->assertJsonMissingPath('coverage_recommendation.service_zone_id')
        ->assertJsonMissingPath('coverage_recommendation.truck_id');
});

test('admin review renders recommendation and manual-review states', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    coverageService(['last_updated' => now()->subDays(2)], false);
    coverageRequest();
    coverageRequest([
        'area_name' => 'Manual GPS Review',
        'normalized_area_name' => 'manual gps review',
        'latitude' => null,
        'longitude' => null,
    ]);
    coverageRequest([
        'area_name' => 'No Coverage Area',
        'normalized_area_name' => 'no coverage area',
        'latitude' => 10.5,
    ]);

    $this->actingAs($admin)->get(route('admin.service-area-requests'))
        ->assertOk()
        ->assertSee('Existing Service Coverage Detected')
        ->assertSee('Assign Existing Service')
        ->assertSee('The recorded truck location may be outdated.')
        ->assertSee('No active truck schedule')
        ->assertSee('Location unavailable')
        ->assertSee('No matching coverage found');
});

test('feature flag cleanly preserves the legacy workflow', function () {
    Config::set('cleanify.smart_service_coverage.enabled', false);
    $admin = User::factory()->create(['is_admin' => true]);
    coverageService();
    $request = coverageRequest();

    expect(app(ExistingServiceCoverageDetector::class)->detect($request)['status'])->toBe('disabled');
    $this->actingAs($admin)->get(route('admin.service-area-requests'))
        ->assertOk()
        ->assertDontSee('Existing Service Coverage Detected');
});
