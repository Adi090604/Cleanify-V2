<?php

use App\Models\User;

test('resident settings omit removed sections and keep account controls and danger zone', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('settings'));

    $response->assertOk()
        ->assertDontSee('Privacy Settings')
        ->assertDontSee('Show my email to community members')
        ->assertDontSee('Share my location for truck tracking')
        ->assertDontSee('Profile Visibility')
        ->assertDontSee('Application Preferences')
        ->assertDontSee('Tracker Auto-Refresh Interval')
        ->assertDontSee('Interface language preference')
        ->assertDontSee('Security &amp; Data', false)
        ->assertDontSee('Active Sessions')
        ->assertDontSee('Download My Data')
        ->assertDontSee('Login History')
        ->assertDontSee('privacyForm', false)
        ->assertDontSee('preferencesForm', false)
        ->assertDontSee('revokeSessionModal', false)
        ->assertSee('Account Information')
        ->assertSee('Change Password')
        ->assertSee('Notification Preferences')
        ->assertSee('Danger Zone')
        ->assertSee('Delete My Account')
        ->assertSee('action="'.route('settings.delete-account').'"', false)
        ->assertSee('name="password"', false)
        ->assertSee('name="confirm_delete"', false);
});

test('resident account deletion still requires the current password and confirmation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('settings'))
        ->delete(route('settings.delete-account'), [
            'password' => 'wrong-password',
            'confirm_delete' => true,
        ])
        ->assertRedirect(route('settings'))
        ->assertSessionHasErrors('password');

    $this->assertDatabaseHas('users', ['id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('settings.delete-account'), [
            'password' => 'password',
            'confirm_delete' => true,
        ])
        ->assertRedirect('/')
        ->assertSessionHas('success', 'Your account has been permanently deleted.');

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertGuest();
});
