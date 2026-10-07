<?php

use App\Models\ActivityLog;
use App\Models\Schedule;
use App\Models\ServiceAreaRequest;
use App\Models\ServiceZone;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Support\Facades\Blade;

function createAdminServiceAreaRequest(User $user, array $overrides = []): ServiceAreaRequest
{
    $attributes = array_merge([
        'area_name' => 'Purok Riverside',
        'normalized_area_name' => 'purok riverside',
        'barangay' => 'Barangay Washington',
        'address' => 'Near Riverside Chapel',
        'latitude' => '9.76740151',
        'longitude' => '125.45395093',
        'details' => 'Several households are requesting service-area consideration.',
    ], $overrides);
    $status = $attributes['status'] ?? 'pending';
    unset($attributes['status']);

    $serviceAreaRequest = $user->serviceAreaRequests()->create($attributes);
    $serviceAreaRequest->forceFill(['status' => $status])->save();

    return $serviceAreaRequest;
}

test('normal users cannot access or forge admin service area request reviews', function () {
    $user = User::factory()->create();
    $serviceAreaRequest = createAdminServiceAreaRequest($user);

    $this->actingAs($user)
        ->get(route('admin.service-area-requests'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('admin.service-area-requests.approve', $serviceAreaRequest), [
            'admin_notes' => 'Forged resident approval.',
        ])
        ->assertForbidden();

    expect($serviceAreaRequest->fresh()->status)->toBe('pending')
        ->and($serviceAreaRequest->fresh()->reviewed_by)->toBeNull();
});

test('admin can view pending approved and rejected service area requests', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    createAdminServiceAreaRequest($resident, ['area_name' => 'Pending Area']);
    createAdminServiceAreaRequest($resident, [
        'area_name' => 'Approved Area',
        'normalized_area_name' => 'approved area',
        'status' => 'approved',
    ]);
    createAdminServiceAreaRequest($resident, [
        'area_name' => 'Rejected Area',
        'normalized_area_name' => 'rejected area',
        'status' => 'rejected',
    ]);

    $this->actingAs($admin)->get(route('admin.service-area-requests'))
        ->assertOk()
        ->assertSee('Service Area Requests')
        ->assertSee('Pending Area')
        ->assertSee('Pending Review')
        ->assertSee('Approved Area')
        ->assertSee('Approved')
        ->assertSee('Rejected Area')
        ->assertSee('Rejected');
});

test('admin service area request status filter shows only the selected status', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    createAdminServiceAreaRequest($resident, ['area_name' => 'Only Pending Area']);
    createAdminServiceAreaRequest($resident, [
        'area_name' => 'Hidden Approved Area',
        'normalized_area_name' => 'hidden approved area',
        'status' => 'approved',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.service-area-requests', ['status' => 'pending']))
        ->assertOk()
        ->assertSee('Only Pending Area')
        ->assertDontSee('Hidden Approved Area')
        ->assertSee('<option value="pending" selected>Pending</option>', false);
});

test('request details show resident area and submitted coordinates', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create([
        'name' => 'Resident Reviewer Test',
        'email' => 'resident-details@example.test',
        'service_area' => 'Existing Zone',
    ]);
    $serviceAreaRequest = createAdminServiceAreaRequest($resident);

    $this->actingAs($admin)->get(route('admin.service-area-requests'))
        ->assertOk()
        ->assertSee('Resident Reviewer Test')
        ->assertSee('resident-details@example.test')
        ->assertSee('Existing Zone')
        ->assertSee('Purok Riverside')
        ->assertSee('Barangay Washington')
        ->assertSee('Near Riverside Chapel')
        ->assertSee('Several households are requesting service-area consideration.')
        ->assertSee('9.767402')
        ->assertSee('125.453951')
        ->assertSee('serviceAreaRequestMap'.$serviceAreaRequest->id);
});

test('request details show a clean no-location state', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    createAdminServiceAreaRequest($resident, [
        'area_name' => 'No Location Area',
        'normalized_area_name' => 'no location area',
        'latitude' => null,
        'longitude' => null,
    ]);

    $this->actingAs($admin)->get(route('admin.service-area-requests'))
        ->assertOk()
        ->assertSee('No location was shared with this request.');
});

test('admin approval records review metadata without creating operational records', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create(['service_area' => 'Existing Resident Area']);
    $serviceAreaRequest = createAdminServiceAreaRequest($resident);
    $originalResidentFields = $serviceAreaRequest->only([
        'user_id', 'area_name', 'normalized_area_name', 'barangay', 'address', 'latitude', 'longitude', 'details',
    ]);
    $zoneCount = ServiceZone::count();
    $scheduleCount = Schedule::count();
    $truckCount = Truck::count();

    $this->actingAs($admin)
        ->from(route('admin.service-area-requests'))
        ->post(route('admin.service-area-requests.approve', $serviceAreaRequest), [
            'admin_notes' => 'Accepted for future service-area consideration.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.service-area-requests'));

    $serviceAreaRequest->refresh();

    expect($serviceAreaRequest->status)->toBe('approved')
        ->and($serviceAreaRequest->reviewed_by)->toBe($admin->id)
        ->and($serviceAreaRequest->reviewed_at)->not->toBeNull()
        ->and($serviceAreaRequest->admin_notes)->toBe('Accepted for future service-area consideration.')
        ->and($serviceAreaRequest->service_zone_id)->toBeNull()
        ->and($serviceAreaRequest->only(array_keys($originalResidentFields)))->toBe($originalResidentFields)
        ->and($resident->fresh()->service_area)->toBe('Existing Resident Area')
        ->and(ServiceZone::count())->toBe($zoneCount)
        ->and(Schedule::count())->toBe($scheduleCount)
        ->and(Truck::count())->toBe($truckCount);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $admin->id,
        'action' => 'service_area_request.approved',
        'model_type' => ServiceAreaRequest::class,
        'model_id' => $serviceAreaRequest->id,
    ]);
});

test('admin can reject a pending request while preserving it and its original fields', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    $serviceAreaRequest = createAdminServiceAreaRequest($resident);
    $originalArea = $serviceAreaRequest->area_name;

    $this->actingAs($admin)
        ->post(route('admin.service-area-requests.reject', $serviceAreaRequest), [
            'admin_notes' => 'The submitted area information could not be verified.',
        ])
        ->assertSessionHasNoErrors();

    $serviceAreaRequest->refresh();

    expect($serviceAreaRequest->exists)->toBeTrue()
        ->and($serviceAreaRequest->status)->toBe('rejected')
        ->and($serviceAreaRequest->reviewed_by)->toBe($admin->id)
        ->and($serviceAreaRequest->reviewed_at)->not->toBeNull()
        ->and($serviceAreaRequest->admin_notes)->toBe('The submitted area information could not be verified.')
        ->and($serviceAreaRequest->area_name)->toBe($originalArea);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'service_area_request.rejected',
        'model_id' => $serviceAreaRequest->id,
    ]);
});

test('reviewed service area requests cannot be reviewed again', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    $serviceAreaRequest = createAdminServiceAreaRequest($resident);

    $this->actingAs($admin)->post(route('admin.service-area-requests.approve', $serviceAreaRequest), [
        'admin_notes' => 'Original final approval note.',
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('admin.service-area-requests.reject', $serviceAreaRequest), [
        'admin_notes' => 'Attempted second review should not be accepted.',
    ])->assertSessionHasErrors(['review']);

    $serviceAreaRequest->refresh();

    expect($serviceAreaRequest->status)->toBe('approved')
        ->and($serviceAreaRequest->admin_notes)->toBe('Original final approval note.')
        ->and(ActivityLog::where('model_type', ServiceAreaRequest::class)->where('model_id', $serviceAreaRequest->id)->count())->toBe(1);

    $this->actingAs($admin)->get(route('admin.service-area-requests'))
        ->assertOk()
        ->assertDontSee('approveServiceAreaRequest'.$serviceAreaRequest->id)
        ->assertDontSee('rejectServiceAreaRequest'.$serviceAreaRequest->id);
});

test('admin navigation includes the service area request destination and active state', function () {
    $desktop = Blade::render('<x-admin.sidebar active="service-area-requests" />');
    $mobile = Blade::render('<x-admin.mobile-menu active="service-area-requests" />');

    expect($desktop)
        ->toContain('href="'.route('admin.service-area-requests').'"')
        ->toContain('Service Area Requests')
        ->toContain('bg-green-700')
        ->and($mobile)
        ->toContain('href="'.route('admin.service-area-requests').'"')
        ->toContain('Service Area Requests')
        ->toContain('bg-green-700');
});
