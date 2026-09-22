<?php

use App\Models\Report;
use App\Models\ReportComment;
use App\Models\ReportLike;
use App\Models\User;

function interactionReport(array $attributes = []): Report
{
    return Report::create(array_merge([
        'user_id' => User::factory()->create()->id,
        'location' => 'Barangay Mabua',
        'description' => 'Community report for interaction testing.',
        'status' => 'pending',
        'priority' => 'medium',
    ], $attributes));
}

test('mobile report interactions require Sanctum authentication', function () {
    $report = interactionReport();

    $this->postJson("/api/v1/reports/{$report->id}/like")->assertUnauthorized();
    $this->getJson("/api/v1/reports/{$report->id}/comments")->assertUnauthorized();
    $this->postJson("/api/v1/reports/{$report->id}/comment", [
        'comment' => 'This should not be stored.',
    ])->assertUnauthorized();
});

test('an authenticated user can toggle a report like without affecting another users like', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $report = interactionReport();
    ReportLike::create(['report_id' => $report->id, 'user_id' => $otherUser->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/reports/{$report->id}/like")
        ->assertOk()
        ->assertExactJson([
            'liked' => true,
            'likes_count' => 2,
        ]);

    expect(ReportLike::query()->where('report_id', $report->id)->where('user_id', $user->id)->count())->toBe(1)
        ->and(ReportLike::query()->where('report_id', $report->id)->where('user_id', $otherUser->id)->exists())->toBeTrue();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/reports/{$report->id}/like")
        ->assertOk()
        ->assertExactJson([
            'liked' => false,
            'likes_count' => 1,
        ]);

    expect(ReportLike::query()->where('report_id', $report->id)->where('user_id', $user->id)->count())->toBe(0)
        ->and(ReportLike::query()->where('report_id', $report->id)->where('user_id', $otherUser->id)->exists())->toBeTrue();
});

test('the report feed reflects a like created through the interaction endpoint', function () {
    $user = User::factory()->create();
    $report = interactionReport();

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/reports/{$report->id}/like")->assertOk();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/reports')
        ->assertOk()
        ->assertJsonPath('data.0.id', $report->id)
        ->assertJsonPath('data.0.likes_count', 1)
        ->assertJsonPath('data.0.is_liked', true);
});

test('liking a missing report returns the normal not found response', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/reports/999999/like')
        ->assertNotFound();
});

test('authenticated users receive mobile safe comments newest first', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $report = interactionReport();
    $older = ReportComment::create([
        'report_id' => $report->id,
        'user_id' => $author->id,
        'comment' => 'Older comment',
    ]);
    $older->forceFill(['created_at' => now()->subHour()])->saveQuietly();
    $newer = ReportComment::create([
        'report_id' => $report->id,
        'user_id' => $author->id,
        'comment' => 'Newer comment',
    ]);

    $this->actingAs($viewer, 'sanctum')
        ->getJson("/api/v1/reports/{$report->id}/comments")
        ->assertOk()
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('data.0.author', $author->name)
        ->assertJsonPath('data.0.author_initial', $author->getAvatarInitial())
        ->assertJsonPath('data.0.comment', 'Newer comment')
        ->assertJsonStructure(['data' => [['id', 'author', 'author_initial', 'profile_photo_url', 'comment', 'timestamp', 'created_at']]])
        ->assertJsonMissingPath('data.0.email')
        ->assertJsonMissingPath('data.0.phone')
        ->assertJsonMissingPath('data.0.is_admin');
});

test('an authenticated user can create a comment as themselves', function () {
    $user = User::factory()->create();
    $spoofedUser = User::factory()->create();
    $report = interactionReport();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/reports/{$report->id}/comment", [
            'comment' => 'Collection is still needed here.',
            'user_id' => $spoofedUser->id,
        ])->assertCreated()
        ->assertJsonPath('comment.author', $user->name)
        ->assertJsonPath('comment.comment', 'Collection is still needed here.')
        ->assertJsonPath('comments_count', 1)
        ->assertJsonMissingPath('comment.email')
        ->assertJsonMissingPath('comment.phone')
        ->assertJsonMissingPath('comment.is_admin');

    $comment = ReportComment::query()->sole();
    expect($comment->user_id)->toBe($user->id)
        ->and($comment->report_id)->toBe($report->id);
});

test('comment validation matches the existing web rules', function () {
    $user = User::factory()->create();
    $report = interactionReport();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/reports/{$report->id}/comment", ['comment' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('comment');

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/reports/{$report->id}/comment", ['comment' => str_repeat('a', 501)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('comment');

    expect(ReportComment::query()->count())->toBe(0);
});

test('comment endpoints return the normal not found response for a missing report', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/reports/999999/comments')
        ->assertNotFound();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/reports/999999/comment', ['comment' => 'Missing report'])
        ->assertNotFound();
});
