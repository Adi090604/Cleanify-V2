<?php

use App\Models\ServiceAreaRequest;
use App\Models\ServiceZone;
use App\Models\User;

function validServiceAreaRequestData(array $overrides = []): array
{
    return array_merge([
        'area_name' => 'Sitio Riverside',
        'barangay' => 'Barangay San Juan',
        'address' => '123 Riverside Road',
        'latitude' => '9.76740151',
        'longitude' => '125.45395093',
        'details' => 'Several nearby households need collection coverage.',
    ], $overrides);
}

test('an authenticated resident can create a pending service area request belonging to them', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('settings.service-area-requests.store'), validServiceAreaRequestData())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings'));

    $serviceAreaRequest = ServiceAreaRequest::sole();

    expect($serviceAreaRequest->user->is($user))->toBeTrue()
        ->and($serviceAreaRequest->status)->toBe('pending')
        ->and($serviceAreaRequest->normalized_area_name)->toBe('sitio riverside');
});

test('guests and admins cannot submit resident service area requests', function () {
    $this->post(route('settings.service-area-requests.store'), validServiceAreaRequestData())
        ->assertRedirect(route('login'));

    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('settings.service-area-requests.store'), validServiceAreaRequestData())
        ->assertRedirect(route('admin.dashboard'));

    expect(ServiceAreaRequest::count())->toBe(0);
});

test('service area request ownership and review fields are server controlled', function () {
    $user = User::factory()->create();
    $forgedUser = User::factory()->create();
    $forgedReviewer = User::factory()->create(['is_admin' => true]);
    $forgedZone = ServiceZone::create([
        'name' => 'Forged Zone',
        'barangay' => 'Forged Barangay',
        'status' => 'active',
    ]);

    $this->actingAs($user)->post(route('settings.service-area-requests.store'), validServiceAreaRequestData([
        'user_id' => $forgedUser->id,
        'status' => 'approved',
        'admin_notes' => 'Forged notes',
        'reviewed_by' => $forgedReviewer->id,
        'reviewed_at' => now(),
        'service_zone_id' => $forgedZone->id,
    ]))->assertSessionHasNoErrors();

    $serviceAreaRequest = ServiceAreaRequest::sole();

    expect($serviceAreaRequest->user_id)->toBe($user->id)
        ->and($serviceAreaRequest->status)->toBe('pending')
        ->and($serviceAreaRequest->admin_notes)->toBeNull()
        ->and($serviceAreaRequest->reviewed_by)->toBeNull()
        ->and($serviceAreaRequest->reviewed_at)->toBeNull()
        ->and($serviceAreaRequest->service_zone_id)->toBeNull();
});

test('service area request coordinates must be valid and complete', function (array $coordinates, array $errors) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('settings.service-area-requests.store'), validServiceAreaRequestData($coordinates))
        ->assertSessionHasErrors($errors);

    expect(ServiceAreaRequest::count())->toBe(0);
})->with([
    'latitude above range' => [['latitude' => '90.00000001'], ['latitude']],
    'longitude below range' => [['longitude' => '-180.00000001'], ['longitude']],
    'latitude without longitude' => [['longitude' => null], ['longitude']],
    'longitude without latitude' => [['latitude' => null], ['latitude']],
]);

test('a resident cannot submit duplicate pending requests for the same normalized area', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(
        route('settings.service-area-requests.store'),
        validServiceAreaRequestData()
    )->assertSessionHasNoErrors();

    $this->actingAs($user)->post(
        route('settings.service-area-requests.store'),
        validServiceAreaRequestData(['area_name' => '  SITIO   RIVERSIDE  '])
    )->assertSessionHasErrors(['area_name']);

    expect(ServiceAreaRequest::count())->toBe(1);
});

test('a rejected request does not block a future request for the same normalized area', function () {
    $user = User::factory()->create();

    $user->serviceAreaRequests()->create([
        ...validServiceAreaRequestData(),
        'normalized_area_name' => 'sitio riverside',
    ])->forceFill([
        'status' => 'rejected',
    ])->save();

    $this->actingAs($user)->post(
        route('settings.service-area-requests.store'),
        validServiceAreaRequestData(['area_name' => 'SITIO RIVERSIDE'])
    )->assertSessionHasNoErrors();

    expect(ServiceAreaRequest::where('status', 'pending')->count())->toBe(1)
        ->and(ServiceAreaRequest::count())->toBe(2);
});

test('deleting a reviewer preserves the service area request and nulls the reviewer', function () {
    $user = User::factory()->create();
    $reviewer = User::factory()->create(['is_admin' => true]);
    $serviceAreaRequest = $user->serviceAreaRequests()->create([
        ...validServiceAreaRequestData(),
        'normalized_area_name' => 'sitio riverside',
    ])->forceFill([
        'reviewed_by' => $reviewer->id,
    ]);
    $serviceAreaRequest->save();

    $reviewer->delete();

    expect($serviceAreaRequest->fresh())->not->toBeNull()
        ->and($serviceAreaRequest->fresh()->reviewed_by)->toBeNull();
});

test('deleting an associated service zone preserves the request and nulls the zone', function () {
    $user = User::factory()->create();
    $zone = ServiceZone::create([
        'name' => 'Requested Zone',
        'barangay' => 'Requested Barangay',
        'status' => 'active',
    ]);
    $serviceAreaRequest = $user->serviceAreaRequests()->create([
        ...validServiceAreaRequestData(),
        'normalized_area_name' => 'sitio riverside',
    ])->forceFill([
        'service_zone_id' => $zone->id,
    ]);
    $serviceAreaRequest->save();

    $zone->delete();

    expect($serviceAreaRequest->fresh())->not->toBeNull()
        ->and($serviceAreaRequest->fresh()->service_zone_id)->toBeNull();
});

test('deleting the requesting user cascades to their service area requests', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(
        route('settings.service-area-requests.store'),
        validServiceAreaRequestData()
    )->assertSessionHasNoErrors();

    $user->delete();

    expect(ServiceAreaRequest::count())->toBe(0);
});
