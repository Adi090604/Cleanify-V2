<?php

use App\Models\ServiceAreaRequest;
use App\Models\ServiceZone;
use App\Models\SmsNotification;
use App\Models\User;
use App\Notifications\ServiceAreaRequestStatusNotification;
use App\Services\ServiceZoneCreator;
use Illuminate\Support\Facades\Notification;

function createNotifiableAreaRequest(User $resident, string $status = 'pending'): ServiceAreaRequest
{
    $serviceAreaRequest = $resident->serviceAreaRequests()->create([
        'area_name' => 'Purok Notification Riverside',
        'normalized_area_name' => 'purok notification riverside',
        'barangay' => 'Barangay Private Test',
        'address' => 'House 17, Private Lane',
        'latitude' => '9.76543210',
        'longitude' => '125.45678901',
        'details' => 'Private resident-submitted context.',
    ]);

    $serviceAreaRequest->forceFill(['status' => $status])->save();

    return $serviceAreaRequest;
}

function notificationZoneData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Zone 78',
        'barangay' => 'Barangay Official',
        'status' => 'active',
        'latitude' => '9.70000000',
        'longitude' => '125.40000000',
    ], $overrides);
}

test('approval and rejection each send one event-specific resident notification', function (string $action, string $eventType, string $title) {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    $serviceAreaRequest = createNotifiableAreaRequest($resident);

    $this->actingAs($admin)->post(
        route("admin.service-area-requests.{$action}", $serviceAreaRequest),
        ['admin_notes' => 'Internal review notes must never be disclosed.']
    )->assertSessionHasNoErrors();

    Notification::assertSentTo(
        $resident,
        ServiceAreaRequestStatusNotification::class,
        fn (ServiceAreaRequestStatusNotification $notification) => $notification->eventType === $eventType
            && $notification->toArray($resident)['title'] === $title
    );
    expect(Notification::sent($resident, ServiceAreaRequestStatusNotification::class))->toHaveCount(1);
})->with([
    'approval' => ['approve', ServiceAreaRequestStatusNotification::EVENT_APPROVED, 'Service Area Request Approved'],
    'rejection' => ['reject', ServiceAreaRequestStatusNotification::EVENT_REJECTED, 'Service Area Request Not Approved'],
]);

test('creating a zone sends one assignment-aware notification without creating sms work', function (bool $assign, string $expectedText) {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    $serviceAreaRequest = createNotifiableAreaRequest($resident, 'approved');
    $smsCount = SmsNotification::count();

    $this->actingAs($admin)->post(
        route('admin.service-area-requests.create-zone', $serviceAreaRequest),
        array_merge(notificationZoneData(['name' => 'New Notification Zone']), $assign ? ['assign_to_requester' => '1'] : [])
    )->assertSessionHasNoErrors();

    $zone = ServiceZone::where('name', 'New Notification Zone')->sole();
    Notification::assertSentTo($resident, ServiceAreaRequestStatusNotification::class, function ($notification) use ($resident, $zone, $assign, $expectedText) {
        return $notification->eventType === ServiceAreaRequestStatusNotification::EVENT_ZONE_CREATED
            && $notification->serviceZone->is($zone)
            && $notification->requesterAssigned === $assign
            && str_contains($notification->toArray($resident)['message'], $expectedText);
    });
    expect(Notification::sent($resident, ServiceAreaRequestStatusNotification::class))->toHaveCount(1)
        ->and(SmsNotification::count())->toBe($smsCount);
})->with([
    'assigned' => [true, 'is now your Cleanify service area'],
    'not assigned' => [false, 'has been created for your approved request'],
]);

test('notification payload and email omit private review and resident location data', function () {
    $reviewer = User::factory()->create(['is_admin' => true, 'name' => 'Secret Reviewer']);
    $resident = User::factory()->create(['name' => 'Maria Resident']);
    $serviceAreaRequest = createNotifiableAreaRequest($resident, 'rejected');
    $serviceAreaRequest->forceFill([
        'admin_notes' => 'Private rejection rationale.',
        'reviewed_by' => $reviewer->id,
        'reviewed_at' => now(),
    ])->save();
    $notification = new ServiceAreaRequestStatusNotification(
        $serviceAreaRequest,
        ServiceAreaRequestStatusNotification::EVENT_REJECTED
    );

    $payload = $notification->toArray($resident);
    $serializedPayload = json_encode($payload);
    $mail = $notification->toMail($resident);
    $serializedMail = json_encode([$mail->subject, $mail->greeting, $mail->introLines, $mail->actionUrl]);

    expect($payload)->toMatchArray([
        'notification_type' => 'service_area_request',
        'event_type' => 'rejected',
        'service_area_request_id' => $serviceAreaRequest->id,
        'service_zone_id' => null,
        'category' => 'system',
        'url' => '/settings',
    ])->not->toHaveKeys(['admin_notes', 'reviewed_by', 'reviewer', 'area_name', 'address', 'latitude', 'longitude', 'details'])
        ->and($serializedPayload)->not->toContain('Private rejection rationale.', 'Secret Reviewer', 'Purok Notification Riverside', 'House 17, Private Lane', '9.76543210', '125.45678901')
        ->and($serializedMail)->not->toContain('Private rejection rationale.', 'Secret Reviewer', 'Purok Notification Riverside', 'House 17, Private Lane', '9.76543210', '125.45678901')
        ->and($mail->actionUrl)->toBe(route('settings'));
});

test('messages distinguish review and setup from collection operations', function () {
    $resident = User::factory()->create();
    $serviceAreaRequest = createNotifiableAreaRequest($resident);
    $zone = ServiceZone::create(notificationZoneData());

    $approval = (new ServiceAreaRequestStatusNotification(
        $serviceAreaRequest,
        ServiceAreaRequestStatusNotification::EVENT_APPROVED
    ))->toArray($resident)['message'];
    $created = (new ServiceAreaRequestStatusNotification(
        $serviceAreaRequest,
        ServiceAreaRequestStatusNotification::EVENT_ZONE_CREATED,
        $zone,
        true
    ))->toArray($resident)['message'];

    expect($approval)->toContain('schedule has not been created')
        ->not->toContain('pickup', 'truck', 'has been scheduled')
        ->and($created)->toContain('scheduling is managed separately')
        ->not->toContain('pickup', 'truck', 'has been scheduled');
});

test('duplicate review and conversion attempts do not send duplicate notifications', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    $serviceAreaRequest = createNotifiableAreaRequest($resident);

    $this->actingAs($admin)->post(route('admin.service-area-requests.approve', $serviceAreaRequest));
    $this->actingAs($admin)->post(route('admin.service-area-requests.approve', $serviceAreaRequest))
        ->assertSessionHasErrors(['review']);
    expect(Notification::sent($resident, ServiceAreaRequestStatusNotification::class))->toHaveCount(1);

    $this->actingAs($admin)->post(
        route('admin.service-area-requests.create-zone', $serviceAreaRequest),
        notificationZoneData(['name' => 'First Created Notification Zone'])
    );
    $this->actingAs($admin)->post(
        route('admin.service-area-requests.create-zone', $serviceAreaRequest),
        notificationZoneData(['name' => 'Duplicate Attempt Notification Zone'])
    )->assertSessionHasErrors(['conversion']);

    expect(Notification::sent($resident, ServiceAreaRequestStatusNotification::class))->toHaveCount(2);
});

test('failed conversion transaction sends no notification and rolls back state', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    $serviceAreaRequest = createNotifiableAreaRequest($resident, 'approved');

    $this->mock(ServiceZoneCreator::class, function ($mock): void {
        $mock->shouldReceive('create')->once()->andThrow(new \RuntimeException('Simulated conversion failure.'));
    });

    try {
        $this->actingAs($admin)->post(
            route('admin.service-area-requests.create-zone', $serviceAreaRequest),
            notificationZoneData(['name' => 'Rolled Back Notification Zone'])
        );
    } catch (\RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Simulated conversion failure.');
    }

    Notification::assertNothingSent();
    expect($serviceAreaRequest->fresh()->service_zone_id)->toBeNull()
        ->and(ServiceZone::where('name', 'Rolled Back Notification Zone')->doesntExist())->toBeTrue();
});

test('database delivery remains enabled when email or system email preferences are disabled', function (array $residentAttributes) {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create($residentAttributes);
    $serviceAreaRequest = createNotifiableAreaRequest($resident);

    $this->actingAs($admin)->post(route('admin.service-area-requests.approve', $serviceAreaRequest));

    $stored = $resident->fresh()->notifications()->sole();
    expect($stored->read_at)->toBeNull()
        ->and($stored->data)->toMatchArray([
            'event_type' => 'approved',
            'category' => 'system',
            'title' => 'Service Area Request Approved',
            'url' => '/settings',
        ]);
})->with([
    'global email disabled' => [['email_notifications' => false]],
    'system email disabled' => [['email_notifications' => true, 'notification_preferences' => ['system' => false]]],
]);

test('email channel follows global and system category preferences', function (array $residentAttributes, bool $expectsMail) {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create($residentAttributes);
    $serviceAreaRequest = createNotifiableAreaRequest($resident);

    $this->actingAs($admin)->post(route('admin.service-area-requests.approve', $serviceAreaRequest));

    Notification::assertSentTo($resident, ServiceAreaRequestStatusNotification::class, function ($notification, array $channels) use ($expectsMail) {
        return in_array('database', $channels, true)
            && in_array('mail', $channels, true) === $expectsMail;
    });
})->with([
    'enabled' => [['email_notifications' => true, 'notification_preferences' => ['system' => true]], true],
    'globally disabled' => [['email_notifications' => false, 'notification_preferences' => ['system' => true]], false],
    'category disabled' => [['email_notifications' => true, 'notification_preferences' => ['system' => false]], false],
]);

test('service area notification appears in the web inbox badge and safe mobile api serialization', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create(['email_notifications' => false]);
    $serviceAreaRequest = createNotifiableAreaRequest($resident);

    $this->actingAs($admin)->post(route('admin.service-area-requests.approve', $serviceAreaRequest));

    $this->actingAs($resident)->get(route('notifications'))
        ->assertOk()
        ->assertSee('Service Area Request Approved')
        ->assertSee('1 unread')
        ->assertSee('href="/settings"', false);

    $this->actingAs($resident, 'sanctum')->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('notifications.0.title', 'Service Area Request Approved')
        ->assertJsonPath('notifications.0.category', 'system')
        ->assertJsonPath('notifications.0.action_route', null)
        ->assertJsonPath('unread_count', 1)
        ->assertJsonMissingPath('notifications.0.admin_notes')
        ->assertJsonMissingPath('notifications.0.latitude')
        ->assertJsonMissingPath('notifications.0.longitude');
});
