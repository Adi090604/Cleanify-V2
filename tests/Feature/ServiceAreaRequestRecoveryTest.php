<?php

use App\Models\Schedule;
use App\Models\ServiceAreaRequest;
use App\Models\ServiceZone;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

function createRecoveryRequest(User $resident, User $reviewer, string $status = 'approved', ?ServiceZone $zone = null): ServiceAreaRequest
{
    $request = $resident->serviceAreaRequests()->create([
        'area_name' => 'Immutable Requested Purok', 'normalized_area_name' => 'immutable requested purok',
        'barangay' => 'Original Barangay', 'address' => 'Original private landmark',
        'latitude' => '9.76543210', 'longitude' => '125.45678901', 'details' => 'Original resident evidence.',
    ]);
    $request->forceFill([
        'status' => $status, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()->subDay()->startOfSecond(),
        'admin_notes' => 'Original immutable review notes.', 'service_zone_id' => $zone?->id,
    ])->save();

    return $request;
}

function recoveryZoneData(string $name, array $overrides = []): array
{
    return array_merge([
        'name' => $name, 'barangay' => 'Barangay Official', 'status' => 'active',
        'latitude' => '9.70000000', 'longitude' => '125.40000000',
    ], $overrides);
}

test('deleted linked zone lets the same approved request create a service zone again', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create(['service_area' => 'Original Resident Area']);
    $request = $resident->serviceAreaRequests()->create([
        'area_name' => 'Immutable Requested Purok', 'normalized_area_name' => 'immutable requested purok',
        'barangay' => 'Original Barangay', 'address' => 'Original private landmark',
        'latitude' => '9.76543210', 'longitude' => '125.45678901', 'details' => 'Original resident evidence.',
    ]);

    $this->actingAs($admin)->post(route('admin.service-area-requests.approve', $request), [
        'admin_notes' => 'Original immutable review notes.',
    ])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('admin.service-area-requests.create-zone', $request), array_merge(
        recoveryZoneData('Zone Recovery Old'), ['assign_to_requester' => '1']
    ))->assertSessionHasNoErrors();

    $request->refresh();
    $oldZone = $request->serviceZone;
    $original = $request->only([
        'user_id', 'area_name', 'normalized_area_name', 'barangay', 'address', 'latitude', 'longitude', 'details', 'admin_notes',
    ]);
    $reviewedBy = $request->reviewed_by;
    $reviewedAt = $request->reviewed_at->toDateTimeString();
    $counts = [ServiceAreaRequest::count(), Schedule::count(), Truck::count()];

    $this->actingAs($admin)->delete(route('admin.service-zones.destroy', $oldZone))
        ->assertRedirect(route('admin.service-zones'))->assertSessionHas('success');

    expect($request->fresh()->status)->toBe('approved')->and($request->fresh()->service_zone_id)->toBeNull()
        ->and($resident->fresh()->service_area)->toBeNull();

    $this->actingAs($admin)->get(route('admin.service-area-requests'))->assertOk()
        ->assertSee('Linked Service Zone:</span> None', false)->assertSee('does not need to be approved again')
        ->assertSee('Create Service Zone Again')->assertSee('setupServiceAreaRequest'.$request->id)
        ->assertSee('action="'.route('admin.service-area-requests.create-zone', $request).'"', false)
        ->assertDontSee('Link Existing Service Zone')->assertDontSee('Change Linked Service Zone')
        ->assertDontSee('approveServiceAreaRequest'.$request->id);

    $this->actingAs($admin)->post(route('admin.service-area-requests.create-zone', $request), array_merge(
        recoveryZoneData('Zone Recovery Created', [
            'barangay' => 'Barangay Replacement', 'latitude' => '9.71000000', 'longitude' => '125.41000000',
        ]), ['assign_to_requester' => '1']
    ))->assertSessionHasNoErrors();

    $replacement = ServiceZone::where('name', 'Zone Recovery Created')->sole();
    $request->refresh();
    expect($request->status)->toBe('approved')->and($request->service_zone_id)->toBe($replacement->id)
        ->and($request->reviewed_by)->toBe($reviewedBy)->and($request->reviewed_at->toDateTimeString())->toBe($reviewedAt)
        ->and($request->only(array_keys($original)))->toBe($original)->and($resident->fresh()->service_area)->toBe($replacement->display_name)
        ->and([ServiceAreaRequest::count(), Schedule::count(), Truck::count()])->toBe($counts)
        ->and(ServiceZone::whereKey($oldZone->id)->doesntExist())->toBeTrue();
});

test('legacy dangling zone id is repaired before creating a replacement zone', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $request = createRecoveryRequest(User::factory()->create(), $admin);

    DB::statement('PRAGMA defer_foreign_keys = ON');
    DB::table('service_area_requests')->where('id', $request->id)->update(['service_zone_id' => 999999]);
    expect($request->fresh()->service_zone_id)->toBe(999999)->and($request->fresh()->serviceZone)->toBeNull();

    $this->actingAs($admin)->post(route('admin.service-area-requests.create-zone', $request), recoveryZoneData('Legacy Recovery Created'))
        ->assertSessionHasNoErrors();
    DB::statement('PRAGMA defer_foreign_keys = OFF');

    expect($request->fresh()->serviceZone->name)->toBe('Legacy Recovery Created')->and($request->fresh()->status)->toBe('approved');
});

test('creation validation reopens the correct modal with actionable errors', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $request = createRecoveryRequest(User::factory()->create(), $admin);

    $this->actingAs($admin)->from(route('admin.service-area-requests'))
        ->post(route('admin.service-area-requests.create-zone', $request), [
            'recovery_action' => 'create', 'service_area_request_id' => $request->id,
            'name' => '', 'barangay' => '', 'status' => 'invalid', 'latitude' => '91', 'longitude' => '181',
        ])->assertRedirect(route('admin.service-area-requests'))->assertSessionHasErrors();

    $this->get(route('admin.service-area-requests'))->assertOk()->assertSee('The name field is required.')
        ->assertSee("openModal('setupServiceAreaRequest{$request->id}')", false);
});

test('pending and rejected requests expose no zone creation action', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    createRecoveryRequest($resident, $admin, 'pending');
    createRecoveryRequest($resident, $admin, 'rejected');

    $this->actingAs($admin)->get(route('admin.service-area-requests'))->assertOk()
        ->assertDontSee('Create Service Zone')->assertDontSee('Link Existing Service Zone');
});

test('schedule and truck zone deletion guards remain enforced', function (string $referenceType) {
    $admin = User::factory()->create(['is_admin' => true]);
    $zone = ServiceZone::create(recoveryZoneData('Zone Guard '.$referenceType));
    $request = createRecoveryRequest(User::factory()->create(), $admin, 'approved', $zone);

    if ($referenceType === 'schedule') {
        Schedule::create([
            'area' => $zone->display_name, 'schedule_type' => 'recurring', 'days' => 'Monday',
            'time_start' => '08:00', 'time_end' => '09:00', 'truck' => 'TRK-GUARD', 'status' => 'active',
        ]);
    } else {
        Truck::create(['code' => 'TRK-GUARD', 'driver' => 'Guard Driver', 'route' => $zone->display_name, 'status' => 'active']);
    }

    $this->actingAs($admin)->delete(route('admin.service-zones.destroy', $zone))
        ->assertRedirect(route('admin.service-zones'))->assertSessionHas('error');

    expect($zone->fresh())->not->toBeNull()->and($request->fresh()->service_zone_id)->toBe($zone->id);
})->with(['schedule', 'truck']);
