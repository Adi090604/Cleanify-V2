<?php

use App\Models\User;

test('administrator user management displays stored and missing mobile numbers', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $withPhone = User::factory()->create(['phone' => '+639171234567']);
    $withoutPhone = User::factory()->create(['phone' => null]);

    $response = $this->actingAs($admin)->get(route('admin.users'));

    $response->assertOk()
        ->assertSee('Mobile')
        ->assertSee($withPhone->phone)
        ->assertSee('Not provided')
        ->assertSee('viewUserPhone')
        ->assertSee("user.phone || 'Not provided'", false)
        ->assertSee('User')
        ->assertSee('Admin');

    expect($withoutPhone->fresh()->phone)->toBeNull();
});

test('regular users cannot access administrator user management phone numbers', function () {
    $resident = User::factory()->create(['is_admin' => false]);
    User::factory()->create(['phone' => '+639181234567']);

    $this->actingAs($resident)
        ->get(route('admin.users'))
        ->assertForbidden();
});
