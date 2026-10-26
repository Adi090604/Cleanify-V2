<?php

use App\Models\Report;
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

function profilePhotoReport(User $author): Report
{
    return Report::query()->create([
        'user_id' => $author->id,
        'barangay' => 'Nabago',
        'location' => 'Profile photo test location',
        'description' => 'Profile photo report card test.',
        'status' => Report::STATUS_PENDING,
        'priority' => 'medium',
    ]);
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

test('Home report cards render the author profile photo', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/home-author.jpg', 'photo');
    $author = User::factory()->create([
        'name' => 'Home Photo Author',
        'profile_photo_path' => 'profile-photos/home-author.jpg',
    ]);
    $viewer = User::factory()->create();
    profilePhotoReport($author);

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('src="/storage/profile-photos/home-author.jpg"', false)
        ->assertSee("alt=\"{$author->name}'s profile photo\"", false);
});

test('Home report cards retain the author initials fallback', function () {
    Storage::fake('public');
    $author = User::factory()->create([
        'name' => 'Home Fallback Author',
        'profile_photo_path' => null,
    ]);
    $viewer = User::factory()->create();
    profilePhotoReport($author);

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($author->getAvatarInitial())
        ->assertDontSee("alt=\"{$author->name}'s profile photo\"", false);
});

test('Community report cards render the author profile photo', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/community-author.jpg', 'photo');
    $author = User::factory()->create([
        'name' => 'Community Photo Author',
        'profile_photo_path' => 'profile-photos/community-author.jpg',
    ]);
    $viewer = User::factory()->create();
    profilePhotoReport($author);

    $this->actingAs($viewer)
        ->get(route('community-reports'))
        ->assertOk()
        ->assertSee('src="/storage/profile-photos/community-author.jpg"', false)
        ->assertSee("alt=\"{$author->name}'s profile photo\"", false);
});

test('Community report cards retain the author initials fallback for missing files', function () {
    Storage::fake('public');
    $author = User::factory()->create([
        'name' => 'Community Fallback Author',
        'profile_photo_path' => 'profile-photos/missing-community-author.jpg',
    ]);
    $viewer = User::factory()->create();
    profilePhotoReport($author);

    $this->actingAs($viewer)
        ->get(route('community-reports'))
        ->assertOk()
        ->assertSee($author->getAvatarInitial())
        ->assertDontSee('profile-photos/missing-community-author.jpg')
        ->assertDontSee("alt=\"{$author->name}'s profile photo\"", false);
});

test('Community report interactions remain available after rendering author photos', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/interactive-author.jpg', 'photo');
    $author = User::factory()->create([
        'profile_photo_path' => 'profile-photos/interactive-author.jpg',
    ]);
    $viewer = User::factory()->create();
    $report = profilePhotoReport($author);

    $this->actingAs($viewer)
        ->get(route('reports.show', $report))
        ->assertOk()
        ->assertJsonPath('id', $report->id);

    $this->postJson(route('reports.like', $report))
        ->assertOk()
        ->assertJson(['liked' => true, 'likes_count' => 1]);

    $this->postJson(route('reports.comment', $report), ['comment' => 'Still works.'])
        ->assertOk()
        ->assertJsonPath('comment.comment', 'Still works.');

    $this->getJson(route('reports.comments', $report))
        ->assertOk()
        ->assertJsonPath('comments.0.comment', 'Still works.');

    $this->postJson(route('reports.follow', $report))
        ->assertOk()
        ->assertJson(['following' => true, 'followers_count' => 1]);
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
