<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('login password is masked by default and has an accessible visibility toggle', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    expect($html)
        ->toContain('type="password"', 'id="password"', 'autocomplete="current-password"')
        ->toContain('data-password-toggle', 'aria-controls="password"', 'aria-label="Show password"')
        ->toContain('type="button"', 'data-password-toggle-icon', 'fa-eye');
});

test('registration password fields have independent toggles and retain required phone registration', function () {
    $html = $this->get(route('register'))->assertOk()->getContent();

    expect($html)
        ->toContain('id="password"', 'id="password_confirmation"')
        ->toContain('aria-controls="password"', 'aria-controls="password_confirmation"')
        ->toContain('autocomplete="new-password"')
        ->and(substr_count($html, 'data-password-toggle'))->toBe(4)
        ->and(substr_count($html, 'type="password"'))->toBe(2)
        ->and($html)->toContain('name="phone"', 'type="tel"');
});

test('resident settings password fields have independent accessible toggles', function () {
    $user = User::factory()->create();
    $html = $this->actingAs($user)->get(route('settings'))->assertOk()->getContent();

    foreach (['settingsCurrentPassword', 'settingsNewPassword', 'settingsPasswordConfirmation'] as $inputId) {
        expect($html)->toContain("id=\"{$inputId}\"", "aria-controls=\"{$inputId}\"");
    }

    expect(substr_count($html, 'data-password-toggle'))->toBe(6)
        ->and(substr_count($html, 'type="password"'))->toBeGreaterThanOrEqual(4)
        ->and($html)->toContain('autocomplete="current-password"', 'autocomplete="new-password"');
});

test('shared password toggle script changes only its controlled input without submitting forms', function () {
    $script = file_get_contents(resource_path('js/app.js'));

    expect($script)
        ->toContain("event.target.closest('[data-password-toggle]')")
        ->toContain("document.getElementById(toggle.getAttribute('aria-controls'))")
        ->toContain("input.type = shouldShow ? 'text' : 'password'")
        ->toContain("shouldShow ? 'Hide password' : 'Show password'")
        ->toContain("icon?.classList.toggle('fa-eye-slash', shouldShow)")
        ->toContain('event.preventDefault()');
});

test('resident password update behavior remains unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('settings.password'), [
        'current_password' => 'password',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])->assertRedirect(route('settings'))
        ->assertSessionHas('success', 'Password updated successfully');

    expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();
});
