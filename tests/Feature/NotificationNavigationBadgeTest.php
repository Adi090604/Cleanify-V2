<?php

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

function navigationNotificationFor(User $user, bool $read = false): DatabaseNotification
{
    return DatabaseNotification::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => ['title' => 'Navigation badge test', 'category' => 'system'],
        'read_at' => $read ? now() : null,
    ]);
}

test('normal user navigation shows the real unread notification count and hides zero', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/notifications')
        ->assertOk()
        ->assertDontSee('aria-label="1 unread notifications"', false);

    navigationNotificationFor($user);
    navigationNotificationFor($user);
    navigationNotificationFor($user, read: true);

    $this->actingAs($user)->get('/notifications')
        ->assertOk()
        ->assertSee('aria-label="2 unread notifications"', false);
});

test('normal user navigation caps the unread notification badge at 99 plus', function () {
    $user = User::factory()->create();

    foreach (range(1, 100) as $_) {
        navigationNotificationFor($user);
    }

    $this->actingAs($user)->get('/notifications')
        ->assertOk()
        ->assertSee('aria-label="100 unread notifications"', false)
        ->assertSee('99+', false);
});

test('existing web notification actions refresh the navigation count', function () {
    $user = User::factory()->create();
    $first = navigationNotificationFor($user);
    $second = navigationNotificationFor($user);
    navigationNotificationFor($user);

    $this->actingAs($user)->post(route('notifications.mark-read', $first->id))->assertRedirect();
    $this->get('/notifications')->assertSee('aria-label="2 unread notifications"', false);

    $this->delete(route('notifications.destroy', $second->id))->assertRedirect();
    $this->get('/notifications')->assertSee('aria-label="1 unread notifications"', false);

    $this->post(route('notifications.mark-all-read'))->assertRedirect();
    $this->get('/notifications')->assertDontSee('unread notifications');
});

test('admin navigation does not invent a notification badge', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    navigationNotificationFor($admin);

    $this->actingAs($admin)->get('/admin/dashboard')
        ->assertOk()
        ->assertDontSee('unread notifications');
});
