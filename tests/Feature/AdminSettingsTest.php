<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admin settings omit connected accounts and retain the supported settings forms', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.settings'))
        ->assertOk()
        ->assertSee('Profile Settings')
        ->assertSee('Update Password')
        ->assertSee('Notification Preferences')
        ->assertSee(route('admin.settings.profile'), false)
        ->assertSee(route('admin.settings.password'), false)
        ->assertSee(route('admin.settings.notifications'), false)
        ->assertDontSee('Connected Accounts')
        ->assertDontSee('connectedAccountsBody', false)
        ->assertDontSee('toggleAccountConnection', false)
        ->assertDontSee('Jan 12, 2025')
        ->assertDontSee('Feb 20, 2025')
        ->assertDontSee('Mar 05, 2025');
});

test('admin profile settings still update the profile', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email' => 'admin@example.com',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.settings.profile'), [
            'name' => 'Updated Administrator',
            'email' => 'updated-admin@example.com',
        ])
        ->assertRedirect(route('admin.settings'))
        ->assertSessionHas('success');

    expect($admin->fresh())
        ->name->toBe('Updated Administrator')
        ->email->toBe('updated-admin@example.com');
});

test('admin password settings still update the password', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.settings.password'), [
            'current_password' => 'password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
        ->assertRedirect(route('admin.settings'))
        ->assertSessionHas('success');

    expect(Hash::check('new-secure-password', $admin->fresh()->password))->toBeTrue();
});

test('admin notification settings still update preferences', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email_notifications' => false,
        'sms_notifications' => true,
        'push_notifications' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.settings.notifications'), [
            'email_notifications' => '1',
        ])
        ->assertRedirect(route('admin.settings'))
        ->assertSessionHas('success');

    expect($admin->fresh())
        ->email_notifications->toBeTrue()
        ->sms_notifications->toBeFalse()
        ->push_notifications->toBeFalse();
});
