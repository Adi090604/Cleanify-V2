<?php

use App\Models\Report;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

function duplicateCommunityReport(User $owner, string $description): Report
{
    return Report::query()->create([
        'user_id' => $owner->id,
        'location' => 'Duplicate policy test location',
        'description' => $description,
        'status' => 'pending',
        'priority' => 'medium',
    ]);
}

test('database replaces the old user-level unique index with exact-post uniqueness', function () {
    $indexNames = collect(DB::select("PRAGMA index_list('user_reports')"))->pluck('name');

    expect($indexNames)->toContain('user_reports_reporter_id_report_id_unique')
        ->and($indexNames)->not->toContain('user_reports_reporter_id_reported_user_id_unique');

    $reporter = User::factory()->create();
    $otherReporter = User::factory()->create();
    $author = User::factory()->create();
    $firstPost = duplicateCommunityReport($author, 'First post');
    $secondPost = duplicateCommunityReport($author, 'Second post');

    UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $author->id,
        'report_id' => $firstPost->id,
        'reason' => 'spam',
    ]);
    UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $author->id,
        'report_id' => $secondPost->id,
        'reason' => 'harassment',
    ]);
    UserReport::query()->create([
        'reporter_id' => $otherReporter->id,
        'reported_user_id' => $author->id,
        'report_id' => $firstPost->id,
        'reason' => 'other',
    ]);
    UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $author->id,
        'report_id' => null,
        'reason' => 'other',
    ]);
    UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $author->id,
        'report_id' => null,
        'reason' => 'other',
    ]);

    expect(UserReport::query()->count())->toBe(5);

    expect(fn () => UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $author->id,
        'report_id' => $firstPost->id,
        'reason' => 'fake_account',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('web allows different posts by the same author and blocks only the same active post', function () {
    $reporter = User::factory()->create();
    $author = User::factory()->create();
    $firstPost = duplicateCommunityReport($author, 'Web first post');
    $secondPost = duplicateCommunityReport($author, 'Web second post');

    foreach ([$firstPost, $secondPost] as $post) {
        $this->actingAs($reporter)->postJson(route('users.report', $author), [
            'reason' => 'inappropriate_content',
            'report_id' => $post->id,
        ])->assertOk();
    }

    expect(UserReport::query()->where('reporter_id', $reporter->id)->count())->toBe(2)
        ->and(UserReport::query()->pluck('report_id')->all())->toEqualCanonicalizing([$firstPost->id, $secondPost->id]);

    $this->actingAs($reporter)->postJson(route('users.report', $author), [
        'reason' => 'spam',
        'report_id' => $firstPost->id,
    ])->assertUnprocessable()
        ->assertExactJson([
            'error' => 'You have already reported this Community Report. Please wait for admin review.',
            'status' => 'pending',
        ]);

    expect(UserReport::query()->count())->toBe(2);
});

test('API supports same-author different posts different authors and different reporters', function () {
    $reporter = User::factory()->create();
    $otherReporter = User::factory()->create();
    $firstAuthor = User::factory()->create();
    $secondAuthor = User::factory()->create();
    $firstPost = duplicateCommunityReport($firstAuthor, 'API first post');
    $secondPost = duplicateCommunityReport($firstAuthor, 'API second post');
    $otherAuthorPost = duplicateCommunityReport($secondAuthor, 'Other author post');

    foreach ([[$firstAuthor, $firstPost], [$firstAuthor, $secondPost], [$secondAuthor, $otherAuthorPost]] as [$author, $post]) {
        $this->actingAs($reporter, 'sanctum')->postJson("/api/v1/users/{$author->id}/report", [
            'reason' => 'spam',
            'report_id' => $post->id,
        ])->assertOk();
    }

    $this->actingAs($otherReporter, 'sanctum')->postJson("/api/v1/users/{$firstAuthor->id}/report", [
        'reason' => 'harassment',
        'report_id' => $firstPost->id,
    ])->assertOk();

    $this->actingAs($reporter, 'sanctum')->postJson("/api/v1/users/{$firstAuthor->id}/report", [
        'reason' => 'other',
        'report_id' => $firstPost->id,
    ])->assertUnprocessable()
        ->assertExactJson([
            'message' => 'You have already reported this Community Report. Please wait for admin review.',
            'status' => 'pending',
        ]);

    expect(UserReport::query()->count())->toBe(4);
});

test('same-post dismissed resubmission resets one row while a different post creates another', function () {
    $reporter = User::factory()->create();
    $author = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $dismissedPost = duplicateCommunityReport($author, 'Dismissed post');
    $differentPost = duplicateCommunityReport($author, 'Different post');
    $dismissed = UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $author->id,
        'report_id' => $dismissedPost->id,
        'reason' => 'spam',
        'status' => 'dismissed',
        'admin_notes' => 'Old notes',
        'reviewed_by' => $admin->id,
        'reviewed_at' => now()->subDay(),
    ]);

    $this->actingAs($reporter, 'sanctum')->postJson("/api/v1/users/{$author->id}/report", [
        'reason' => 'harassment',
        'description' => 'Same post again.',
        'report_id' => $dismissedPost->id,
    ])->assertOk();

    $reset = $dismissed->fresh();
    expect(UserReport::query()->count())->toBe(1)
        ->and($reset->status)->toBe('pending')
        ->and($reset->reason)->toBe('harassment')
        ->and($reset->admin_notes)->toBeNull()
        ->and($reset->reviewed_by)->toBeNull()
        ->and($reset->reviewed_at)->toBeNull();

    $reset->update(['status' => 'dismissed']);

    $this->actingAs($reporter, 'sanctum')->postJson("/api/v1/users/{$author->id}/report", [
        'reason' => 'inappropriate_content',
        'report_id' => $differentPost->id,
    ])->assertOk();

    expect(UserReport::query()->count())->toBe(2)
        ->and(UserReport::query()->where('report_id', $dismissedPost->id)->value('status'))->toBe('dismissed')
        ->and(UserReport::query()->where('report_id', $differentPost->id)->value('status'))->toBe('pending');
});

test('legacy no-evidence duplicate and dismissed reuse behavior remains user-level', function () {
    $activeReporter = User::factory()->create();
    $dismissedReporter = User::factory()->create();
    $author = User::factory()->create();
    UserReport::query()->create([
        'reporter_id' => $activeReporter->id,
        'reported_user_id' => $author->id,
        'report_id' => null,
        'reason' => 'spam',
        'status' => 'reviewed',
    ]);
    $dismissed = UserReport::query()->create([
        'reporter_id' => $dismissedReporter->id,
        'reported_user_id' => $author->id,
        'report_id' => null,
        'reason' => 'spam',
        'status' => 'dismissed',
        'admin_notes' => 'Dismissed legacy report',
        'reviewed_at' => now()->subDay(),
    ]);

    $this->actingAs($activeReporter, 'sanctum')->postJson("/api/v1/users/{$author->id}/report", [
        'reason' => 'other',
    ])->assertUnprocessable()
        ->assertExactJson([
            'message' => 'You have already reported this user. Please wait for admin review.',
            'status' => 'reviewed',
        ]);

    $this->actingAs($dismissedReporter, 'sanctum')->postJson("/api/v1/users/{$author->id}/report", [
        'reason' => 'fake_account',
    ])->assertOk();

    expect($dismissed->fresh()->status)->toBe('pending')
        ->and($dismissed->fresh()->reason)->toBe('fake_account')
        ->and($dismissed->fresh()->admin_notes)->toBeNull()
        ->and($dismissed->fresh()->reviewed_at)->toBeNull()
        ->and(UserReport::query()->where('reporter_id', $dismissedReporter->id)->count())->toBe(1);
});

test('legacy or deleted null evidence does not block a new exact post report', function () {
    $reporter = User::factory()->create();
    $author = User::factory()->create();
    UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $author->id,
        'report_id' => null,
        'reason' => 'other',
        'status' => 'pending',
    ]);
    $newPost = duplicateCommunityReport($author, 'New post after legacy history');

    $this->actingAs($reporter, 'sanctum')->postJson("/api/v1/users/{$author->id}/report", [
        'reason' => 'spam',
        'report_id' => $newPost->id,
    ])->assertOk();

    expect(UserReport::query()->count())->toBe(2)
        ->and(UserReport::query()->where('report_id', $newPost->id)->exists())->toBeTrue();
});

test('admin lists separate evidence and deletion nulls only the matching record', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $reporter = User::factory()->create();
    $author = User::factory()->create();
    $firstPost = duplicateCommunityReport($author, 'Admin evidence one');
    $secondPost = duplicateCommunityReport($author, 'Admin evidence two');
    $firstUserReport = UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $author->id,
        'report_id' => $firstPost->id,
        'reason' => 'spam',
    ]);
    $secondUserReport = UserReport::query()->create([
        'reporter_id' => $reporter->id,
        'reported_user_id' => $author->id,
        'report_id' => $secondPost->id,
        'reason' => 'harassment',
    ]);

    $this->actingAs($admin)->get(route('admin.user-reports'))
        ->assertOk()
        ->assertSee('Admin evidence one')
        ->assertSee('Admin evidence two')
        ->assertViewHas('reports', fn ($reports) => $reports->count() === 2);

    $this->actingAs($admin)
        ->delete(route('admin.user-reports.report.destroy', $firstUserReport))
        ->assertRedirect();

    expect($firstUserReport->fresh()->report_id)->toBeNull()
        ->and($secondUserReport->fresh()->report_id)->toBe($secondPost->id)
        ->and($secondPost->fresh())->not->toBeNull();
});
