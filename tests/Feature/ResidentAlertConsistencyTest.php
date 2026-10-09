<?php

use App\Models\User;

test('resident destructive dialogs use the shared admin confirmation presentation', function () {
    $resident = User::factory()->create();

    $this->actingAs($resident)
        ->get(route('settings'))
        ->assertOk()
        ->assertSee('id="deleteModal"', false)
        ->assertSee('admin-confirm-modal', false)
        ->assertSee('admin-confirm-actions', false)
        ->assertSee('admin-btn-danger', false);

    $this->get(route('profile'))
        ->assertOk()
        ->assertSee('id="deletePostModal"', false)
        ->assertSee('admin-confirm-warning', false);

    $this->get(route('notifications'))
        ->assertOk()
        ->assertSee('id="dismissNotificationModal"', false)
        ->assertSee('id="confirmNotificationDismiss"', false)
        ->assertDontSee("confirm('Are you sure you want to dismiss this notification?')", false);

    $this->get(route('community-reports'))
        ->assertOk()
        ->assertSee('id="reportUserModal"', false)
        ->assertSee('admin-confirm-modal', false);
});

test('resident logout uses the same confirmation structure as admin logout', function () {
    $resident = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);

    $residentPage = $this->actingAs($resident)->get(route('dashboard'))->assertOk();
    $adminPage = $this->actingAs($admin)->get(route('admin.settings'))->assertOk();

    $residentPage
        ->assertSee('id="logoutModal"', false)
        ->assertSee('admin-confirm-modal', false)
        ->assertSee('admin-btn-secondary', false)
        ->assertSee('admin-btn-danger', false);

    $adminPage
        ->assertSee('id="adminLogoutModal"', false)
        ->assertSee('admin-confirm-modal', false)
        ->assertSee('admin-btn-secondary', false)
        ->assertSee('admin-btn-danger', false);
});

test('resident views no longer contain native application alerts or confirmations', function () {
    $views = [
        resource_path('views/community-reports.blade.php'),
        resource_path('views/notifications.blade.php'),
        resource_path('views/profile.blade.php'),
        resource_path('views/settings.blade.php'),
        resource_path('views/layouts/app.blade.php'),
    ];

    foreach ($views as $view) {
        expect(file_get_contents($view))
            ->not->toMatch('/\balert\s*\(/')
            ->not->toMatch('/\bconfirm\s*\(/');
    }
});

test('admin confirmation styles are shared with resident pages', function () {
    $styles = file_get_contents(resource_path('css/app.css'));

    expect($styles)
        ->toContain('.admin-page .admin-confirm-modal')
        ->toContain('.user-page .admin-confirm-modal')
        ->toContain('.admin-page .admin-btn-danger')
        ->toContain('.user-page .admin-btn-danger');
});
