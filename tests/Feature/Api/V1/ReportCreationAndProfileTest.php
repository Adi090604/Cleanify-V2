<?php

use App\Models\Report;
use App\Models\ReportComment;
use App\Models\ReportLike;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('unauthenticated users cannot create reports or access my posts', function () {
    $this->postJson('/api/v1/reports', [])->assertUnauthorized();
    $this->getJson('/api/v1/me/reports')->assertUnauthorized();
});

test('an authenticated user can create a pending report with the web fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/reports', [
        'location' => 'Barangay Mabua',
        'latitude' => 9.78701234,
        'longitude' => 125.49281234,
        'description' => 'Waste has not been collected.',
    ])->assertCreated()
        ->assertJsonPath('data.author.id', $user->id)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.priority', 'medium');

    $report = Report::findOrFail($response->json('data.id'));
    expect($report->user_id)->toBe($user->id)
        ->and($report->location)->toBe('Barangay Mabua')
        ->and((float) $report->latitude)->toBe(9.78701234)
        ->and((float) $report->longitude)->toBe(125.49281234);
});

test('report creation enforces existing description coordinate and image validation', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/reports', [
        'latitude' => 91,
        'longitude' => 181,
        'image' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['description', 'latitude', 'longitude', 'image']);
});

test('report photo is stored on the public reports disk and serialized in the feed', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->post('/api/v1/reports', [
        'description' => 'Photo report.',
        'image' => UploadedFile::fake()->image('waste.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();

    $report = Report::findOrFail($response->json('data.id'));
    Storage::disk('public')->assertExists($report->image_path);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/reports')
        ->assertOk()
        ->assertJsonPath('data.0.id', $report->id)
        ->assertJsonPath('data.0.image_url', 'http://localhost/storage/'.$report->image_path)
        ->assertJsonPath('data.0.likes_count', 0)
        ->assertJsonPath('data.0.comments_count', 0);
});

test('my posts returns only the authenticated users reports with current status and counts', function () {
    Storage::fake('public');
    Storage::disk('public')->put('reports/mine.jpg', 'image');
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mine = Report::create([
        'user_id' => $user->id,
        'location' => 'My area',
        'description' => 'My report',
        'image_path' => 'reports/mine.jpg',
        'status' => 'resolved',
        'priority' => 'medium',
    ]);
    Report::create([
        'user_id' => $other->id,
        'location' => 'Other area',
        'description' => 'Another report',
        'status' => 'pending',
        'priority' => 'medium',
    ]);
    ReportLike::create(['report_id' => $mine->id, 'user_id' => $other->id]);
    ReportComment::create(['report_id' => $mine->id, 'user_id' => $other->id, 'comment' => 'Noted.']);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/reports')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('data.0.status', 'resolved')
        ->assertJsonPath('data.0.likes_count', 1)
        ->assertJsonPath('data.0.comments_count', 1)
        ->assertJsonPath('data.0.image_url', 'http://localhost/storage/reports/mine.jpg')
        ->assertJsonMissingPath('data.0.author.email');
});
