<?php

use App\Models\ActivityLog;
use App\Models\Schedule;
use App\Models\ServiceAreaRequest;
use App\Models\ServiceZone;
use App\Models\Truck;
use App\Models\User;

function createConvertibleAreaRequest(User $resident, User $reviewer, array $overrides = []): ServiceAreaRequest
{
    $attributes = array_merge([
        'area_name' => 'Purok Riverside', 'normalized_area_name' => 'purok riverside',
        'barangay' => 'Barangay Washington', 'address' => 'Near Riverside Chapel',
        'latitude' => '9.76740151', 'longitude' => '125.45395093',
        'details' => 'Resident-submitted details must remain unchanged.',
    ], $overrides);
    $status = $attributes['status'] ?? 'approved';
    unset($attributes['status']);

    $request = $resident->serviceAreaRequests()->create($attributes);
    $request->forceFill([
        'status' => $status, 'reviewed_by' => $reviewer->id,
        'reviewed_at' => now()->subHour()->startOfSecond(), 'admin_notes' => 'Original approval notes.',
    ])->save();

    return $request;
}

function conversionZoneData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Official Riverside Zone', 'barangay' => 'Barangay Washington', 'status' => 'active',
        'latitude' => '9.76740151', 'longitude' => '125.45395093',
    ], $overrides);
}

test('only approved requests can create service zones', function (string $status) {
    $admin = User::factory()->create(['is_admin' => true]);
    $request = createConvertibleAreaRequest(User::factory()->create(), $admin, ['status' => $status]);
    $zoneCount = ServiceZone::count();

    $this->actingAs($admin)->post(route('admin.service-area-requests.create-zone', $request), conversionZoneData([
        'name' => 'Forbidden Zone '.$status,
    ]))->assertSessionHasErrors(['conversion']);

    expect($request->fresh()->service_zone_id)->toBeNull()->and(ServiceZone::count())->toBe($zoneCount);
})->with(['pending', 'rejected']);

test('approved unlinked request shows only the editable create service zone workflow', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $request = createConvertibleAreaRequest(User::factory()->create(), $admin);

    $this->actingAs($admin)->get(route('admin.service-area-requests'))
        ->assertOk()->assertSee('Create Service Zone')
        ->assertSee('action="'.route('admin.service-area-requests.create-zone', $request).'"', false)
        ->assertSee('id="createServiceZoneFromRequestForm'.$request->id.'" method="POST"', false)
        ->assertSee('value="Purok Riverside"', false)->assertSee('value="Barangay Washington"', false)
        ->assertSee('value="9.76740151"', false)->assertSee('value="125.45395093"', false)
        ->assertSee('Zone names must be unique')->assertDontSee('Link Existing Service Zone')
        ->assertDontSee('Confirm Link to Service Zone')->assertDontSee('Change Linked Service Zone');
});

test('approved request creates an editable zone and links the same request without operational records', function () {
    $reviewer = User::factory()->create(['is_admin' => true]);
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create(['service_area' => 'Original Area']);
    $request = createConvertibleAreaRequest($resident, $reviewer);
    $original = $request->only(['area_name', 'normalized_area_name', 'barangay', 'address', 'latitude', 'longitude', 'details']);
    $reviewedAt = $request->reviewed_at->toDateTimeString();
    $counts = [ServiceAreaRequest::count(), Schedule::count(), Truck::count()];

    $this->actingAs($admin)->post(route('admin.service-area-requests.create-zone', $request), conversionZoneData([
        'name' => 'Admin Edited Zone Name', 'barangay' => 'Admin Edited Barangay',
        'latitude' => '10.12345678', 'longitude' => '124.87654321', 'status' => 'inactive',
        'assign_to_requester' => '1',
    ]))->assertSessionHasNoErrors();

    $zone = ServiceZone::where('name', 'Admin Edited Zone Name')->sole();
    $request->refresh();
    expect($zone->barangay)->toBe('Admin Edited Barangay')->and((float) $zone->latitude)->toBe(10.12345678)
        ->and((float) $zone->longitude)->toBe(124.87654321)->and($zone->status)->toBe('inactive')
        ->and($request->service_zone_id)->toBe($zone->id)->and($request->status)->toBe('approved')
        ->and($request->reviewed_by)->toBe($reviewer->id)->and($request->reviewed_at->toDateTimeString())->toBe($reviewedAt)
        ->and($request->admin_notes)->toBe('Original approval notes.')->and($request->only(array_keys($original)))->toBe($original)
        ->and($resident->fresh()->service_area)->toBe($zone->display_name)
        ->and([ServiceAreaRequest::count(), Schedule::count(), Truck::count()])->toBe($counts);

    $activity = ActivityLog::where('action', 'service_area_request.zone_created')->sole();
    expect($activity->model_id)->toBe($request->id)->and($activity->changes)->toMatchArray([
        'request_id' => $request->id, 'service_zone_id' => $zone->id,
        'action_type' => 'created', 'requester_assigned' => true,
    ])->and($activity->changes)->not->toHaveKeys(['latitude', 'longitude', 'address', 'details']);
});

test('unchecked requester assignment preserves the resident service area', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create(['service_area' => 'Keep This Area']);
    $request = createConvertibleAreaRequest($resident, $admin);

    $this->actingAs($admin)->post(route('admin.service-area-requests.create-zone', $request), conversionZoneData())
        ->assertSessionHasNoErrors();

    expect($resident->fresh()->service_area)->toBe('Keep This Area')->and($request->fresh()->service_zone_id)->not->toBeNull();
});

test('invalid or duplicate zone data is rejected without linking the request', function (array $payload, array $errors) {
    $admin = User::factory()->create(['is_admin' => true]);
    $request = createConvertibleAreaRequest(User::factory()->create(), $admin);
    ServiceZone::create(conversionZoneData(['name' => 'Duplicate Zone']));
    $zoneCount = ServiceZone::count();

    $response = $this->actingAs($admin)->post(route('admin.service-area-requests.create-zone', $request), conversionZoneData($payload))
        ->assertSessionHasErrors($errors);
    if (in_array('name', $errors, true)) {
        $response->assertSessionHasErrors(['name' => 'A Service Zone with this name already exists. Review the existing Service Zones before creating another one.']);
    }

    expect($request->fresh()->service_zone_id)->toBeNull()->and(ServiceZone::count())->toBe($zoneCount);
})->with([
    'invalid coordinates' => [['name' => 'Invalid Coordinate Zone', 'latitude' => '91', 'longitude' => '181'], ['latitude', 'longitude']],
    'missing barangay' => [['name' => 'Missing Barangay Zone', 'barangay' => ''], ['barangay']],
    'duplicate zone name' => [['name' => 'Duplicate Zone'], ['name']],
]);

test('normal residents cannot create a service zone from a request', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    $request = createConvertibleAreaRequest($resident, $admin);

    $this->actingAs($resident)->post(route('admin.service-area-requests.create-zone', $request), conversionZoneData())
        ->assertForbidden();
    expect($request->fresh()->service_zone_id)->toBeNull();
});

test('already linked request cannot create a second zone and exposes no setup action', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $request = createConvertibleAreaRequest(User::factory()->create(), $admin);
    $this->actingAs($admin)->post(route('admin.service-area-requests.create-zone', $request), conversionZoneData([
        'name' => 'First Created Zone',
    ]))->assertSessionHasNoErrors();
    $firstZone = $request->fresh()->serviceZone;
    $zoneCount = ServiceZone::count();

    $this->actingAs($admin)->post(route('admin.service-area-requests.create-zone', $request), conversionZoneData([
        'name' => 'Second Zone Must Not Exist',
    ]))->assertSessionHasErrors(['conversion']);

    expect($request->fresh()->service_zone_id)->toBe($firstZone->id)->and(ServiceZone::count())->toBe($zoneCount);
    $this->actingAs($admin)->get(route('admin.service-area-requests'))->assertOk()
        ->assertSee($firstZone->display_name)->assertDontSee('setupServiceAreaRequest'.$request->id)
        ->assertDontSee('Change Linked Service Zone');
});
