<?php

use App\Models\ServiceAreaRequest;
use App\Models\User;

function serviceAreaRequestForBadge(User $resident, string $areaName, string $status = 'pending'): ServiceAreaRequest
{
    $request = $resident->serviceAreaRequests()->create([
        'area_name' => $areaName,
        'normalized_area_name' => ServiceAreaRequest::normalizeAreaName($areaName),
        'barangay' => 'Badge Barangay',
    ]);
    $request->forceFill(['status' => $status])->save();

    return $request;
}

test('admin navigation hides the service area request badge when no requests are pending', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    serviceAreaRequestForBadge($resident, 'Approved Badge Area', 'approved');
    serviceAreaRequestForBadge($resident, 'Rejected Badge Area', 'rejected');

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('pending service area requests');
});

test('desktop and mobile admin navigation show only the real pending service area request count', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    serviceAreaRequestForBadge($resident, 'First Pending Badge Area');
    serviceAreaRequestForBadge($resident, 'Second Pending Badge Area');
    serviceAreaRequestForBadge($resident, 'Approved Badge Area', 'approved');
    serviceAreaRequestForBadge($resident, 'Rejected Badge Area', 'rejected');

    $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $html = $response->getContent();

    expect(substr_count($html, 'aria-label="2 pending service area requests"'))->toBe(2)
        ->and(substr_count($html, 'bg-red-600'))->toBeGreaterThanOrEqual(2);
});

test('pending badge naturally decreases after approval and rejection', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    $toApprove = serviceAreaRequestForBadge($resident, 'Approve Badge Area');
    $toReject = serviceAreaRequestForBadge($resident, 'Reject Badge Area');

    $this->actingAs($admin)->post(route('admin.service-area-requests.approve', $toApprove))
        ->assertSessionHasNoErrors();

    $afterApproval = $this->get(route('admin.dashboard'))->assertOk()->getContent();
    expect(substr_count($afterApproval, 'aria-label="1 pending service area requests"'))->toBe(2);

    $this->post(route('admin.service-area-requests.reject', $toReject), [
        'admin_notes' => 'Rejected for badge count verification.',
    ])->assertSessionHasNoErrors();

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('pending service area requests');
});

test('service area request submission does not create an admin notification', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();

    $this->actingAs($resident)->post(route('settings.service-area-requests.store'), [
        'area_name' => 'Submission Without Admin Notification',
        'barangay' => 'Badge Barangay',
    ])->assertSessionHasNoErrors();

    expect($admin->fresh()->notifications()->count())->toBe(0)
        ->and(ServiceAreaRequest::where('status', 'pending')->count())->toBe(1);
});
