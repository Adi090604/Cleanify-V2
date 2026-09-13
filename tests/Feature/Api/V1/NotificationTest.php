<?php

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

function notificationFor(User $user, array $data, bool $read = false): DatabaseNotification
{
    return DatabaseNotification::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => $data,
        'read_at' => $read ? now() : null,
    ]);
}

test('mobile notification endpoints require Sanctum authentication', function () {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
    $this->postJson('/api/v1/notifications/mark-all-read')->assertUnauthorized();
    $this->postJson('/api/v1/notifications/missing/read')->assertUnauthorized();
    $this->deleteJson('/api/v1/notifications/missing')->assertUnauthorized();
});

test('a user receives only their real notification data and unread count', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $notification = notificationFor($user, ['title' => 'Schedule changed', 'message' => 'Monday at 8:00 AM', 'category' => 'schedule']);
    notificationFor($other, ['title' => 'Private', 'message' => 'Not yours', 'category' => 'system']);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications')
        ->assertOk()->assertJsonCount(1, 'notifications')
        ->assertJsonPath('notifications.0.id', $notification->id)
        ->assertJsonPath('notifications.0.title', 'Schedule changed')
        ->assertJsonPath('notifications.0.is_read', false)
        ->assertJsonPath('notifications.0.action_route', '/tabs/schedule')
        ->assertJsonPath('unread_count', 1)
        ->assertJsonMissingPath('notifications.0.notifiable_id')
        ->assertJsonMissingPath('notifications.0.data');
});

test('notification filters and muted category behavior match the web inbox', function () {
    $user = User::factory()->create(['notification_preferences' => ['tracker' => false]]);
    notificationFor($user, ['title' => 'Unread report', 'category' => 'reports']);
    notificationFor($user, ['title' => 'Read report', 'category' => 'reports'], true);
    notificationFor($user, ['title' => 'Muted tracker', 'category' => 'tracker']);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications?filter=unread&category=reports')
        ->assertOk()->assertJsonCount(1, 'notifications')->assertJsonPath('notifications.0.title', 'Unread report');
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications')
        ->assertOk()->assertJsonCount(2, 'notifications');
});

test('users can read dismiss and mark all of only their own notifications', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $first = notificationFor($user, ['title' => 'First', 'category' => 'system']);
    $second = notificationFor($user, ['title' => 'Second', 'category' => 'reports']);
    $foreign = notificationFor($other, ['title' => 'Foreign', 'category' => 'system']);

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/notifications/{$first->id}/read")
        ->assertOk()->assertJsonPath('notification.is_read', true);
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/notifications/{$foreign->id}/read")->assertNotFound();
    $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/notifications/{$first->id}")->assertOk();
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/notifications/mark-all-read')->assertOk();

    expect(DatabaseNotification::find($first->id))->toBeNull()
        ->and(DatabaseNotification::find($second->id)->read_at)->not->toBeNull()
        ->and(DatabaseNotification::find($foreign->id)->read_at)->toBeNull();
});
