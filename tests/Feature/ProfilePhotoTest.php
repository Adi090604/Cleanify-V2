<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function profilePhotoUpdate(User $user, array $attributes = [])
{
    return test()->actingAs($user)->post('/profile', array_merge([
        '_method' => 'PATCH',
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'address' => $user->address,
    ], $attributes));
}

test('an authenticated user can upload a profile photo to the public disk', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    profilePhotoUpdate($user, [
        'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
    ])->assertRedirect(route('profile'));

    $path = $user->fresh()->profile_photo_path;
    expect($path)->toStartWith('profile-photos/');
    Storage::disk('public')->assertExists($path);

    $this->actingAs($user->fresh())
        ->get('/profile')
        ->assertOk()
        ->assertSee($user->fresh()->profile_photo_url);
});

test('profile photo upload rejects unsupported file types and oversized images', function () {
    $user = User::factory()->create();

    profilePhotoUpdate($user, [
        'profile_photo' => UploadedFile::fake()->create('profile.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors('profile_photo');

    profilePhotoUpdate($user, [
        'profile_photo' => UploadedFile::fake()->image('large-profile.jpg')->size(4097),
    ])->assertSessionHasErrors('profile_photo');
});

test('replacing a profile photo removes the previous public-disk file', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/old.jpg', 'old-photo');
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/old.jpg']);

    profilePhotoUpdate($user, [
        'profile_photo' => UploadedFile::fake()->image('new-profile.png'),
    ])->assertRedirect(route('profile'));

    $newPath = $user->fresh()->profile_photo_path;
    expect($newPath)->not->toBe('profile-photos/old.jpg');
    Storage::disk('public')->assertMissing('profile-photos/old.jpg');
    Storage::disk('public')->assertExists($newPath);
});

test('a user can remove their profile photo', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/remove.jpg', 'photo');
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/remove.jpg']);

    profilePhotoUpdate($user, ['remove_profile_photo' => true])
        ->assertRedirect(route('profile'));

    expect($user->fresh()->profile_photo_path)->toBeNull();
    Storage::disk('public')->assertMissing('profile-photos/remove.jpg');

    $token = $user->fresh()->createToken('mobile-app');
    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('user.profile_photo_url', null);
});

test('an admin uses the same profile photo field and storage behavior', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->post('/admin/settings/profile', [
        'name' => $admin->name,
        'email' => $admin->email,
        'profile_photo' => UploadedFile::fake()->image('admin-profile.webp'),
    ])->assertRedirect(route('admin.settings'));

    $path = $admin->fresh()->profile_photo_path;
    expect($path)->toStartWith('profile-photos/');
    Storage::disk('public')->assertExists($path);

    $this->actingAs($admin->fresh())
        ->get('/admin/settings')
        ->assertOk()
        ->assertSee($admin->fresh()->profile_photo_url);
});

test('the mobile me response exposes a photo URL but not sensitive or raw photo fields', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/mobile.jpg', 'photo');
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/mobile.jpg']);
    $token = $user->createToken('mobile-app');

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('user.profile_photo_url', $user->profile_photo_url)
        ->assertJsonMissingPath('user.profile_photo_path')
        ->assertJsonMissingPath('user.password')
        ->assertJsonMissingPath('user.remember_token');
});
