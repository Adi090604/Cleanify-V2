<?php

use App\Models\Schedule;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('schedule time controls use hidden backend values instead of native time inputs', function () {
    $view = file_get_contents(resource_path('views/admin/schedule.blade.php'));

    expect($view)
        ->not->toMatch('/<input[^>]+type="time"/')
        ->toContain('type="hidden" name="{{ $timePicker[\'name\'] }}"')
        ->toContain('data-time-trigger')
        ->toContain('data-time-popover')
        ->toContain('Array.from({ length: 60 }')
        ->toContain("event.key === 'Escape'")
        ->toContain('!activeTimePicker.contains(event.target)');
});

test('schedule store and update continue accepting exact 24 hour values', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->post(route('admin.schedule.store'), [
        'area' => 'Zone 1',
        'schedule_type' => 'recurring',
        'days' => 'Monday',
        'time_start' => '00:15',
        'time_end' => '12:30',
        'truck' => 'TRK-01',
        'status' => 'pending',
    ])->assertRedirect(route('admin.schedule'));

    $schedule = Schedule::firstOrFail();

    expect($schedule->time_start->format('H:i'))->toBe('00:15')
        ->and($schedule->time_end->format('H:i'))->toBe('12:30');

    $this->actingAs($admin)->put(route('admin.schedule.update', $schedule->id), [
        'area' => $schedule->area,
        'schedule_type' => $schedule->schedule_type,
        'days' => $schedule->days,
        'time_start' => '00:15',
        'time_end' => '12:30',
        'truck' => $schedule->truck,
        'status' => $schedule->status,
    ])->assertRedirect(route('admin.schedule'));

    $schedule->refresh();

    expect($schedule->time_start->format('H:i'))->toBe('00:15')
        ->and($schedule->time_end->format('H:i'))->toBe('12:30');
});

test('schedule validation still rejects invalid time ranges', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->post(route('admin.schedule.store'), [
        'area' => 'Zone 1',
        'schedule_type' => 'recurring',
        'days' => 'Monday',
        'time_start' => '13:30',
        'time_end' => '12:30',
        'truck' => 'TRK-01',
        'status' => 'pending',
    ])->assertSessionHasErrors(['time_end']);

    $this->assertDatabaseCount('schedules', 0);
});

test('schedule truck options show real identifying details while retaining code values', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Truck::create([
        'code' => 'TRK-01',
        'driver' => 'Juan Dela Cruz',
        'route' => 'Zone 1 - Brgy. Alang-Alang',
        'status' => 'active',
    ]);
    Truck::create([
        'code' => 'TRK-02',
        'driver' => '',
        'route' => '',
        'status' => 'offline',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.schedule'));
    $response->assertOk();
    $html = $response->getContent();

    expect(substr_count($html, 'TRK-01 — Juan Dela Cruz — Zone 1 - Brgy. Alang-Alang'))->toBe(2)
        ->and(substr_count($html, 'TRK-02 — No driver assigned — No route assigned'))->toBe(2)
        ->and(substr_count($html, 'value="TRK-01"'))->toBe(2)
        ->and($html)->toContain("document.getElementById('editScheduleTruck').value = schedule.truck;");
});
