<?php

use App\Models\ServiceZone;
use App\Models\Truck;
use App\Models\User;

test('resident tracker uses the resident fleet layout without changing tracker behavior', function () {
    $user = User::factory()->create();
    ServiceZone::create([
        'name' => 'Resident Overlay Zone',
        'barangay' => 'Canlanipa',
        'status' => 'active',
        'latitude' => 9.79,
        'longitude' => 125.49,
    ]);
    Truck::create([
        'code' => 'TRK-101',
        'driver' => 'Driver One',
        'route' => 'Zone 1 - Brgy. Alang-Alang',
        'status' => 'active',
        'coverage_radius_meters' => 1200,
        'latitude' => 9.787,
        'longitude' => 125.4928,
        'last_updated' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('tracker'));

    $response->assertOk()
        ->assertSee('Truck Tracker')
        ->assertSee('View current truck locations and service assignments.')
        ->assertSee('TRK-101')
        ->assertSee('Driver One')
        ->assertSee('Zone 1 - Brgy. Alang-Alang')
        ->assertSee('Auto-refreshing')
        ->assertSee('Service assignment')
        ->assertSee('Coverage Radius')
        ->assertSee('Selected truck')
        ->assertSee('id="map"', false)
        ->assertSee('id="truckSearch"', false)
        ->assertSee('id="statusFilter"', false)
        ->assertSee('id="truckDetailPanel"', false)
        ->assertSee('resident-tracker-grid')
        ->assertSee('Focus')
        ->assertDontSee('Highlight a service zone')
        ->assertDontSee('Resident Overlay Zone')
        ->assertDontSee('id="zoneFilterGroup"', false)
        ->assertDontSee('id="clearZoneHighlight"', false)
        ->assertDontSee('const zones =', false)
        ->assertDontSee('zoneLayers', false)
        ->assertDontSee('drawZones', false)
        ->assertDontSee('L.circle([coords.lat, coords.lng]', false)
        ->assertDontSee('Active Trucks')
        ->assertDontSee('Live Truck Tracker')
        ->assertDontSee('Live updating')
        ->assertDontSee('ETA')
        ->assertDontSee('View route')
        ->assertDontSee('route-history-btn', false)
        ->assertDontSee('detailRouteBtn', false)
        ->assertSee("marker.on('click', () => showTruckDetail(truckCache[truck.id] ?? truck));", false)
        ->assertSee('const coverageCircleLayer = L.layerGroup().addTo(map);', false)
        ->assertSee('existingCircle.setLatLng([latitude, longitude]);', false)
        ->assertSee('existingCircle.setRadius(radius);', false)
        ->assertSee('coverageCircleLayer.removeLayer(circle);', false)
        ->assertSee('removeCoverageCircle(id);', false)
        ->assertSee("document.getElementById('truckSearch').addEventListener('input', filterList);", false)
        ->assertSee("document.getElementById('statusFilter').addEventListener('change', filterList);", false)
        ->assertSee("document.getElementById('manualRefreshBtn')?.addEventListener('click', fetchTrucks);", false)
        ->assertSee('setInterval(() =>', false);
});
