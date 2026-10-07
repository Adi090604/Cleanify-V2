<?php

use App\Models\Schedule;
use App\Models\ServiceAreaRequest;
use App\Models\ServiceZone;
use App\Models\User;

function createSettingsServiceAreaRequest(User $user, string $status = 'pending', array $overrides = []): ServiceAreaRequest
{
    $request = $user->serviceAreaRequests()->create(array_merge([
        'area_name' => 'Purok 8',
        'normalized_area_name' => 'purok 8',
        'barangay' => 'Barangay Washington',
        'address' => 'Near the covered court',
        'details' => 'Several households need service.',
    ], $overrides));

    $request->forceFill(['status' => $status])->save();

    return $request;
}

test('settings renders the request action and preserves existing service area options', function () {
    $user = User::factory()->create(['service_area' => 'Zone 1 - Barangay One']);
    ServiceZone::create([
        'name' => 'Zone 1',
        'barangay' => 'Barangay One',
        'status' => 'active',
    ]);
    Schedule::create([
        'area' => 'Legacy Schedule Area',
        'schedule_type' => 'recurring',
        'days' => 'Monday',
        'time_start' => '08:00',
        'time_end' => '10:00',
        'truck' => 'TRK-01',
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->get(route('settings'));

    $response->assertOk()
        ->assertSee('My area is not listed')
        ->assertSee('Request Service for Your Area')
        ->assertSee('Zone 1 - Barangay One')
        ->assertSee('Legacy Schedule Area')
        ->assertSee('action="'.route('settings.service-area-requests.store').'"', false)
        ->assertDontSee('<option value="My area is not listed"', false);

    expect($user->fresh()->service_area)->toBe('Zone 1 - Barangay One');
});

test('settings shows the latest pending service area request', function () {
    $user = User::factory()->create();
    createSettingsServiceAreaRequest($user);

    $this->actingAs($user)->get(route('settings'))
        ->assertOk()
        ->assertSee('Service Area Request')
        ->assertSee('Purok 8, Barangay Washington')
        ->assertSee('Pending Review')
        ->assertSee('waiting for administrator review');
});

test('settings renders an approved service area request without promising a schedule', function () {
    $user = User::factory()->create();
    createSettingsServiceAreaRequest($user, 'approved');

    $this->actingAs($user)->get(route('settings'))
        ->assertOk()
        ->assertSee('Approved')
        ->assertSee('Your requested area has been approved.')
        ->assertSee('does not automatically create a collection schedule');
});

test('settings renders a rejected request without exposing moderation fields', function () {
    $user = User::factory()->create();
    $reviewer = User::factory()->create([
        'name' => 'Private Reviewer',
        'email' => 'private-reviewer@example.test',
        'is_admin' => true,
    ]);
    $request = createSettingsServiceAreaRequest($user, 'rejected');
    $request->forceFill([
        'admin_notes' => 'Internal moderation note that residents must not see.',
        'reviewed_by' => $reviewer->id,
        'reviewed_at' => now(),
    ])->save();

    $this->actingAs($user)->get(route('settings'))
        ->assertOk()
        ->assertSee('Rejected')
        ->assertSee('Your request was not approved.')
        ->assertDontSee('Internal moderation note that residents must not see.')
        ->assertDontSee('Private Reviewer')
        ->assertDontSee('private-reviewer@example.test');
});

test('successful request submission returns to settings without changing the current service area', function () {
    $user = User::factory()->create(['service_area' => 'Zone 2 - Barangay Two']);

    $this->actingAs($user)
        ->from(route('settings'))
        ->post(route('settings.service-area-requests.store'), [
            'area_name' => 'Purok Riverside',
            'barangay' => 'Barangay Two',
            'address' => 'Beside Riverside Chapel',
            'latitude' => '9.76740151',
            'longitude' => '125.45395093',
            'details' => 'Requesting area review only.',
            'service_area' => 'My area is not listed',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings'));

    expect($user->fresh()->service_area)->toBe('Zone 2 - Barangay Two')
        ->and(ServiceAreaRequest::sole()->status)->toBe('pending');
});

test('request validation errors return to settings and keep the request form available', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('settings'))
        ->post(route('settings.service-area-requests.store'), [
            'area_name' => '',
            'latitude' => '9.76740151',
        ]);

    $response->assertRedirect(route('settings'))
        ->assertSessionHasErrors(['area_name', 'longitude']);

    $this->actingAs($user)
        ->withSession(['errors' => session('errors')])
        ->get(route('settings'))
        ->assertOk()
        ->assertSee('serviceAreaRequestModal')
        ->assertSee('serviceAreaRequestHasErrors = true', false);
});
