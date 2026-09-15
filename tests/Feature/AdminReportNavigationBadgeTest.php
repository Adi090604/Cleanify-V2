<?php

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Blade;

function reportForAdminBadge(User $user, string $status = 'pending'): Report
{
    return Report::query()->create([
        'user_id' => $user->id,
        'location' => 'Badge test location',
        'description' => 'Report used to verify the admin navigation badge.',
        'status' => $status,
        'priority' => 'medium',
    ]);
}

test('admin reports navigation shows only the real pending report count and hides zero', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create();

    $this->actingAs($admin)->get('/admin/dashboard')
        ->assertOk()
        ->assertDontSee('pending reports');

    reportForAdminBadge($reporter);
    reportForAdminBadge($reporter);
    reportForAdminBadge($reporter, 'resolved');
    reportForAdminBadge($reporter, 'rejected');

    $this->get('/admin/dashboard')
        ->assertOk()
        ->assertSee('href="'.route('admin.reports').'"', false)
        ->assertSee('aria-label="2 pending reports"', false);
});

test('admin reports navigation caps pending report counts at 99 plus', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create();

    foreach (range(1, 100) as $_) {
        reportForAdminBadge($reporter);
    }

    $this->actingAs($admin)->get('/admin/dashboard')
        ->assertOk()
        ->assertSee('aria-label="100 pending reports"', false)
        ->assertSee('99+', false);
});

test('admin reports navigation keeps its active state on the reports page', function () {
    $sidebar = Blade::render(
        '<x-admin.sidebar active="reports" :pending-report-count="1" />'
    );

    expect($sidebar)
        ->toContain('href="'.route('admin.reports').'"')
        ->toContain('aria-label="1 pending reports"')
        ->toContain('bg-green-700');
});
