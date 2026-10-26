<?php

use App\Models\Report;
use App\Models\ReportComment;
use App\Models\ReportLike;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function mobileReport(array $attributes = []): Report
{
    return Report::create(array_merge([
        'user_id' => User::factory()->create()->id,
        'barangay' => 'Mabua',
        'location' => 'Beside the public market',
        'description' => 'Uncollected waste beside the road.',
        'status' => 'pending',
        'priority' => 'medium',
    ], $attributes));
}

test('the mobile report feed requires Sanctum authentication', function () {
    $this->getJson('/api/v1/reports')->assertUnauthorized();
    $this->getJson('/api/v1/reports/1')->assertUnauthorized();
});

test('an authenticated user receives real report fields with safe author data', function () {
    $viewer = User::factory()->create();
    $report = mobileReport([
        'barangay' => 'Nabago',
        'location' => 'Zone 31',
        'latitude' => 9.80123456,
        'longitude' => 125.49234567,
        'description' => 'Overflowing community bin.',
        'status' => 'resolved',
        'priority' => 'high',
    ]);

    $this->actingAs($viewer, 'sanctum')
        ->getJson('/api/v1/reports')
        ->assertOk()
        ->assertJsonPath('data.0.id', $report->id)
        ->assertJsonPath('data.0.author.name', $report->user->name)
        ->assertJsonPath('data.0.author.profile_photo_url', null)
        ->assertJsonPath('data.0.description', 'Overflowing community bin.')
        ->assertJsonPath('data.0.barangay', 'Nabago')
        ->assertJsonPath('data.0.location', 'Zone 31')
        ->assertJsonPath('data.0.status', 'resolved')
        ->assertJsonPath('data.0.priority', 'high')
        ->assertJsonMissingPath('data.0.author.email')
        ->assertJsonMissingPath('data.0.author.password')
        ->assertJsonMissingPath('data.0.image_path');
});

test('report endpoints return an absolute existing author profile photo URL', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/report-author.jpg', 'photo');
    $viewer = User::factory()->create();
    $author = User::factory()->create([
        'profile_photo_path' => 'profile-photos/report-author.jpg',
    ]);
    $report = mobileReport(['user_id' => $author->id]);
    $expectedUrl = rtrim(config('app.url'), '/').'/storage/profile-photos/report-author.jpg';

    foreach ([
        '/api/v1/reports',
        '/api/v1/reports/home',
        "/api/v1/reports/{$report->id}",
    ] as $endpoint) {
        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson($endpoint)
            ->assertOk();

        $response->assertJsonPath(
            $endpoint === "/api/v1/reports/{$report->id}"
                ? 'data.author.profile_photo_url'
                : 'data.0.author.profile_photo_url',
            $expectedUrl,
        );
    }
});

test('report endpoints do not expose a missing author profile photo path', function () {
    Storage::fake('public');
    $viewer = User::factory()->create();
    $author = User::factory()->create([
        'profile_photo_path' => 'profile-photos/missing-report-author.jpg',
    ]);
    mobileReport(['user_id' => $author->id]);

    $this->actingAs($viewer, 'sanctum')
        ->getJson('/api/v1/reports')
        ->assertOk()
        ->assertJsonPath('data.0.author.profile_photo_url', null)
        ->assertJsonMissingPath('data.0.author.profile_photo_path')
        ->assertJsonMissing(['profile_photo_path' => 'profile-photos/missing-report-author.jpg']);
});

test('the mobile report feed is paginated six reports per page', function () {
    $viewer = User::factory()->create();

    foreach (range(1, 7) as $number) {
        mobileReport(['description' => "Report {$number}"]);
    }

    $this->actingAs($viewer, 'sanctum')
        ->getJson('/api/v1/reports')
        ->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonPath('meta.per_page', 6)
        ->assertJsonPath('meta.total', 7)
        ->assertJsonPath('meta.last_page', 2);
});

test('the mobile report feed orders newest reports first', function () {
    $viewer = User::factory()->create();
    $older = mobileReport();
    $older->forceFill(['created_at' => now()->subDay()])->saveQuietly();
    $newer = mobileReport();

    $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/reports');

    expect($response->json('data.0.id'))->toBe($newer->id)
        ->and($response->json('data.1.id'))->toBe($older->id);
});

test('the mobile report feed returns real like and comment counts and current like state', function () {
    $viewer = User::factory()->create();
    $otherUser = User::factory()->create();
    $report = mobileReport();

    ReportLike::create(['report_id' => $report->id, 'user_id' => $viewer->id]);
    ReportLike::create(['report_id' => $report->id, 'user_id' => $otherUser->id]);
    ReportComment::create([
        'report_id' => $report->id,
        'user_id' => $otherUser->id,
        'comment' => 'This is still visible today.',
    ]);

    $this->actingAs($viewer, 'sanctum')
        ->getJson('/api/v1/reports')
        ->assertOk()
        ->assertJsonPath('data.0.likes_count', 2)
        ->assertJsonPath('data.0.comments_count', 1)
        ->assertJsonPath('data.0.is_liked', true);
});

test('the mobile report feed returns the public storage URL for an existing image', function () {
    Storage::fake('public');
    Storage::disk('public')->put('reports/photo.jpg', 'image-content');

    $viewer = User::factory()->create();
    mobileReport(['image_path' => 'reports/photo.jpg']);

    $this->actingAs($viewer, 'sanctum')
        ->getJson('/api/v1/reports')
        ->assertOk()
        ->assertJsonPath('data.0.image_url', 'http://localhost/storage/reports/photo.jpg');
});

test('the mobile report feed omits an image URL when no stored photo exists', function () {
    Storage::fake('public');

    $viewer = User::factory()->create();
    mobileReport(['image_path' => null]);

    $this->actingAs($viewer, 'sanctum')
        ->getJson('/api/v1/reports')
        ->assertOk()
        ->assertJsonPath('data.0.image_url', null);
});
