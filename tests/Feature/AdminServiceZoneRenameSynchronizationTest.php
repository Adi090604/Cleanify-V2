<?php

use App\Models\Schedule;
use App\Models\ServiceZone;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

function serviceZoneUpdateData(ServiceZone $zone, array $overrides = []): array
{
    return array_merge([
        'name' => $zone->name,
        'barangay' => $zone->barangay,
        'status' => $zone->status,
        'latitude' => $zone->latitude,
        'longitude' => $zone->longitude,
    ], $overrides);
}

function assignedTruck(string $route, array $overrides = []): Truck
{
    return Truck::query()->create(array_merge([
        'code' => 'TRK-RENAME',
        'driver' => 'Rename Driver',
        'route' => $route,
        'status' => 'active',
        'latitude' => '9.76740151',
        'longitude' => '125.45395093',
        'last_updated' => '2026-11-10 08:00:00',
        'coverage_radius_meters' => 750,
    ], $overrides));
}

function assignedSchedule(string $area, array $overrides = []): Schedule
{
    return Schedule::query()->create(array_merge([
        'area' => $area,
        'schedule_type' => 'recurring',
        'recurrence_start_date' => '2026-11-01',
        'recurrence_interval_days' => 3,
        'days' => 'Monday',
        'time_start' => '08:00',
        'time_end' => '10:00',
        'truck' => 'TRK-RENAME',
        'status' => 'active',
    ], $overrides));
}

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

test('renaming a service zone synchronizes exact assignments and preserves all other data', function () {
    $zone = ServiceZone::query()->create([
        'name' => 'Purok One',
        'barangay' => 'Old Barangay',
        'status' => 'active',
        'latitude' => '9.70000000',
        'longitude' => '125.40000000',
    ]);
    $oldDisplayName = $zone->display_name;
    $truck = assignedTruck($oldDisplayName);
    $schedule = assignedSchedule($oldDisplayName);
    $resident = User::factory()->create(['service_area' => $oldDisplayName]);
    $unrelatedTruck = assignedTruck('Unrelated Zone', ['code' => 'TRK-OTHER']);
    $unrelatedSchedule = assignedSchedule('Unrelated Zone', ['truck' => 'TRK-OTHER']);
    $unrelatedUser = User::factory()->create(['service_area' => 'Unrelated Zone']);
    $request = $resident->serviceAreaRequests()->create([
        'area_name' => 'Historical requested area',
        'normalized_area_name' => 'historical requested area',
        'barangay' => 'Historical Barangay',
        'address' => 'Historical address',
        'details' => 'Historical details',
    ]);
    $request->forceFill(['status' => 'approved', 'service_zone_id' => $zone->id])->save();
    $originalRequest = $request->fresh()->getAttributes();
    $originalTruck = $truck->only(['driver', 'status', 'latitude', 'longitude', 'last_updated', 'coverage_radius_meters']);
    $originalSchedule = $schedule->only([
        'schedule_type', 'specific_date', 'recurrence_start_date', 'recurrence_interval_days',
        'days', 'time_start', 'time_end', 'truck', 'status',
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.service-zones.update', $zone), serviceZoneUpdateData($zone, [
            'name' => 'Purok Two',
            'barangay' => 'New Barangay',
        ]))
        ->assertRedirect(route('admin.service-zones'))
        ->assertSessionHas('success');

    $newDisplayName = 'Purok Two - New Barangay';

    expect($zone->fresh()->display_name)->toBe($newDisplayName)
        ->and($truck->fresh()->route)->toBe($newDisplayName)
        ->and($schedule->fresh()->area)->toBe($newDisplayName)
        ->and($resident->fresh()->service_area)->toBe($newDisplayName)
        ->and($truck->fresh()->only(array_keys($originalTruck)))->toEqual($originalTruck)
        ->and($schedule->fresh()->only(array_keys($originalSchedule)))->toEqual($originalSchedule)
        ->and($unrelatedTruck->fresh()->route)->toBe('Unrelated Zone')
        ->and($unrelatedSchedule->fresh()->area)->toBe('Unrelated Zone')
        ->and($unrelatedUser->fresh()->service_area)->toBe('Unrelated Zone')
        ->and($request->fresh()->getAttributes())->toBe($originalRequest);
});

test('coordinate-only and status-only updates do not write assignment fields', function (array $changes) {
    $zone = ServiceZone::query()->create([
        'name' => 'Stable Zone',
        'barangay' => 'Stable Barangay',
        'status' => 'active',
        'latitude' => '9.70000000',
        'longitude' => '125.40000000',
    ]);
    $truck = assignedTruck($zone->display_name);
    $schedule = assignedSchedule($zone->display_name);
    $resident = User::factory()->create(['service_area' => $zone->display_name]);
    $assignmentUpdatedAt = now()->subDays(2)->startOfSecond();

    foreach ([$truck, $schedule, $resident] as $assignment) {
        $assignment->forceFill(['updated_at' => $assignmentUpdatedAt])->saveQuietly();
    }

    $this->actingAs($this->admin)
        ->put(route('admin.service-zones.update', $zone), serviceZoneUpdateData($zone, $changes))
        ->assertRedirect(route('admin.service-zones'))
        ->assertSessionHas('success');

    expect($truck->fresh()->route)->toBe($zone->display_name)
        ->and($schedule->fresh()->area)->toBe($zone->display_name)
        ->and($resident->fresh()->service_area)->toBe($zone->display_name)
        ->and($truck->fresh()->updated_at->equalTo($assignmentUpdatedAt))->toBeTrue()
        ->and($schedule->fresh()->updated_at->equalTo($assignmentUpdatedAt))->toBeTrue()
        ->and($resident->fresh()->updated_at->equalTo($assignmentUpdatedAt))->toBeTrue();
})->with([
    'coordinates only' => [['latitude' => '10.12345678', 'longitude' => '124.12345678']],
    'status only' => [['status' => 'inactive']],
]);

test('an ambiguous old display name is reported without changing the zone or assignments', function () {
    $zone = ServiceZone::query()->create([
        'name' => 'Alpha',
        'barangay' => 'Beta',
        'status' => 'active',
    ]);
    ServiceZone::query()->create([
        'name' => 'Alpha - Beta',
        'barangay' => 'Beta',
        'status' => 'active',
    ]);
    $truck = assignedTruck('Alpha - Beta');

    $this->actingAs($this->admin)
        ->put(route('admin.service-zones.update', $zone), serviceZoneUpdateData($zone, ['name' => 'Renamed Alpha']))
        ->assertRedirect(route('admin.service-zones'))
        ->assertSessionHas('error');

    expect($zone->fresh()->name)->toBe('Alpha')
        ->and($truck->fresh()->route)->toBe('Alpha - Beta');
});

test('existing authorization still prevents residents from renaming service zones', function () {
    $resident = User::factory()->create();
    $zone = ServiceZone::query()->create([
        'name' => 'Protected Zone',
        'barangay' => 'Protected Barangay',
        'status' => 'active',
    ]);
    $truck = assignedTruck($zone->display_name);

    $this->actingAs($resident)
        ->put(route('admin.service-zones.update', $zone), serviceZoneUpdateData($zone, ['name' => 'Forbidden Rename']))
        ->assertForbidden();

    expect($zone->fresh()->name)->toBe('Protected Zone')
        ->and($truck->fresh()->route)->toBe($zone->display_name);
});

test('a related assignment update failure rolls back the entire rename', function () {
    $zone = ServiceZone::query()->create([
        'name' => 'Rollback Zone',
        'barangay' => 'Original Barangay',
        'status' => 'active',
    ]);
    $oldDisplayName = $zone->display_name;
    $truck = assignedTruck($oldDisplayName);
    $schedule = assignedSchedule($oldDisplayName);
    $resident = User::factory()->create(['service_area' => $oldDisplayName]);

    DB::listen(function (QueryExecuted $query): void {
        if (str_starts_with(strtolower($query->sql), 'update "users"')) {
            throw new RuntimeException('Simulated assignment update failure.');
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($this->admin)
        ->put(route('admin.service-zones.update', $zone), serviceZoneUpdateData($zone, ['name' => 'Failed Rename'])))
        ->toThrow(RuntimeException::class, 'Simulated assignment update failure.');

    expect($zone->fresh()->name)->toBe('Rollback Zone')
        ->and($truck->fresh()->route)->toBe($oldDisplayName)
        ->and($schedule->fresh()->area)->toBe($oldDisplayName)
        ->and($resident->fresh()->service_area)->toBe($oldDisplayName);
});
