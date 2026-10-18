<?php

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function enableSqliteFieldFunctionForReportLocations(): void
{
    if (DB::getDriverName() !== 'sqlite') {
        return;
    }

    DB::connection()->getPdo()->sqliteCreateFunction('FIELD', function ($value, ...$values) {
        $position = array_search($value, $values, true);

        return $position === false ? 0 : $position + 1;
    });
}

beforeEach(function () {
    enableSqliteFieldFunctionForReportLocations();
});

test('web report creation requires trimmed barangay and specific location values', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('reports.store'), [
        'barangay' => '   ',
        'location' => 'In front of Nabago Fire Station',
        'description' => 'Uncollected waste.',
    ])->assertSessionHasErrors('barangay');

    $this->actingAs($user)->post(route('reports.store'), [
        'barangay' => 'Nabago',
        'location' => '   ',
        'description' => 'Uncollected waste.',
    ])->assertSessionHasErrors('location');

    expect(Report::query()->count())->toBe(0);
});

test('api report creation rejects missing readable location even when GPS is supplied', function () {
    $user = User::factory()->create();
    $gps = ['latitude' => 9.78701234, 'longitude' => 125.49281234, 'description' => 'GPS-only report.'];

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/reports', $gps + [
        'location' => 'Near the covered court',
    ])->assertUnprocessable()->assertJsonValidationErrors('barangay');

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/reports', $gps + [
        'barangay' => 'Nabago',
    ])->assertUnprocessable()->assertJsonValidationErrors('location');

    expect(Report::query()->count())->toBe(0);
});

test('a web report with readable location and no GPS succeeds', function () {
    $user = User::factory()->create(['service_area' => 'A different service area']);

    $this->actingAs($user)->post(route('reports.store'), [
        'barangay' => '  Nabago  ',
        'location' => '  In front of Nabago Fire Station  ',
        'description' => 'Garbage bags are blocking the sidewalk.',
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

    $report = Report::query()->sole();
    expect($report->barangay)->toBe('Nabago')
        ->and($report->location)->toBe('In front of Nabago Fire Station')
        ->and($report->latitude)->toBeNull()
        ->and($report->longitude)->toBeNull();
});

test('an API report with readable location and GPS succeeds and serializes every location field', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/reports', [
        'barangay' => 'Nabago',
        'location' => 'In front of Nabago Fire Station',
        'latitude' => 9.78701234,
        'longitude' => 125.49281234,
        'description' => 'Waste has not been collected.',
    ])->assertCreated()
        ->assertJsonPath('data.barangay', 'Nabago')
        ->assertJsonPath('data.location', 'In front of Nabago Fire Station')
        ->assertJsonPath('data.latitude', 9.78701234)
        ->assertJsonPath('data.longitude', 125.49281234);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/reports/'.$response->json('data.id'))
        ->assertOk()
        ->assertJsonPath('data.barangay', 'Nabago')
        ->assertJsonPath('data.location', 'In front of Nabago Fire Station');
});

test('historical reports render a neutral Barangay fallback without using the reporter service area', function () {
    $owner = User::factory()->create(['service_area' => 'Private Home Service Area']);
    $viewer = User::factory()->create();
    $report = Report::query()->create([
        'user_id' => $owner->id,
        'barangay' => null,
        'location' => 'Old riverside landmark',
        'description' => 'Historical report without structured Barangay data.',
        'status' => Report::STATUS_PENDING,
        'priority' => 'medium',
    ]);

    $this->actingAs($viewer)->get(route('community-reports'))
        ->assertOk()
        ->assertSee('Barangay not recorded')
        ->assertSee('Old riverside landmark')
        ->assertDontSee('Private Home Service Area');

    $this->actingAs($viewer)->get(route('reports.show', $report))
        ->assertOk()
        ->assertJsonPath('barangay', null)
        ->assertJsonPath('location', 'Old riverside landmark');

    $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/reports/'.$report->id)
        ->assertOk()
        ->assertJsonPath('data.barangay', null)
        ->assertJsonPath('data.location', 'Old riverside landmark');
});

test('web forms and Admin details clearly separate Barangay from the specific landmark', function () {
    $resident = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    Report::query()->create([
        'user_id' => $resident->id,
        'barangay' => 'Nabago',
        'location' => 'In front of Nabago Fire Station',
        'description' => 'Location presentation test.',
        'status' => Report::STATUS_PENDING,
        'priority' => 'medium',
    ]);

    $this->actingAs($resident)->get(route('community-reports'))
        ->assertOk()
        ->assertSee('Barangay')
        ->assertSee('Specific Location / Landmark')
        ->assertSee('Use Current Location');

    $this->actingAs($admin)->get(route('admin.reports'))
        ->assertOk()
        ->assertSee('Nabago')
        ->assertSee('In front of Nabago Fire Station')
        ->assertSee('viewReportBarangay', false)
        ->assertSee('Specific Location / Landmark');
});

test('editing a historical report requires the resident to add the missing Barangay', function () {
    $owner = User::factory()->create();
    $report = Report::query()->create([
        'user_id' => $owner->id,
        'barangay' => null,
        'location' => 'Original landmark',
        'description' => 'Historical report.',
        'status' => Report::STATUS_PENDING,
        'priority' => 'medium',
    ]);

    $this->actingAs($owner)->patchJson(route('profile.reports.update', $report), [
        'location' => 'Updated landmark',
        'description' => 'Updated report.',
    ])->assertUnprocessable()->assertJsonValidationErrors('barangay');

    $this->actingAs($owner)->patchJson(route('profile.reports.update', $report), [
        'barangay' => 'Nabago',
        'location' => 'Updated landmark',
        'description' => 'Updated report.',
    ])->assertOk();

    expect($report->fresh()->barangay)->toBe('Nabago')
        ->and($report->fresh()->location)->toBe('Updated landmark');
});
