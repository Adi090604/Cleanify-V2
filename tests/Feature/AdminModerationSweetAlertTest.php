<?php

use App\Models\ModerationAction;
use App\Models\User;
use App\Models\UserReport;
use App\Services\ModerationService;
use Illuminate\Support\Facades\Notification;

function sweetAlertModerationReport(?User $reportedUser = null): UserReport
{
    return UserReport::query()->create([
        'reporter_id' => User::factory()->create()->id,
        'reported_user_id' => ($reportedUser ?? User::factory()->create())->id,
        'reason' => 'harassment',
        'description' => 'Evidence for the administrator moderation feedback test.',
        'status' => 'pending',
    ]);
}

test('admin moderation decisions return their specific safe success messages', function (string $decision, array $extra, string $message) {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $report = sweetAlertModerationReport();

    $this->actingAs($admin)
        ->from(route('admin.user-reports'))
        ->put(route('admin.user-reports.update', $report), array_merge([
            'decision' => $decision,
            'admin_notes' => 'Private administrator context must not enter the success message.',
        ], $extra))
        ->assertRedirect(route('admin.user-reports'))
        ->assertSessionHas('success', $message)
        ->assertSessionMissing('error');
})->with([
    'warning' => ['warning', [], 'Warning issued successfully.'],
    'community restriction' => ['community_restriction', ['duration' => '3'], 'Community restriction applied successfully.'],
    'report submission restriction' => ['report_submission_restriction', ['duration' => '7'], 'Report submission restriction applied successfully.'],
    'suspension' => ['suspension', [], 'Account suspended successfully.'],
]);

test('dismissing a user report returns the dedicated success message', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $report = sweetAlertModerationReport();

    $this->actingAs($admin)
        ->put(route('admin.user-reports.update', $report), [
            'decision' => 'dismiss',
            'admin_notes' => 'Private dismissal rationale.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'User report dismissed successfully.');

    expect($report->fresh()->status)->toBe('dismissed');
});

test('lifting restrictions and suspensions returns action-specific success messages', function (string $actionType, string $message) {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create([
        'banned_at' => $actionType === ModerationAction::SUSPENSION ? now() : null,
    ]);
    $action = ModerationAction::query()->create([
        'user_id' => $resident->id,
        'action_type' => $actionType,
        'applied_by' => $admin->id,
        'starts_at' => now(),
        'expires_at' => $actionType === ModerationAction::SUSPENSION ? null : now()->addDays(3),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.moderation-actions.lift', $action))
        ->assertRedirect()
        ->assertSessionHas('success', $message);
})->with([
    'temporary restriction' => [ModerationAction::COMMUNITY_RESTRICTION, 'Restriction lifted successfully.'],
    'suspension' => [ModerationAction::SUSPENSION, 'Account unsuspended successfully.'],
]);

test('invalid moderation input returns safe field validation and a concise alert summary', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $report = sweetAlertModerationReport();

    $this->actingAs($admin)
        ->from(route('admin.user-reports'))
        ->put(route('admin.user-reports.update', $report), [
            'id' => $report->id,
            'decision' => 'community_restriction',
            'duration' => 'custom',
            'custom_until' => now()->subDay()->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect(route('admin.user-reports'))
        ->assertSessionHasErrors('custom_until');

    $this->followingRedirects()
        ->get(route('admin.user-reports'))
        ->assertOk()
        ->assertSee('data-validation="Please check the highlighted fields."', false)
        ->assertSee('The custom until field must be a date after now.');

    expect($report->fresh()->status)->toBe('pending');
});

test('unexpected moderation failures return only the generic safe error message', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $report = sweetAlertModerationReport();
    $moderation = Mockery::mock(ModerationService::class);
    $moderation->shouldReceive('apply')
        ->once()
        ->andThrow(new RuntimeException('SQL failure at C:\\private\\internal.php with secret notes'));
    $this->app->instance(ModerationService::class, $moderation);

    $response = $this->actingAs($admin)
        ->put(route('admin.user-reports.update', $report), [
            'decision' => 'warning',
            'admin_notes' => 'Private administrator investigation notes.',
        ]);

    $response->assertRedirect()
        ->assertSessionHas('error', 'Something went wrong while processing the moderation action. Please try again.');

    expect(session('error'))
        ->not->toContain('SQL failure')
        ->not->toContain('internal.php')
        ->not->toContain('Private administrator investigation notes.');
});

test('resident cannot submit moderation decisions or lift actions', function () {
    $resident = User::factory()->create();
    $reported = User::factory()->create();
    $report = sweetAlertModerationReport($reported);
    $action = ModerationAction::query()->create([
        'user_id' => $reported->id,
        'action_type' => ModerationAction::COMMUNITY_RESTRICTION,
        'applied_by' => User::factory()->create(['is_admin' => true])->id,
        'starts_at' => now(),
        'expires_at' => now()->addDays(3),
    ]);

    $this->actingAs($resident)
        ->put(route('admin.user-reports.update', $report), ['decision' => 'warning'])
        ->assertForbidden();
    $this->actingAs($resident)
        ->post(route('admin.moderation-actions.lift', $action))
        ->assertForbidden();
});

test('admin reported users page integrates SweetAlert without native confirmation or duplicate banners', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    sweetAlertModerationReport();

    $this->actingAs($admin)
        ->withSession(['success' => 'Warning issued successfully.'])
        ->get(route('admin.user-reports'))
        ->assertOk()
        ->assertSee('id="adminAlertData"', false)
        ->assertSee('data-success="Warning issued successfully."', false)
        ->assertSee('data-admin-moderation-confirmation', false)
        ->assertSee('Issue warning?')
        ->assertSee('Apply community restriction?')
        ->assertSee('Suspend this account?')
        ->assertSee('Dismiss this user report?')
        ->assertDontSee('Restrict report submission?')
        ->assertDontSee('confirm(', false);

    $script = file_get_contents(resource_path('js/app.js'));
    $page = file_get_contents(resource_path('views/admin/user-reports.blade.php'));

    expect($script)
        ->toContain("import Swal from 'sweetalert2'")
        ->toContain('form[data-admin-moderation-confirmation]')
        ->toContain('window.showAdminAlert = function')
        ->toContain("error: 'Something went wrong'")
        ->toContain('form.requestSubmit()')
        ->and($page)
        ->toContain('Lift this restriction?')
        ->toContain('Unsuspend this account?');
});

test('admin moderation alerts do not alter the resident alert integration', function () {
    $resident = User::factory()->create();

    $this->actingAs($resident)
        ->withSession(['success' => 'Resident feedback remains available.'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="residentAlertData"', false)
        ->assertDontSee('adminAlertData');
});
