<?php

use App\Models\ServiceAreaRequest;
use App\Models\ServiceZone;
use App\Models\User;

function validApiServiceAreaRequestData(array $overrides = []): array
{
    return array_merge([
        'area_name' => 'Sitio Mobile Riverside',
        'barangay' => 'Barangay Mobile',
        'address' => '45 Mobile Road',
        'latitude' => '9.76740151',
        'longitude' => '125.45395093',
        'details' => 'Mobile resident request details.',
    ], $overrides);
}

function apiServiceAreaRequest(User $user, array $overrides = []): ServiceAreaRequest
{
    $attributes = array_merge(validApiServiceAreaRequestData(), $overrides);
    $status = $attributes['status'] ?? 'pending';
    $serviceZoneId = $attributes['service_zone_id'] ?? null;
    unset($attributes['status'], $attributes['service_zone_id']);

    $request = $user->serviceAreaRequests()->create([
        ...$attributes,
        'normalized_area_name' => ServiceAreaRequest::normalizeAreaName($attributes['area_name']),
    ]);
    $request->forceFill(['status' => $status, 'service_zone_id' => $serviceZoneId])->save();

    return $request;
}

test('service area request API endpoints require Sanctum authentication', function () {
    $this->getJson('/api/v1/service-area-requests/latest')->assertUnauthorized();
    $this->postJson('/api/v1/service-area-requests', validApiServiceAreaRequestData())->assertUnauthorized();

    expect(ServiceAreaRequest::count())->toBe(0);
});

test('latest returns a clean null response when the resident has no request', function () {
    $resident = User::factory()->create();

    $this->actingAs($resident, 'sanctum')->getJson('/api/v1/service-area-requests/latest')
        ->assertOk()
        ->assertExactJson(['data' => null]);
});

test('latest returns only the authenticated residents newest request with safe fields', function () {
    $resident = User::factory()->create();
    $other = User::factory()->create();
    apiServiceAreaRequest($resident, ['area_name' => 'Older Own Area'])->forceFill(['created_at' => now()->subDay()])->save();
    $latest = apiServiceAreaRequest($resident, ['area_name' => 'Newest Own Area', 'status' => 'rejected']);
    $latest->forceFill([
        'admin_notes' => 'Private moderation notes',
        'reviewed_by' => User::factory()->create(['is_admin' => true])->id,
        'reviewed_at' => now(),
    ])->save();
    apiServiceAreaRequest($other, ['area_name' => 'Other Resident Private Area']);

    $this->actingAs($resident, 'sanctum')->getJson('/api/v1/service-area-requests/latest')
        ->assertOk()
        ->assertJsonPath('data.id', $latest->id)
        ->assertJsonPath('data.area_name', 'Newest Own Area')
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonMissingPath('data.user_id')
        ->assertJsonMissingPath('data.normalized_area_name')
        ->assertJsonMissingPath('data.admin_notes')
        ->assertJsonMissingPath('data.reviewed_by')
        ->assertJsonMissingPath('data.reviewed_at')
        ->assertJsonMissingPath('data.reviewer')
        ->assertJsonMissingPath('data.activity_logs')
        ->assertJsonMissing(['Other Resident Private Area', 'Private moderation notes']);
});

test('authenticated resident can submit a pending request without changing current service area', function () {
    $resident = User::factory()->create(['service_area' => 'Current Official Area']);

    $this->actingAs($resident, 'sanctum')->postJson('/api/v1/service-area-requests', validApiServiceAreaRequestData())
        ->assertCreated()
        ->assertJsonPath('message', 'Service area request submitted successfully.')
        ->assertJsonPath('data.area_name', 'Sitio Mobile Riverside')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.latitude', 9.76740151)
        ->assertJsonPath('data.longitude', 125.45395093)
        ->assertJsonPath('data.service_zone', null);

    $created = ServiceAreaRequest::query()->sole();
    expect($created->user_id)->toBe($resident->id)
        ->and($created->normalized_area_name)->toBe('sitio mobile riverside')
        ->and($created->status)->toBe('pending')
        ->and($resident->fresh()->service_area)->toBe('Current Official Area');
});

test('ownership and moderation fields cannot be forged through the API', function () {
    $resident = User::factory()->create();
    $forgedOwner = User::factory()->create();
    $forgedReviewer = User::factory()->create(['is_admin' => true]);
    $forgedZone = ServiceZone::create([
        'name' => 'Forged API Zone', 'barangay' => 'Forged Barangay', 'status' => 'active',
    ]);

    $this->actingAs($resident, 'sanctum')->postJson('/api/v1/service-area-requests', validApiServiceAreaRequestData([
        'user_id' => $forgedOwner->id,
        'normalized_area_name' => 'forged normalization',
        'status' => 'approved',
        'admin_notes' => 'Forged notes',
        'reviewed_by' => $forgedReviewer->id,
        'reviewed_at' => now()->toIso8601String(),
        'service_zone_id' => $forgedZone->id,
    ]))->assertCreated()
        ->assertJsonMissingPath('data.user_id')
        ->assertJsonMissingPath('data.admin_notes')
        ->assertJsonMissingPath('data.reviewed_by');

    $created = ServiceAreaRequest::query()->sole();
    expect($created->user_id)->toBe($resident->id)
        ->and($created->normalized_area_name)->toBe('sitio mobile riverside')
        ->and($created->status)->toBe('pending')
        ->and($created->admin_notes)->toBeNull()
        ->and($created->reviewed_by)->toBeNull()
        ->and($created->reviewed_at)->toBeNull()
        ->and($created->service_zone_id)->toBeNull();
});

test('coordinate validation matches the web request rules', function (array $coordinates, array $errors) {
    $resident = User::factory()->create();

    $this->actingAs($resident, 'sanctum')->postJson(
        '/api/v1/service-area-requests',
        validApiServiceAreaRequestData($coordinates)
    )->assertUnprocessable()->assertJsonValidationErrors($errors);

    expect(ServiceAreaRequest::count())->toBe(0);
})->with([
    'latitude without longitude' => [['longitude' => null], ['longitude']],
    'longitude without latitude' => [['latitude' => null], ['latitude']],
    'latitude above range' => [['latitude' => '90.00000001'], ['latitude']],
    'longitude below range' => [['longitude' => '-180.00000001'], ['longitude']],
]);

test('normalized duplicate pending request is rejected', function () {
    $resident = User::factory()->create();
    apiServiceAreaRequest($resident);

    $this->actingAs($resident, 'sanctum')->postJson('/api/v1/service-area-requests', validApiServiceAreaRequestData([
        'area_name' => '  SITIO   MOBILE RIVERSIDE ',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors('area_name')
        ->assertJsonPath('errors.area_name.0', 'You already have a pending request for this service area.');

    expect(ServiceAreaRequest::count())->toBe(1);
});

test('rejected request permits a future submission of the same normalized area', function () {
    $resident = User::factory()->create();
    apiServiceAreaRequest($resident, ['status' => 'rejected']);

    $this->actingAs($resident, 'sanctum')->postJson('/api/v1/service-area-requests', validApiServiceAreaRequestData([
        'area_name' => ' SITIO  MOBILE RIVERSIDE ',
    ]))->assertCreated()->assertJsonPath('data.status', 'pending');

    expect(ServiceAreaRequest::count())->toBe(2)
        ->and(ServiceAreaRequest::where('status', 'pending')->count())->toBe(1);
});

test('banned residents and admins cannot use service area request mobile endpoints', function (array $attributes, string $message) {
    $user = User::factory()->create($attributes);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/service-area-requests/latest')
        ->assertForbidden()->assertExactJson(['message' => $message]);
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/service-area-requests', validApiServiceAreaRequestData())
        ->assertForbidden()->assertExactJson(['message' => $message]);

    expect(ServiceAreaRequest::count())->toBe(0);
})->with([
    'banned resident' => [['banned_at' => now()], 'Your account has been banned. Please contact an administrator.'],
    'admin' => [['is_admin' => true], 'Admin accounts can only sign in through the web Admin Portal.'],
]);

test('latest safely includes the created official service zone without schedule claims', function () {
    $resident = User::factory()->create();
    $zone = ServiceZone::create([
        'name' => 'Official Mobile Zone', 'barangay' => 'Barangay Canonical', 'status' => 'active',
    ]);
    $request = apiServiceAreaRequest($resident, [
        'status' => 'approved', 'service_zone_id' => $zone->id,
    ]);

    $this->actingAs($resident, 'sanctum')->getJson('/api/v1/service-area-requests/latest')
        ->assertOk()
        ->assertJsonPath('data.id', $request->id)
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.service_zone.id', $zone->id)
        ->assertJsonPath('data.service_zone.name', 'Official Mobile Zone')
        ->assertJsonPath('data.service_zone.display_name', 'Official Mobile Zone - Barangay Canonical')
        ->assertJsonPath('data.service_zone.status', 'active')
        ->assertJsonMissingPath('data.service_zone.latitude')
        ->assertJsonMissingPath('data.schedule')
        ->assertJsonMissingPath('data.truck');
});
