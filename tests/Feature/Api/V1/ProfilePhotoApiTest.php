<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('profile photo API requires Sanctum authentication', function () {
    $this->postJson('/api/v1/me/profile-photo')->assertUnauthorized();
    $this->deleteJson('/api/v1/me/profile-photo')->assertUnauthorized();
});

test('an authenticated user can upload a request-host-aware profile photo', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->post('http://192.168.1.5:8000/api/v1/me/profile-photo', [
            'profile_photo' => UploadedFile::fake()->image('avatar.png'),
        ], ['Accept' => 'application/json'])
        ->assertOk();

    $path = $user->fresh()->profile_photo_path;
    Storage::disk('public')->assertExists($path);
    $response->assertJsonPath('user.profile_photo_url', 'http://192.168.1.5:8000/storage/'.$path);

    $this->actingAs($user, 'sanctum')->getJson('http://192.168.1.5:8000/api/v1/me')
        ->assertJsonPath('user.profile_photo_url', 'http://192.168.1.5:8000/storage/'.$path);
});

test('profile photo API rejects unsupported and oversized images', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->post('/api/v1/me/profile-photo', [
        'profile_photo' => UploadedFile::fake()->create('avatar.gif', 10, 'image/gif'),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('profile_photo');

    $this->actingAs($user, 'sanctum')->post('/api/v1/me/profile-photo', [
        'profile_photo' => UploadedFile::fake()->image('large.jpg')->size(4097),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('profile_photo');
});

test('replacing and removing a profile photo deletes the old public file', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/old.jpg', 'old');
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/old.jpg']);

    $this->actingAs($user, 'sanctum')->post('/api/v1/me/profile-photo', [
        'profile_photo' => UploadedFile::fake()->image('new.webp'),
    ], ['Accept' => 'application/json'])->assertOk();

    Storage::disk('public')->assertMissing('profile-photos/old.jpg');
    $newPath = $user->fresh()->profile_photo_path;
    Storage::disk('public')->assertExists($newPath);

    $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/me/profile-photo')
        ->assertOk()->assertJsonPath('user.profile_photo_url', null);

    Storage::disk('public')->assertMissing($newPath);
    expect($user->fresh()->profile_photo_path)->toBeNull();
});

test('a user cannot modify another users profile photo', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $other = User::factory()->create(['profile_photo_path' => 'profile-photos/other.jpg']);
    Storage::disk('public')->put($other->profile_photo_path, 'other');

    $this->actingAs($user, 'sanctum')->post('/api/v1/me/profile-photo', [
        'profile_photo' => UploadedFile::fake()->image('mine.jpg'),
    ], ['Accept' => 'application/json'])->assertOk();

    expect($other->fresh()->profile_photo_path)->toBe('profile-photos/other.jpg');
    Storage::disk('public')->assertExists('profile-photos/other.jpg');
});
