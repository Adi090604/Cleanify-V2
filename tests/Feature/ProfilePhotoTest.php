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

test('a stored profile photo renders with a request-relative URL on the user profile', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/user.jpg', 'photo');
    config(['filesystems.disks.public.url' => 'http://stale-host.test/storage']);
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/user.jpg']);

    expect($user->profile_photo_url)
        ->toBe('/storage/profile-photos/user.jpg')
        ->not->toContain('localhost')
        ->not->toContain('stale-host.test');

    $this->actingAs($user)
        ->get('http://127.0.0.1:8000/profile')
        ->assertOk()
        ->assertSee('src="/storage/profile-photos/user.jpg"', false)
        ->assertDontSee('stale-host.test');
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

test('an admin profile photo renders with a request-relative URL', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/admin.jpg', 'photo');
    config(['filesystems.disks.public.url' => 'http://stale-host.test/storage']);
    $admin = User::factory()->create([
        'is_admin' => true,
        'profile_photo_path' => 'profile-photos/admin.jpg',
    ]);

    $this->actingAs($admin)
        ->get('http://localhost:8000/admin/settings')
        ->assertOk()
        ->assertSee('src="/storage/profile-photos/admin.jpg"', false)
        ->assertDontSee('stale-host.test');

    $this->actingAs($admin)
        ->get('http://localhost:8000/admin/dashboard')
        ->assertOk()
        ->assertSee('src="/storage/profile-photos/admin.jpg"', false)
        ->assertDontSee('stale-host.test');
});

test('a missing profile photo file falls back without rendering a broken image', function () {
    Storage::fake('public');
    $user = User::factory()->create([
        'name' => 'Missing Photo',
        'profile_photo_path' => 'profile-photos/missing.jpg',
    ]);

    expect($user->profile_photo_url)->toBeNull();

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertDontSee('profile-photos/missing.jpg')
        ->assertSee($user->getAvatarInitial());
});

test('a null admin profile photo falls back without rendering a broken image', function () {
    Storage::fake('public');
    $admin = User::factory()->create([
        'name' => 'Fallback Admin',
        'is_admin' => true,
        'profile_photo_path' => null,
    ]);

    expect($admin->profile_photo_url)->toBeNull();

    $this->actingAs($admin)
        ->get('/admin/settings')
        ->assertOk()
        ->assertSee($admin->getAvatarInitial())
        ->assertDontSee("{$admin->name}'s profile photo");
});

test('the mobile me response exposes a photo URL but not sensitive or raw photo fields', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/mobile.jpg', 'photo');
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/mobile.jpg']);
    $token = $user->createToken('mobile-app');

    $this->withToken($token->plainTextToken)
        ->getJson('http://10.0.0.50:8000/api/v1/me')
        ->assertOk()
        ->assertJsonPath('user.profile_photo_url', 'http://10.0.0.50:8000/storage/profile-photos/mobile.jpg')
        ->assertJsonMissingPath('user.profile_photo_path')
        ->assertJsonMissingPath('user.password')
        ->assertJsonMissingPath('user.remember_token');
});
