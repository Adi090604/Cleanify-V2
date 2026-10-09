<?php

use App\Models\Truck;
use App\Models\User;

test('resident tracker keeps truck details and map controls without route actions', function () {
    $user = User::factory()->create();
    Truck::create([
        'code' => 'TRK-101',
        'driver' => 'Driver One',
        'route' => 'Zone 1 - Brgy. Alang-Alang',
        'status' => 'active',
        'latitude' => 9.787,
        'longitude' => 125.4928,
        'last_updated' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('tracker'));

    $response->assertOk()
        ->assertSee('TRK-101')
        ->assertSee('Driver One')
        ->assertSee('Zone 1 - Brgy. Alang-Alang')
        ->assertSee('Center map')
        ->assertSee('Focus')
        ->assertDontSee('View route')
        ->assertDontSee('route-history-btn', false)
        ->assertDontSee('detailRouteBtn', false)
        ->assertSee("marker.on('click', () => showTruckDetail(truck));", false)
        ->assertSee("document.getElementById('manualRefreshBtn')?.addEventListener('click', fetchTrucks);", false)
        ->assertSee('setInterval(() =>', false);
});
