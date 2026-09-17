<?php

use App\Models\ServiceZone;
use App\Models\User;

test('service zone coordinate fields are editable and expose standard coordinate bounds', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)->get(route('admin.service-zones'));

    $response->assertOk();
    $html = $response->getContent();

    foreach (['create', 'edit'] as $key) {
        $this->assertMatchesRegularExpression(
            '/id="'.$key.'ServiceZoneLatitude"[^>]*min="-90"[^>]*max="90"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="'.$key.'ServiceZoneLongitude"[^>]*min="-180"[^>]*max="180"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="'.$key.'ServiceZone(?:Latitude|Longitude)"[^>]*readonly/',
            $html
        );
    }
});

test('admins can create and update service zones with valid manual coordinates', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->post(route('admin.service-zones.store'), [
        'name' => 'Manual Zone',
        'barangay' => 'Manual Barangay',
        'status' => 'active',
        'latitude' => '9.76740151',
        'longitude' => '125.45395093',
    ])->assertRedirect(route('admin.service-zones'));

    $zone = ServiceZone::where('name', 'Manual Zone')->firstOrFail();

    expect((float) $zone->latitude)->toBe(9.76740151)
        ->and((float) $zone->longitude)->toBe(125.45395093);

    $this->put(route('admin.service-zones.update', $zone->id), [
        'name' => 'Manual Zone',
        'barangay' => 'Manual Barangay',
        'status' => 'inactive',
        'latitude' => '-12.34567890',
        'longitude' => '-170.12345678',
    ])->assertRedirect(route('admin.service-zones'));

    $zone->refresh();

    expect((float) $zone->latitude)->toBe(-12.34567890)
        ->and((float) $zone->longitude)->toBe(-170.12345678)
        ->and($zone->status)->toBe('inactive');
});

test('service zone requests reject coordinates outside valid ranges', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->post(route('admin.service-zones.store'), [
        'name' => 'Invalid Coordinates',
        'barangay' => 'Invalid Barangay',
        'status' => 'active',
        'latitude' => '90.00000001',
        'longitude' => '180.00000001',
    ])->assertSessionHasErrors(['latitude', 'longitude']);

    $this->assertDatabaseMissing('service_zones', ['name' => 'Invalid Coordinates']);
});
