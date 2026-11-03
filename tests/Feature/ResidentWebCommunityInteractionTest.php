<?php

use App\Models\ModerationAction;
use App\Models\Report;
use App\Models\User;
use App\Models\UserReport;

function reportForResidentWebInteraction(User $author): Report
{
    return Report::query()->create([
        'user_id' => $author->id,
        'barangay' => 'Barangay Washington',
        'location' => 'Community interaction test location',
        'description' => 'Community Report used for resident web interaction tests.',
        'status' => 'pending',
        'priority' => 'medium',
    ]);
}

function moderationActionForResidentWebInteraction(User $resident, string $type, array $attributes = []): ModerationAction
{
    return ModerationAction::query()->create(array_merge([
        'user_id' => $resident->id,
        'action_type' => $type,
        'applied_by' => User::factory()->create(['is_admin' => true])->id,
        'starts_at' => now()->subDay(),
    ], $attributes));
}

test('Home retains its existing working like and comment interactions', function () {
    $resident = User::factory()->create();
    $report = reportForResidentWebInteraction(User::factory()->create());

    $this->actingAs($resident)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('like-button', false)
        ->assertSee('comment-form', false)
        ->assertSee('/reports/${reportId}/like', false)
        ->assertSee('/reports/${reportId}/comment', false);

    $this->postJson(route('reports.like', $report))
        ->assertOk()
        ->assertExactJson(['liked' => true, 'likes_count' => 1]);
    $this->postJson(route('reports.comment', $report), ['comment' => 'Home regression comment.'])
        ->assertOk()
        ->assertJsonPath('comment.comment', 'Home regression comment.')
        ->assertJsonPath('comments_count', 1);
});

test('normal resident can like unlike comment follow and unfollow with frontend-compatible JSON', function () {
    $resident = User::factory()->create();
    $report = reportForResidentWebInteraction(User::factory()->create());

    expect($resident->activeCommunityRestriction())->toBeNull();

    $this->actingAs($resident)
        ->postJson(route('reports.like', $report))
        ->assertOk()
        ->assertExactJson(['liked' => true, 'likes_count' => 1]);
    $this->assertDatabaseHas('report_likes', ['report_id' => $report->id, 'user_id' => $resident->id]);

    $this->postJson(route('reports.like', $report))
        ->assertOk()
        ->assertExactJson(['liked' => false, 'likes_count' => 0]);
    $this->assertDatabaseMissing('report_likes', ['report_id' => $report->id, 'user_id' => $resident->id]);

    $this->postJson(route('reports.comment', $report), ['comment' => 'A valid resident comment.'])
        ->assertOk()
        ->assertJsonPath('comment.author', $resident->name)
        ->assertJsonPath('comment.comment', 'A valid resident comment.')
        ->assertJsonPath('comments_count', 1);
    $this->assertDatabaseHas('report_comments', [
        'report_id' => $report->id,
        'user_id' => $resident->id,
        'comment' => 'A valid resident comment.',
    ]);

    $this->postJson(route('reports.follow', $report))
        ->assertOk()
        ->assertExactJson(['following' => true, 'followers_count' => 1]);
    $this->assertDatabaseHas('report_followers', ['report_id' => $report->id, 'user_id' => $resident->id]);

    $this->postJson(route('reports.follow', $report))
        ->assertOk()
        ->assertExactJson(['following' => false, 'followers_count' => 0]);
    $this->assertDatabaseMissing('report_followers', ['report_id' => $report->id, 'user_id' => $resident->id]);
});

test('like and comment requests update only the selected report', function () {
    $resident = User::factory()->create();
    $reportA = reportForResidentWebInteraction(User::factory()->create());
    $reportB = reportForResidentWebInteraction(User::factory()->create());

    $this->actingAs($resident)->postJson(route('reports.like', $reportA))
        ->assertOk()->assertJsonPath('likes_count', 1);
    $this->postJson(route('reports.comment', $reportA), ['comment' => 'Comment for Report A'])
        ->assertOk()->assertJsonPath('comments_count', 1);

    $this->postJson(route('reports.like', $reportB))
        ->assertOk()->assertJsonPath('likes_count', 1);
    $this->postJson(route('reports.comment', $reportB), ['comment' => 'Comment for Report B'])
        ->assertOk()->assertJsonPath('comments_count', 1);

    $this->assertDatabaseHas('report_likes', ['report_id' => $reportA->id, 'user_id' => $resident->id]);
    $this->assertDatabaseHas('report_likes', ['report_id' => $reportB->id, 'user_id' => $resident->id]);
    $this->assertDatabaseHas('report_comments', ['report_id' => $reportA->id, 'comment' => 'Comment for Report A']);
    $this->assertDatabaseHas('report_comments', ['report_id' => $reportB->id, 'comment' => 'Comment for Report B']);
    expect($reportA->comments()->where('comment', 'Comment for Report B')->exists())->toBeFalse()
        ->and($reportB->comments()->where('comment', 'Comment for Report A')->exists())->toBeFalse();
});

test('inactive moderation history does not block resident web likes or comments', function (string $state) {
    $resident = User::factory()->create();
    $report = reportForResidentWebInteraction(User::factory()->create());
    $admin = User::factory()->create(['is_admin' => true]);

    if ($state === 'warning') {
        moderationActionForResidentWebInteraction($resident, ModerationAction::WARNING);
    } elseif ($state === 'expired') {
        moderationActionForResidentWebInteraction($resident, ModerationAction::COMMUNITY_RESTRICTION, [
            'expires_at' => now()->subMinute(),
        ]);
    } elseif ($state === 'lifted') {
        $restriction = moderationActionForResidentWebInteraction($resident, ModerationAction::COMMUNITY_RESTRICTION, [
            'expires_at' => now()->addDay(),
            'lifted_at' => now()->subMinute(),
            'lifted_by' => $admin->id,
        ]);
        moderationActionForResidentWebInteraction($resident, ModerationAction::RESTRICTION_LIFTED, [
            'related_action_id' => $restriction->id,
        ]);
    } elseif ($state === 'dismissed report') {
        UserReport::query()->create([
            'reporter_id' => User::factory()->create()->id,
            'reported_user_id' => $resident->id,
            'reason' => 'harassment',
            'description' => 'Dismissed report history.',
            'status' => 'dismissed',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
    }

    expect($resident->activeCommunityRestriction())->toBeNull();

    $this->actingAs($resident)->postJson(route('reports.like', $report))
        ->assertOk()->assertJsonPath('liked', true);
    $this->postJson(route('reports.comment', $report), ['comment' => "Allowed with {$state} history."])
        ->assertOk()->assertJsonPath('comments_count', 1);
})->with(['warning', 'expired', 'lifted', 'dismissed report']);

test('active community restriction blocks like unlike comment and follow state changes', function () {
    $resident = User::factory()->create();
    $report = reportForResidentWebInteraction(User::factory()->create());
    $report->likes()->create(['user_id' => $resident->id]);
    $report->followers()->attach($resident->id);
    $restriction = moderationActionForResidentWebInteraction($resident, ModerationAction::COMMUNITY_RESTRICTION, [
        'expires_at' => now()->addDays(3),
    ]);
    $message = 'Your Community Reports access is temporarily read-only until '.$restriction->expires_at->format('M j, Y g:i A').'. You can still view reports and use other Cleanify services.';

    expect($resident->activeCommunityRestriction())->not->toBeNull();

    $this->actingAs($resident)->getJson(route('reports.show', $report))
        ->assertOk()
        ->assertJsonPath('id', $report->id);
    $this->actingAs($resident)->postJson(route('reports.like', $report))
        ->assertForbidden()->assertJsonPath('message', $message);
    $this->postJson(route('reports.comment', $report), ['comment' => 'Blocked'])
        ->assertForbidden()->assertJsonPath('message', $message);
    $this->postJson(route('reports.follow', $report))
        ->assertForbidden()->assertJsonPath('message', $message);

    expect($report->likes()->where('user_id', $resident->id)->exists())->toBeTrue()
        ->and($report->followers()->whereKey($resident->id)->exists())->toBeTrue()
        ->and($report->comments()->where('user_id', $resident->id)->exists())->toBeFalse();
});

test('Community Report cards expose direct interaction counts and the current resident like state', function () {
    $resident = User::factory()->create();
    $report = reportForResidentWebInteraction(User::factory()->create());
    $report->likes()->create(['user_id' => $resident->id]);
    $report->comments()->createMany([
        ['user_id' => $resident->id, 'comment' => 'First card count comment.'],
        ['user_id' => User::factory()->create()->id, 'comment' => 'Second card count comment.'],
    ]);

    $this->actingAs($resident)
        ->get(route('community-reports'))
        ->assertOk()
        ->assertSee('community-card-like-btn', false)
        ->assertSee('community-card-like-btn resident-home-action like-button text-green-600', false)
        ->assertSee('community-card-like-label">Liked', false)
        ->assertSee('community-card-like-count">(1)', false)
        ->assertDontSee('community-card-like-label">Unlike', false)
        ->assertSee('community-card-comments-btn', false)
        ->assertSee('community-card-comments-btn view-report-btn resident-home-action hover:!bg-gray-100 hover:!text-gray-700', false)
        ->assertSee('community-card-comment-count">2', false)
        ->assertSee('aria-pressed="true"', false);
});

test('Community Reports page renders working like and comment controls with JSON request handling', function () {
    $resident = User::factory()->create();
    $report = reportForResidentWebInteraction(User::factory()->create());

    $this->actingAs($resident)
        ->get(route('community-reports'))
        ->assertOk()
        ->assertSee('id="detailLikeBtn"', false)
        ->assertSee('id="detailLikeBtn" type="button" class="resident-home-action like-button hover:text-green-600"', false)
        ->assertSee('id="detailLikeLabel">Like', false)
        ->assertSee('id="detailLikeCount" class="like-count">(0)', false)
        ->assertSee('id="detailCommentForm"', false)
        ->assertSee('id="detailCommentInput"', false)
        ->assertSee('community-card-like-label">Like', false)
        ->assertSee('community-card-like-count">(0)', false)
        ->assertSee('community-card-comments-btn view-report-btn resident-home-action hover:!bg-gray-100 hover:!text-gray-700', false)
        ->assertSee(route('reports.like', '__ID__'), false)
        ->assertSee(route('reports.comment', '__ID__'), false)
        ->assertSee("'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]'", false)
        ->assertSee("'Accept': 'application/json'", false)
        ->assertSee('if (!response.ok)', false)
        ->assertSee('activeReportId = data.id', false)
        ->assertSee("replace('__ID__', activeReportId)", false)
        ->assertSee("isLiked ? 'Liked' : 'Like'", false)
        ->assertDontSee("isLiked ? 'Unlike' : 'Like'", false)
        ->assertSee('synchronizeLikeState(reportId, data.liked, data.likes_count)', false)
        ->assertSee('updateCardCommentCount(activeReportId, data.comments_count)', false)
        ->assertSee('event.stopPropagation()', false)
        ->assertSee("showResidentAlert('error', message)", false);

    $this->getJson(route('reports.show', $report))
        ->assertOk()
        ->assertJsonPath('is_liked', false);

    $report->likes()->create(['user_id' => $resident->id]);
    $this->getJson(route('reports.show', $report))
        ->assertOk()
        ->assertJsonPath('is_liked', true);
});
