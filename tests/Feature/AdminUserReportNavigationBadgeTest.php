<?php

use App\Models\Report;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Support\Facades\Notification;

function userReportForAdminNavigationBadge(string $status = 'pending'): UserReport
{
    return UserReport::query()->create([
        'reporter_id' => User::factory()->create()->id,
        'reported_user_id' => User::factory()->create()->id,
        'reason' => 'harassment',
        'description' => 'User report used to verify the Admin navigation badge.',
        'status' => $status,
    ]);
}

function communityReportForIndependentAdminBadge(User $reporter, string $status = 'pending'): Report
{
    return Report::query()->create([
        'user_id' => $reporter->id,
        'barangay' => 'Barangay Washington',
        'location' => 'Independent badge test location',
        'description' => 'Community Report used to verify independent Admin badges.',
        'status' => $status,
        'priority' => 'medium',
    ]);
}

test('admin navigation hides the user report badge when no cases are pending', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    userReportForAdminNavigationBadge('dismissed');
    userReportForAdminNavigationBadge('action_taken');
    userReportForAdminNavigationBadge('reviewed');

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('pending user reports');
});

test('desktop and mobile admin navigation show one pending user report', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    userReportForAdminNavigationBadge();

    $html = $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'aria-label="1 pending user reports"'))->toBe(2);
});

test('pending user report badge counts only pending cases', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    foreach (range(1, 5) as $_) {
        userReportForAdminNavigationBadge();
    }

    userReportForAdminNavigationBadge('dismissed');
    userReportForAdminNavigationBadge('action_taken');
    userReportForAdminNavigationBadge('reviewed');

    $html = $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'aria-label="5 pending user reports"'))->toBe(2)
        ->and($html)->not->toContain('aria-label="8 pending user reports"');
});

test('reports and user reports badges render independently with identical badge styling', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create();
    communityReportForIndependentAdminBadge($reporter);
    communityReportForIndependentAdminBadge($reporter, 'resolved');
    userReportForAdminNavigationBadge();
    userReportForAdminNavigationBadge();

    $html = $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->getContent();
    $badgeClasses = 'ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-bold leading-none text-white';

    expect(substr_count($html, 'aria-label="1 pending reports"'))->toBe(2)
        ->and(substr_count($html, 'aria-label="2 pending user reports"'))->toBe(2)
        ->and(substr_count($html, $badgeClasses))->toBe(4);
});

test('user report badge refreshes after dismissing and actioning pending cases', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $dismissed = userReportForAdminNavigationBadge();
    $actioned = userReportForAdminNavigationBadge();

    $before = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();
    expect(substr_count($before, 'aria-label="2 pending user reports"'))->toBe(2);

    $this->put(route('admin.user-reports.update', $dismissed), ['decision' => 'dismiss'])
        ->assertRedirect();

    $afterDismissal = $this->get(route('admin.dashboard'))->assertOk()->getContent();
    expect(substr_count($afterDismissal, 'aria-label="1 pending user reports"'))->toBe(2);

    $this->put(route('admin.user-reports.update', $actioned), ['decision' => 'warning'])
        ->assertRedirect();

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('pending user reports');
});
