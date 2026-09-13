<?php

use App\Models\Truck;
use App\Models\TruckLocation;
use App\Models\ServiceZone;
use App\Models\User;

test('the mobile truck endpoint requires Sanctum authentication', function () {
    $this->getJson('/api/v1/trucks')->assertUnauthorized();
});

test('authenticated users receive real trucks ordered by code', function () {
    $user = User::factory()->create();
    $second = Truck::create(['code' => 'TRK-002', 'driver' => 'Driver B', 'route' => 'Zone 2', 'status' => 'offline']);
    $first = Truck::create(['code' => 'TRK-001', 'driver' => 'Driver A', 'route' => 'Zone 1', 'status' => 'active', 'latitude' => 9.78, 'longitude' => 125.49, 'last_updated' => now()]);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/trucks')
        ->assertOk()
        ->assertJsonPath('trucks.0.id', $first->id)
        ->assertJsonPath('trucks.0.code', 'TRK-001')
        ->assertJsonPath('trucks.0.formatted_status', 'Active')
        ->assertJsonPath('trucks.1.id', $second->id)
        ->assertJsonPath('trucks.1.latitude', null)
        ->assertJsonMissingPath('trucks.0.created_at')
        ->assertJsonMissingPath('trucks.0.updated_at');
});

test('tracker current location follows the truck fields used by the web tracker', function () {
    $user = User::factory()->create();
    $truck = Truck::create(['code' => 'TRK-001', 'driver' => 'Driver A', 'route' => 'Zone 1', 'status' => 'active', 'latitude' => 9.80, 'longitude' => 125.50, 'last_updated' => now()]);
    TruckLocation::create(['truck_id' => $truck->id, 'latitude' => 1, 'longitude' => 2, 'recorded_at' => now()->subMinute()]);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/trucks')
        ->assertOk()
        ->assertJsonPath('trucks.0.latitude', 9.8)
        ->assertJsonPath('trucks.0.longitude', 125.5)
        ->assertJsonPath('map.center.latitude', 9.8)
        ->assertJsonPath('map.center.longitude', 125.5);
});

test('tracker handles no trucks and trucks without locations cleanly', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/trucks')
        ->assertOk()->assertJsonCount(0, 'trucks')
        ->assertJsonPath('map.center.latitude', 9.787)
        ->assertJsonPath('map.center.longitude', 125.4928);

    Truck::create(['code' => 'TRK-001', 'driver' => 'Driver A', 'route' => 'Zone 1', 'status' => 'maintenance']);
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/trucks')
        ->assertOk()->assertJsonPath('trucks.0.latitude', null)->assertJsonPath('trucks.0.last_updated', null);
});

test('tracker exposes only active located service zones used by the web tracker', function () {
    $user = User::factory()->create();
    $zone = ServiceZone::create(['name' => 'Central', 'barangay' => 'Barangay 1', 'status' => 'active', 'latitude' => 9.79, 'longitude' => 125.49]);
    ServiceZone::create(['name' => 'Inactive', 'status' => 'inactive', 'latitude' => 9.8, 'longitude' => 125.5]);
    ServiceZone::create(['name' => 'Unmapped', 'status' => 'active']);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/trucks')
        ->assertOk()->assertJsonCount(1, 'zones')
        ->assertJsonPath('zones.0.id', $zone->id)
        ->assertJsonPath('zones.0.name', 'Central - Barangay 1')
        ->assertJsonMissingPath('zones.0.status');
});

test('authenticated users receive the real ordered 24 hour route history', function () {
    $user = User::factory()->create();
    $truck = Truck::create(['code' => 'TRK-001', 'driver' => 'Driver A', 'route' => 'Zone 1', 'status' => 'active']);
    TruckLocation::create(['truck_id' => $truck->id, 'latitude' => 1, 'longitude' => 2, 'recorded_at' => now()->subDays(2)]);
    $first = TruckLocation::create(['truck_id' => $truck->id, 'latitude' => 9.78, 'longitude' => 125.48, 'recorded_at' => now()->subHours(2)]);
    $second = TruckLocation::create(['truck_id' => $truck->id, 'latitude' => 9.79, 'longitude' => 125.49, 'recorded_at' => now()->subHour()]);

    $this->actingAs($user, 'sanctum')->getJson("/api/v1/trucks/{$truck->id}/route-history")
        ->assertOk()->assertJsonCount(2, 'locations')
        ->assertJsonPath('locations.0.latitude', (float) $first->latitude)
        ->assertJsonPath('locations.1.latitude', (float) $second->latitude)
        ->assertJsonMissingPath('locations.0.created_at');
});
