<?php

use App\Models\ModerationAction;
use App\Models\Report;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Support\Facades\Notification;

function reportForCommunityReadOnlyTest(User $author, string $description = 'Existing Community Report'): Report
{
    return Report::query()->create([
        'user_id' => $author->id,
        'barangay' => 'Barangay Washington',
        'location' => 'Covered court',
        'description' => $description,
        'status' => 'pending',
        'priority' => 'medium',
    ]);
}

function communityRestrictionForReadOnlyTest(User $resident, ?User $admin = null): ModerationAction
{
    return ModerationAction::query()->create([
        'user_id' => $resident->id,
        'action_type' => ModerationAction::COMMUNITY_RESTRICTION,
        'applied_by' => ($admin ?? User::factory()->create(['is_admin' => true]))->id,
        'starts_at' => now(),
        'expires_at' => now()->addDays(3),
    ]);
}

function validCommunityReportPayload(string $description): array
{
    return [
        'barangay' => 'Barangay Washington',
        'location' => 'Public market',
        'description' => $description,
    ];
}

test('community restricted residents retain web and API read access', function () {
    $resident = User::factory()->create();
    $report = reportForCommunityReadOnlyTest($resident);
    communityRestrictionForReadOnlyTest($resident);

    $this->actingAs($resident)->get(route('dashboard'))->assertOk();
    $this->get(route('community-reports'))->assertOk();
    $this->get(route('reports.show', $report))->assertOk();
    $this->get(route('profile'))->assertOk()->assertSee($report->description);
    $this->get(route('garbage-schedule'))->assertOk();
    $this->get(route('tracker'))->assertOk();
    $this->get(route('notifications'))->assertOk();
    $this->get(route('settings'))->assertOk();

    $this->actingAs($resident, 'sanctum')->getJson('/api/v1/reports')->assertOk();
    $this->getJson('/api/v1/reports/home')->assertOk();
    $this->getJson("/api/v1/reports/{$report->id}")->assertOk();
    $this->getJson('/api/v1/me/reports')->assertOk();
    $this->getJson('/api/v1/schedules')->assertOk();
    $this->getJson('/api/v1/trucks')->assertOk();
    $this->getJson('/api/v1/notifications')->assertOk();
    $this->getJson('/api/v1/settings')->assertOk();
});

test('community restriction blocks every Community Reports write except deleting an owned report', function () {
    $resident = User::factory()->create();
    $other = User::factory()->create();
    $emptyStateReport = reportForCommunityReadOnlyTest($other, 'No existing resident interaction');
    $existingStateReport = reportForCommunityReadOnlyTest($other, 'Existing resident interaction');
    $ownedReport = reportForCommunityReadOnlyTest($resident, 'Resident may delete this report');
    $existingStateReport->likes()->create(['user_id' => $resident->id]);
    $existingStateReport->followers()->attach($resident->id);
    $restriction = communityRestrictionForReadOnlyTest($resident);
    $readOnlyMessage = 'Your Community Reports access is temporarily read-only until '.$restriction->expires_at->format('M j, Y g:i A').'. You can still view reports and use other Cleanify services.';

    $this->actingAs($resident)
        ->from(route('dashboard'))
        ->post(route('reports.store'), validCommunityReportPayload('Blocked web report'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', $readOnlyMessage);

    $this->postJson(route('reports.comment', $emptyStateReport), ['comment' => 'Blocked comment'])
        ->assertForbidden()->assertJsonPath('message', $readOnlyMessage);
    $this->postJson(route('reports.like', $emptyStateReport))
        ->assertForbidden()->assertJsonPath('message', $readOnlyMessage);
    $this->postJson(route('reports.like', $existingStateReport))
        ->assertForbidden()->assertJsonPath('message', $readOnlyMessage);
    $this->postJson(route('reports.follow', $emptyStateReport))
        ->assertForbidden()->assertJsonPath('message', $readOnlyMessage);
    $this->postJson(route('reports.follow', $existingStateReport))
        ->assertForbidden()->assertJsonPath('message', $readOnlyMessage);
    $this->patchJson(route('profile.reports.update', $ownedReport), validCommunityReportPayload('Blocked edit'))
        ->assertForbidden()->assertJsonPath('message', $readOnlyMessage);

    expect($existingStateReport->likes()->where('user_id', $resident->id)->exists())->toBeTrue()
        ->and($existingStateReport->followers()->whereKey($resident->id)->exists())->toBeTrue()
        ->and($emptyStateReport->likes()->where('user_id', $resident->id)->exists())->toBeFalse()
        ->and($emptyStateReport->followers()->whereKey($resident->id)->exists())->toBeFalse();

    $this->deleteJson(route('profile.reports.delete', $ownedReport))
        ->assertOk()
        ->assertJsonPath('message', 'Report deleted successfully');
    $this->assertDatabaseMissing('reports', ['id' => $ownedReport->id]);

    $this->actingAs($resident, 'sanctum')
        ->postJson('/api/v1/reports', validCommunityReportPayload('Blocked API report'))
        ->assertForbidden()
        ->assertJsonPath('message', $readOnlyMessage);
    $this->postJson("/api/v1/reports/{$emptyStateReport->id}/comment", ['comment' => 'Blocked API comment'])
        ->assertForbidden()->assertJsonPath('message', $readOnlyMessage);
    $this->postJson("/api/v1/reports/{$emptyStateReport->id}/like")
        ->assertForbidden()->assertJsonPath('message', $readOnlyMessage);

    $this->assertDatabaseCount('reports', 2);
});

test('expiration restores report creation comments likes and follows', function () {
    $resident = User::factory()->create();
    $report = reportForCommunityReadOnlyTest(User::factory()->create());
    $restriction = communityRestrictionForReadOnlyTest($resident);
    $restriction->update(['expires_at' => now()->subMinute()]);

    $this->actingAs($resident)
        ->post(route('reports.store'), validCommunityReportPayload('Allowed after expiration'))
        ->assertRedirect(route('dashboard'));
    $this->postJson(route('reports.comment', $report), ['comment' => 'Allowed after expiration'])->assertOk();
    $this->postJson(route('reports.like', $report))->assertOk()->assertJsonPath('liked', true);
    $this->postJson(route('reports.follow', $report))->assertOk()->assertJsonPath('following', true);

    expect($resident->activeCommunityRestriction())->toBeNull();
});

test('lifting community restriction immediately restores all Community Reports writes and preserves history', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $resident = User::factory()->create();
    $report = reportForCommunityReadOnlyTest(User::factory()->create());
    $restriction = communityRestrictionForReadOnlyTest($resident, $admin);

    $this->actingAs($admin)
        ->post(route('admin.moderation-actions.lift', $restriction))
        ->assertRedirect()
        ->assertSessionHas('success', 'Restriction lifted successfully.');

    $this->actingAs($resident)
        ->post(route('reports.store'), validCommunityReportPayload('Allowed after lift'))
        ->assertRedirect(route('dashboard'));
    $this->postJson(route('reports.comment', $report), ['comment' => 'Allowed after lift'])->assertOk();
    $this->postJson(route('reports.like', $report))->assertOk()->assertJsonPath('liked', true);
    $this->postJson(route('reports.follow', $report))->assertOk()->assertJsonPath('following', true);

    expect($restriction->fresh()->lifted_at)->not->toBeNull()
        ->and(ModerationAction::query()->where('related_action_id', $restriction->id)->where('action_type', ModerationAction::RESTRICTION_LIFTED)->exists())->toBeTrue()
        ->and(ModerationAction::query()->count())->toBe(2);
});

test('legacy report submission restrictions keep their original narrow behavior', function () {
    $resident = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $report = reportForCommunityReadOnlyTest(User::factory()->create());
    ModerationAction::query()->create([
        'user_id' => $resident->id,
        'action_type' => ModerationAction::REPORT_SUBMISSION_RESTRICTION,
        'applied_by' => $admin->id,
        'starts_at' => now(),
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($resident)
        ->from(route('dashboard'))
        ->post(route('reports.store'), validCommunityReportPayload('Blocked by legacy restriction'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error');
    $this->postJson(route('reports.comment', $report), ['comment' => 'Still allowed'])->assertOk();
    $this->postJson(route('reports.like', $report))->assertOk()->assertJsonPath('liked', true);
    $this->postJson(route('reports.follow', $report))->assertOk()->assertJsonPath('following', true);

    $this->actingAs($resident, 'sanctum')
        ->postJson('/api/v1/reports', validCommunityReportPayload('Blocked API legacy report'))
        ->assertForbidden()
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'New cleanliness report submission'));
});

test('new Admin moderation choices omit the legacy report submission restriction', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    UserReport::query()->create([
        'reporter_id' => User::factory()->create()->id,
        'reported_user_id' => User::factory()->create()->id,
        'reason' => 'harassment',
        'description' => 'Admin choice test.',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.user-reports'))
        ->assertOk()
        ->assertSee('Community Restriction')
        ->assertDontSee('>Report Submission Restriction</option>', false)
        ->assertSee('Temporarily restrict this resident from posting or interacting in Community Reports. They can still view reports and access other Cleanify services.');
});
