<?php

use App\Http\Controllers\Admin\TrackerController;
use App\Models\ServiceZone;
use App\Models\Truck;
use App\Models\TruckLocation;
use App\Models\User;

function validTruckCoveragePayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'TRK-500',
        'driver' => 'Coverage Driver',
        'route' => 'Zone 1',
        'status' => 'active',
        'coverage_radius_meters' => 500,
        'latitude' => 9.787,
        'longitude' => 125.4928,
    ], $overrides);
}

test('admin can create a truck with a persisted coverage radius', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.tracker.store'), validTruckCoveragePayload())
        ->assertRedirect(route('admin.tracker'));

    $this->assertDatabaseHas('trucks', [
        'code' => 'TRK-500',
        'coverage_radius_meters' => 500,
    ]);
    expect(Truck::where('code', 'TRK-500')->firstOrFail()->coverage_radius_meters)->toBe(500);
});

test('admin truck radius validation rejects unsafe values', function ($radius) {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->from(route('admin.tracker'))
        ->post(route('admin.tracker.store'), validTruckCoveragePayload([
            'coverage_radius_meters' => $radius,
        ]))
        ->assertRedirect(route('admin.tracker'))
        ->assertSessionHasErrors('coverage_radius_meters');

    $this->assertDatabaseMissing('trucks', ['code' => 'TRK-500']);
})->with([
    'negative' => -1,
    'zero' => 0,
    'above maximum' => Truck::MAX_COVERAGE_RADIUS_METERS + 1,
    'non numeric' => 'wide',
    'missing' => null,
]);

test('admin can update a truck radius without changing status or location history', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $truck = Truck::create(validTruckCoveragePayload());
    TruckLocation::create([
        'truck_id' => $truck->id,
        'latitude' => $truck->latitude,
        'longitude' => $truck->longitude,
        'recorded_at' => now(),
    ]);

    $this->actingAs($admin)
        ->put(route('admin.tracker.update', $truck->id), validTruckCoveragePayload([
            'coverage_radius_meters' => 1000,
        ]))
        ->assertRedirect(route('admin.tracker'));

    $truck->refresh();
    expect($truck->coverage_radius_meters)->toBe(1000)
        ->and($truck->status)->toBe('active')
        ->and($truck->locations()->count())->toBe(1);
});

test('location updates preserve radius and existing history behavior', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $truck = Truck::create(validTruckCoveragePayload(['latitude' => null, 'longitude' => null]));

    $location = ['latitude' => 9.79, 'longitude' => 125.49];
    $this->actingAs($admin)->post(route('admin.tracker.update-location', $truck->id), $location)->assertRedirect();
    $this->actingAs($admin)->post(route('admin.tracker.update-location', $truck->id), $location)->assertRedirect();

    expect($truck->fresh()->coverage_radius_meters)->toBe(500)
        ->and($truck->locations()->count())->toBe(1);
});

test('historical trucks with null radius and missing coordinates render safely', function () {
    $resident = User::factory()->create();
    $truck = Truck::create([
        'code' => 'TRK-OLD',
        'driver' => 'Historical Driver',
        'route' => 'Zone 1',
        'status' => 'offline',
    ]);

    $this->actingAs($resident)->get(route('tracker'))
        ->assertOk()
        ->assertSee('Not configured', false)
        ->assertSee('coverageCircles', false);
    $this->actingAs($resident)->getJson(route('tracker.data.client'))
        ->assertOk()
        ->assertJsonPath('trucks.0.id', $truck->id)
        ->assertJsonPath('trucks.0.coverage_radius_meters', null)
        ->assertJsonPath('trucks.0.latitude', null);
});

test('admin tracker loads with coverage controls and map layer', function () {
    Truck::create([
        'code' => 'TRK-OLD',
        'driver' => 'Historical Driver',
        'route' => 'Zone 1',
        'status' => 'offline',
    ]);

    $view = app(TrackerController::class)->index();
    $source = file_get_contents(resource_path('views/admin/tracker.blade.php'));

    expect($view->name())->toBe('admin.tracker')
        ->and($source)->toContain('Coverage Radius (meters)')
        ->toContain('coverageCircleLayer.clearLayers()');
});

test('admin truck creation keeps service zone assignment coordinates', function () {
    $zone = ServiceZone::create([
        'name' => 'Canlanipa Route',
        'barangay' => 'Canlanipa',
        'status' => 'active',
        'latitude' => 9.801,
        'longitude' => 125.501,
    ]);

    $view = app(TrackerController::class)->index();
    $coordinates = $view->getData()['serviceZoneCoordinates'];
    $source = file_get_contents(resource_path('views/admin/tracker.blade.php'));

    expect($coordinates[$zone->display_name])->toBe([
        'lat' => 9.801,
        'lng' => 125.501,
    ])->and($source)->toContain('id="addTruckRoute"')
        ->toContain('syncSelectedRouteLocation')
        ->toContain('serviceZoneCoordinates[selectElement.value]');
});

test('tracker payloads retain independent per truck radius values', function () {
    $resident = User::factory()->create();
    Truck::create(validTruckCoveragePayload(['code' => 'TRK-A', 'coverage_radius_meters' => 300]));
    Truck::create(validTruckCoveragePayload(['code' => 'TRK-B', 'coverage_radius_meters' => 1000]));
    Truck::create(validTruckCoveragePayload(['code' => 'TRK-C', 'coverage_radius_meters' => null]));

    $this->actingAs($resident)->getJson(route('tracker.data.client'))
        ->assertOk()
        ->assertJsonPath('trucks.0.coverage_radius_meters', 300)
        ->assertJsonPath('trucks.1.coverage_radius_meters', 1000)
        ->assertJsonPath('trucks.2.coverage_radius_meters', null);
});

test('residents cannot modify truck radius', function () {
    $resident = User::factory()->create(['is_admin' => false]);
    $truck = Truck::create(validTruckCoveragePayload());

    $this->actingAs($resident)
        ->put(route('admin.tracker.update', $truck->id), validTruckCoveragePayload([
            'coverage_radius_meters' => 900,
        ]))
        ->assertForbidden();

    expect($truck->fresh()->coverage_radius_meters)->toBe(500);
});
