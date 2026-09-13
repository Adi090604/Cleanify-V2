<?php

use App\Models\Schedule;
use App\Models\ServiceZone;
use App\Models\User;
use Carbon\Carbon;

function mobileSchedule(array $attributes = []): Schedule
{
    return Schedule::create(array_merge([
        'area' => 'Zone 1 - Barangay Washington',
        'schedule_type' => 'recurring',
        'specific_date' => null,
        'days' => 'Monday & Thursday',
        'time_start' => '06:00',
        'time_end' => '09:00',
        'truck' => 'Truck 01',
        'status' => 'active',
    ], $attributes));
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-13 12:00:00'));
    ServiceZone::create([
        'name' => 'Zone 1',
        'barangay' => 'Barangay Washington',
        'status' => 'active',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('mobile schedule endpoints require Sanctum authentication', function () {
    $this->getJson('/api/v1/schedules')->assertUnauthorized();
    $this->getJson('/api/v1/schedules/next')->assertUnauthorized();
});

test('an authenticated user receives only active schedules for their exact service area', function () {
    $user = User::factory()->create(['service_area' => 'Zone 1 - Barangay Washington']);
    $matching = mobileSchedule();
    mobileSchedule(['area' => 'Zone 2 - Barangay Taft', 'truck' => 'Truck 02']);
    mobileSchedule(['status' => 'inactive', 'truck' => 'Truck 03']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/schedules')
        ->assertOk()
        ->assertJsonPath('service_area', $user->service_area)
        ->assertJsonCount(1, 'schedules')
        ->assertJsonPath('schedules.0.id', $matching->id)
        ->assertJsonPath('schedules.0.area', $user->service_area)
        ->assertJsonPath('schedules.0.status', 'active')
        ->assertJsonMissingPath('schedules.0.created_at');
});

test('a recurring schedule returns its next collection using existing day separators', function () {
    $user = User::factory()->create(['service_area' => 'Zone 1 - Barangay Washington']);
    mobileSchedule(['days' => 'Monday & Thursday', 'time_start' => '06:00']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/schedules/next')
        ->assertOk()
        ->assertJsonPath('next_collection.area', $user->service_area)
        ->assertJsonPath('next_collection.collection_at', '2026-09-14T06:00:00+00:00')
        ->assertJsonPath('next_collection.time_range', '6:00 AM - 9:00 AM');
});

test('a specific-date schedule returns its future collection datetime', function () {
    $user = User::factory()->create(['service_area' => 'Zone 1 - Barangay Washington']);
    mobileSchedule([
        'schedule_type' => 'specific_date',
        'specific_date' => '2026-09-16',
        'days' => null,
        'time_start' => '08:30',
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/schedules/next')
        ->assertOk()
        ->assertJsonPath('next_collection.schedule_type', 'specific_date')
        ->assertJsonPath('next_collection.collection_at', '2026-09-16T08:30:00+00:00');
});

test('past specific-date and inactive schedules are not upcoming', function () {
    $user = User::factory()->create(['service_area' => 'Zone 1 - Barangay Washington']);
    mobileSchedule([
        'schedule_type' => 'specific_date',
        'specific_date' => '2026-09-12',
        'days' => null,
    ]);
    mobileSchedule(['status' => 'inactive']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/schedules/next')
        ->assertOk()
        ->assertJsonPath('next_collection', null);
});

test('a user without a service area receives clean empty schedule responses', function () {
    $user = User::factory()->create(['service_area' => null]);
    mobileSchedule();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/schedules')
        ->assertOk()
        ->assertJsonPath('service_area', null)
        ->assertJsonCount(0, 'schedules')
        ->assertJsonCount(0, 'upcoming_pickups');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/schedules/next')
        ->assertOk()
        ->assertJsonPath('next_collection', null);
});

test('an invalid or deleted service area returns no stale schedule data', function () {
    $user = User::factory()->create(['service_area' => 'Deleted Zone']);
    mobileSchedule(['area' => 'Deleted Zone']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/schedules')
        ->assertOk()
        ->assertJsonPath('service_area', null)
        ->assertJsonCount(0, 'schedules')
        ->assertJsonCount(0, 'upcoming_pickups');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/schedules/next')
        ->assertOk()
        ->assertJsonPath('service_area', null)
        ->assertJsonPath('next_collection', null);
});

test('an inactive service area returns no stale schedule data', function () {
    ServiceZone::where('name', 'Zone 1')->update(['status' => 'inactive']);
    $user = User::factory()->create(['service_area' => 'Zone 1 - Barangay Washington']);
    mobileSchedule();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/schedules/next')
        ->assertOk()
        ->assertJsonPath('service_area', null)
        ->assertJsonPath('next_collection', null);
});

test('upcoming pickups are scoped to the user area and ordered by collection time', function () {
    $user = User::factory()->create(['service_area' => 'Zone 1 - Barangay Washington']);
    $later = mobileSchedule(['days' => 'Thursday', 'truck' => 'Truck Later']);
    $next = mobileSchedule(['days' => 'Monday', 'truck' => 'Truck Next']);
    mobileSchedule(['area' => 'Zone 2 - Barangay Taft', 'days' => 'Monday']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/schedules');

    $response->assertOk()->assertJsonCount(2, 'upcoming_pickups');
    expect($response->json('upcoming_pickups.0.schedule_id'))->toBe($next->id)
        ->and($response->json('upcoming_pickups.1.schedule_id'))->toBe($later->id);
});

test('upcoming pickups include each configured recurring day', function () {
    $user = User::factory()->create(['service_area' => 'Zone 1 - Barangay Washington']);
    mobileSchedule(['days' => 'Monday & Thursday']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/schedules')
        ->assertOk()
        ->assertJsonCount(2, 'upcoming_pickups')
        ->assertJsonPath('upcoming_pickups.0.date_display', 'Monday, September 14')
        ->assertJsonPath('upcoming_pickups.1.date_display', 'Thursday, September 17');
});
