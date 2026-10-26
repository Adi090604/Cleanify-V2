<?php

use App\Models\ServiceZone;
use App\Models\User;

function serviceZoneFormData(string $name, string $barangay): array
{
    return [
        'name' => $name,
        'barangay' => $barangay,
        'status' => 'active',
        'latitude' => null,
        'longitude' => null,
    ];
}

test('admin service zone creation uses the normalized local area and Barangay pair', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->post(route('admin.service-zones.store'), serviceZoneFormData('Purok 1', 'Canlanipa'))
        ->assertSessionHasNoErrors();
    $this->post(route('admin.service-zones.store'), serviceZoneFormData('Purok 1', 'Washington'))
        ->assertSessionHasNoErrors();

    foreach ([
        ['Purok 1', 'Canlanipa'],
        ['PUROK 1', 'CANLANIPA'],
        ['  Purok   1  ', ' Canlanipa '],
    ] as [$name, $barangay]) {
        $this->post(route('admin.service-zones.store'), serviceZoneFormData($name, $barangay))
            ->assertSessionHasErrors(['name' => 'This service area already exists in the selected Barangay.']);
    }

    $this->post(route('admin.service-zones.store'), serviceZoneFormData('Purok 1 Don Julio', 'Canlanipa'))
        ->assertSessionHasNoErrors();
    $this->post(route('admin.service-zones.store'), serviceZoneFormData('Espina', 'Taft'))
        ->assertSessionHasNoErrors();
    $this->post(route('admin.service-zones.store'), serviceZoneFormData('Espina Extension', 'Taft'))
        ->assertSessionHasNoErrors();

    expect(ServiceZone::count())->toBe(5)
        ->and(ServiceZone::where('name', 'Purok 1')->pluck('barangay')->all())
        ->toEqualCanonicalizing(['Canlanipa', 'Washington'])
        ->and(ServiceZone::where('barangay', 'Canlanipa')->pluck('name')->all())
        ->toEqualCanonicalizing(['Purok 1', 'Purok 1 Don Julio']);
});

test('service zone directory and forms use the final labels and render historical null Barangays safely', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $zone = ServiceZone::query()->create([
        'name' => 'Historical Zone',
        'barangay' => null,
        'status' => 'active',
    ]);

    $this->actingAs($admin)->get(route('admin.service-zones'))
        ->assertOk()
        ->assertSee('Service Zone Directory')
        ->assertSee('Service Area')
        ->assertSee('Barangay')
        ->assertSee('Local Area / Purok / Sitio')
        ->assertSee('Map Location')
        ->assertSee('Historical Zone');

    $this->put(route('admin.service-zones.update', $zone), serviceZoneFormData('Historical Zone', ''))
        ->assertSessionHasErrors(['barangay' => 'Barangay is required.']);

    expect($zone->fresh()->barangay)->toBeNull();

    $this->put(route('admin.service-zones.update', $zone), serviceZoneFormData('Historical Zone', 'Canlanipa'))
        ->assertSessionHasNoErrors();

    expect($zone->fresh()->barangay)->toBe('Canlanipa');
});

test('residents cannot create service zones', function () {
    $resident = User::factory()->create();

    $this->actingAs($resident)
        ->post(route('admin.service-zones.store'), serviceZoneFormData('Purok 1', 'Canlanipa'))
        ->assertForbidden();

    expect(ServiceZone::count())->toBe(0);
});

test('resident and admin request views use the final local area and Barangay presentation', function () {
    $resident = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $request = $resident->serviceAreaRequests()->create([
        'area_name' => 'Purok Review',
        'normalized_area_name' => 'purok review',
        'barangay' => 'Canlanipa',
        'address' => 'Near covered court',
        'details' => 'Optional notes',
    ]);
    $request->forceFill([
        'status' => 'approved',
        'reviewed_by' => $admin->id,
        'reviewed_at' => now(),
    ])->save();

    $this->actingAs($resident)->get(route('settings'))
        ->assertOk()
        ->assertSee('Area / Purok / Sitio')
        ->assertSee('Barangay')
        ->assertSee('e.g. Purok 1, Don Julio, Espina Extension')
        ->assertSee('e.g. Canlanipa');

    $this->actingAs($admin)->get(route('admin.service-area-requests'))
        ->assertOk()
        ->assertSee('Requested Area / Purok / Sitio')
        ->assertSee('Address / Landmark')
        ->assertSee('Coordinates')
        ->assertSee('Additional Details')
        ->assertSee('value="Purok Review"', false)
        ->assertSee('value="Canlanipa"', false);

    expect($request->fresh()->barangay)->toBe('Canlanipa');
});
