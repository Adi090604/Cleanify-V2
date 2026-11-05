<?php

use App\Models\User;

test('resident layout exposes shared SweetAlert flash states without changing admin alerts', function () {
    $resident = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($resident)
        ->withSession([
            'success' => 'Saved successfully.',
            'error' => 'A safe error message.',
            'warning' => 'Please review this action.',
            'info' => 'Your request is being reviewed.',
        ])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="residentAlertData"', false)
        ->assertSee('data-success="Saved successfully."', false)
        ->assertSee('data-error="A safe error message."', false)
        ->assertSee('data-warning="Please review this action."', false)
        ->assertSee('data-info="Your request is being reviewed."', false);

    $this->actingAs($admin)
        ->get(route('admin.settings'))
        ->assertOk()
        ->assertDontSee('residentAlertData');
});

test('resident validation errors retain field feedback and expose a concise alert summary', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('settings'))
        ->followingRedirects()
        ->patch(route('settings.notifications'), [])
        ->assertOk()
        ->assertSee('data-validation="Please check the highlighted fields."', false)
        ->assertSee('Please review the notification preference errors and try again.');
});

test('major resident form submissions retain their specific success flash messages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('settings.notifications'), [
            'email_notifications' => true,
            'sms_notifications' => true,
            'preferences' => [
                'report_updates' => true,
                'schedule_reminders' => true,
                'system' => true,
            ],
        ])
        ->assertRedirect(route('settings'))
        ->assertSessionHas('success', 'Notification preferences updated successfully');

    $this->post(route('settings.service-area-requests.store'), [
        'area_name' => 'Sitio SweetAlert',
        'barangay' => 'Barangay Washington',
        'address' => '123 Test Street',
        'latitude' => 9.78701234,
        'longitude' => 125.49281234,
        'details' => 'Coverage request feedback test.',
    ])->assertRedirect(route('settings'))
        ->assertSessionHas('success', 'Service area request submitted successfully.');

    $this->post(route('reports.store'), [
        'barangay' => 'Barangay Washington',
        'location' => 'Near the covered court',
        'latitude' => 9.78701234,
        'longitude' => 125.49281234,
        'description' => 'Resident report feedback test.',
    ])->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Your update has been shared! Our team will take a look soon.');
});

test('resident settings ajax submissions return json instead of consuming redirect flashes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('settings.notifications'), [
            'email_notifications' => true,
            'sms_notifications' => false,
            'preferences' => [
                'report_updates' => true,
                'schedule_reminders' => false,
                'system' => true,
            ],
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Notification preferences updated successfully',
        ]);

    $this->actingAs($user)
        ->patchJson(route('settings.notifications'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email_notifications',
            'sms_notifications',
            'preferences',
        ]);
});

test('resident profile ajax submissions return success and field validation json', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('profile.update'), [
            'name' => 'Updated Resident',
            'email' => 'updated-resident@example.test',
            'phone' => '09171234567',
            'address' => 'Updated address',
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Profile updated successfully',
        ]);

    $this->actingAs($user)
        ->patchJson(route('profile.update'), [
            'name' => '',
            'email' => 'not-an-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email']);
});

test('resident alert messages are rendered as escaped text and not executable html', function () {
    $user = User::factory()->create();
    $unsafe = '<script>window.leakedInternalData = true</script>';

    $this->actingAs($user)
        ->withSession(['error' => $unsafe])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(e($unsafe), false)
        ->assertDontSee($unsafe, false);

    $script = file_get_contents(resource_path('js/app.js'));

    expect($script)
        ->toContain("import Swal from 'sweetalert2'")
        ->toContain('window.showResidentAlert = function')
        ->toContain('return Swal.fire({')
        ->toContain("document.addEventListener('DOMContentLoaded'")
        ->toContain('window.showResidentAlert(type, message)')
        ->toContain("error: 'Something went wrong'")
        ->toContain("text: String(message || '')")
        ->toContain('!Number.isFinite(status) || status < 400 || status >= 500')
        ->not->toContain('html: message');
});

test('meaningful ajax actions use resident alerts while high frequency likes keep lightweight feedback', function () {
    $profile = file_get_contents(resource_path('views/profile.blade.php'));
    $community = file_get_contents(resource_path('views/community-reports.blade.php'));
    $settings = file_get_contents(resource_path('views/settings.blade.php'));

    expect($profile)
        ->toContain("showResidentAlert('success', data.message || 'Profile updated successfully')")
        ->toContain("? 'Please check the highlighted fields.'")
        ->toContain('errorElement.classList.remove(\'hidden\')')
        ->toContain("showResidentAlert('success', data.message || 'Post deleted successfully')")
        ->toContain("showToast('error', 'Failed to like post. Please try again.')")
        ->and($community)
        ->toContain("showResidentAlert('success', data.message || 'User reported successfully. Our team will review this report.')")
        ->and($settings)
        ->toContain("'Accept': 'application/json'")
        ->toContain("'X-Requested-With': 'XMLHttpRequest'")
        ->toContain('if (!response.ok)')
        ->toContain("showResidentAlert('success', data.message || successMessage)")
        ->toContain("? 'Please check the highlighted fields.'");
});
