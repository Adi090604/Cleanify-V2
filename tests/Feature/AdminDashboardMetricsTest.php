<?php

use App\Models\Report;
use App\Models\Schedule;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

test('admin dashboard exposes truthful current-week activity and role percentages', function () {
    Carbon::setTestNow('2026-09-17 12:00:00');

    $admin = User::factory()->create([
        'name' => 'Cleanify Admin',
        'is_admin' => true,
        'created_at' => now()->subWeeks(2),
    ]);

    $currentReporter = User::factory()->create(['created_at' => now()->subDay()]);
    User::factory()->create(['created_at' => now()->startOfWeek()]);
    User::factory()->create(['created_at' => now()->subWeeks(2)]);

    foreach ([now()->subDay(), now()->startOfWeek(), now()->subWeeks(2)] as $createdAt) {
        Report::query()->forceCreate([
            'user_id' => $currentReporter->id,
            'location' => 'Dashboard metrics test location',
            'description' => 'Dashboard metrics test report.',
            'status' => 'pending',
            'priority' => 'medium',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    foreach ([
        ['status' => 'active', 'created_at' => now()->subDay()],
        ['status' => 'active', 'created_at' => now()->subWeeks(2)],
        ['status' => 'pending', 'created_at' => now()->subDay()],
    ] as $attributes) {
        Schedule::query()->forceCreate([
            'area' => 'Test Area',
            'schedule_type' => 'recurring',
            'days' => 'Monday',
            'time_start' => '08:00',
            'time_end' => '09:00',
            'truck' => 'TRK-TEST',
            'status' => $attributes['status'],
            'created_at' => $attributes['created_at'],
            'updated_at' => $attributes['created_at'],
        ]);
    }

    foreach ([
        ['code' => 'TRK-001', 'status' => 'active', 'created_at' => now()->subDay()],
        ['code' => 'TRK-002', 'status' => 'active', 'created_at' => now()->subWeeks(2)],
        ['code' => 'TRK-003', 'status' => 'offline', 'created_at' => now()->subDay()],
    ] as $attributes) {
        Truck::query()->forceCreate([
            'code' => $attributes['code'],
            'driver' => 'Test Driver',
            'route' => 'Test Route',
            'status' => $attributes['status'],
            'created_at' => $attributes['created_at'],
            'updated_at' => $attributes['created_at'],
        ]);
    }

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('totalUsers', 4)
        ->assertViewHas('totalReports', 3)
        ->assertViewHas('activeSchedules', 2)
        ->assertViewHas('activeTrucks', 2)
        ->assertViewHas('usersThisWeek', 2)
        ->assertViewHas('reportsThisWeek', 2)
        ->assertViewHas('activeSchedulesThisWeek', 1)
        ->assertViewHas('activeTrucksThisWeek', 1)
        ->assertViewHas('regularUserPercentage', 75.0)
        ->assertViewHas('adminPercentage', 25.0)
        ->assertViewHas('admin', fn (User $user) => $user->is($admin))
        ->assertSee('Welcome, Admin')
        ->assertSee('+2 this week')
        ->assertSee('+1 active added this week')
        ->assertSee('Thu, Sep 17, 2026');
});
