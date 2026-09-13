<?php

use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function settingsToken(User $user): string
{
    return $user->createToken('mobile-app')->plainTextToken;
}

test('mobile settings endpoints require Sanctum authentication', function () {
    $this->getJson('/api/v1/settings')->assertUnauthorized();
    $this->patchJson('/api/v1/settings/account')->assertUnauthorized();
    $this->patchJson('/api/v1/settings/password')->assertUnauthorized();
    $this->patchJson('/api/v1/settings/notifications')->assertUnauthorized();
});

test('settings returns real account preferences and only active service zones', function () {
    $active = ServiceZone::create(['name' => 'Zone 1', 'barangay' => 'Washington', 'status' => 'active']);
    ServiceZone::create(['name' => 'Zone 2', 'barangay' => 'Quezon', 'status' => 'inactive']);
    $user = User::factory()->create([
        'phone' => '09123456789',
        'service_area' => $active->display_name,
        'email_notifications' => true,
        'sms_notifications' => false,
        'push_notifications' => true,
        'notification_preferences' => ['report_updates' => false],
    ]);

    $this->withToken(settingsToken($user))->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('account.email', $user->email)
        ->assertJsonPath('account.phone', '09123456789')
        ->assertJsonPath('account.service_area', $active->display_name)
        ->assertJsonPath('service_areas.0', $active->display_name)
        ->assertJsonCount(1, 'service_areas')
        ->assertJsonPath('notifications.preferences.report_updates', false)
        ->assertJsonPath('notifications.preferences.schedule_reminders', true);
});

test('settings exposes an invalid service area as unselected', function () {
    $user = User::factory()->create(['service_area' => 'Deleted Zone']);

    $this->withToken(settingsToken($user))->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('account.service_area', null);
});

test('a user can update account information using an active service zone', function () {
    $zone = ServiceZone::create(['name' => 'Zone 1', 'barangay' => 'Washington', 'status' => 'active']);
    $user = User::factory()->create();

    $this->withToken(settingsToken($user))->patchJson('/api/v1/settings/account', [
        'email' => 'updated@example.com',
        'phone' => '09987654321',
        'service_area' => $zone->display_name,
    ])->assertOk();

    $user->refresh();
    expect($user->email)->toBe('updated@example.com')
        ->and($user->phone)->toBe('09987654321')
        ->and($user->service_area)->toBe($zone->display_name)
        ->and($user->email_verified_at)->toBeNull();
});

test('account settings reject an inactive or unknown service zone', function () {
    $zone = ServiceZone::create(['name' => 'Zone 2', 'barangay' => 'Quezon', 'status' => 'inactive']);
    $user = User::factory()->create();

    $this->withToken(settingsToken($user))->patchJson('/api/v1/settings/account', [
        'email' => $user->email,
        'phone' => null,
        'service_area' => $zone->display_name,
    ])->assertUnprocessable()->assertJsonValidationErrors('service_area');
});

test('password settings require the current password and confirmation', function () {
    $user = User::factory()->create();
    $token = settingsToken($user);

    $this->withToken($token)->patchJson('/api/v1/settings/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

    $this->withToken($token)->patchJson('/api/v1/settings/password', [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertOk();

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

test('a user can persist the existing notification preference fields', function () {
    $user = User::factory()->create();

    $this->withToken(settingsToken($user))->patchJson('/api/v1/settings/notifications', [
        'email_notifications' => false,
        'sms_notifications' => true,
        'push_notifications' => false,
        'preferences' => [
            'report_updates' => false,
            'schedule_reminders' => true,
            'community_posts' => false,
            'truck_tracking' => true,
        ],
    ])->assertOk();

    $user->refresh();
    expect($user->email_notifications)->toBeFalse()
        ->and($user->sms_notifications)->toBeTrue()
        ->and($user->push_notifications)->toBeFalse()
        ->and($user->notification_preferences)->toBe([
            'report_updates' => false,
            'schedule_reminders' => true,
            'community_posts' => false,
            'truck_tracking' => true,
        ]);
});
