<?php

namespace App\Services;

use App\Models\ModerationAction;
use App\Models\User;
use App\Models\UserReport;
use App\Notifications\ModerationActionNotification;
use App\Notifications\UserReportReviewedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ModerationService
{
    public function recommendedAction(User $user): array
    {
        return match (true) {
            $user->confirmedViolationCount() === 0 => [
                'action' => ModerationAction::WARNING,
                'label' => 'Warning',
                'days' => null,
            ],
            $user->confirmedViolationCount() === 1 => [
                'action' => ModerationAction::COMMUNITY_RESTRICTION,
                'label' => '3 Day Community Restriction',
                'days' => 3,
            ],
            default => [
                'action' => ModerationAction::COMMUNITY_RESTRICTION,
                'label' => '7 Day Community Restriction',
                'days' => 7,
            ],
        };
    }

    public function dismiss(UserReport $userReport, User $administrator, ?string $adminNotes): UserReport
    {
        $userReport = DB::transaction(function () use ($userReport, $administrator, $adminNotes): UserReport {
            $locked = UserReport::query()->lockForUpdate()->findOrFail($userReport->id);
            $this->ensurePending($locked);
            $locked->update([
                'status' => 'dismissed',
                'admin_notes' => $adminNotes,
                'reviewed_by' => $administrator->id,
                'reviewed_at' => now(),
            ]);

            return $locked->fresh(['reporter', 'reportedUser', 'reviewer']);
        });

        $this->notifyReporter($userReport);

        return $userReport;
    }

    public function apply(
        UserReport $userReport,
        User $administrator,
        string $actionType,
        ?string $residentReason,
        ?string $adminNotes,
        $expiresAt = null
    ): ModerationAction {
        $action = DB::transaction(function () use (
            $userReport,
            $administrator,
            $actionType,
            $residentReason,
            $adminNotes,
            $expiresAt
        ): ModerationAction {
            $locked = UserReport::query()->lockForUpdate()->findOrFail($userReport->id);
            $this->ensurePending($locked);

            $action = ModerationAction::query()->create([
                'user_id' => $locked->reported_user_id,
                'user_report_id' => $locked->id,
                'action_type' => $actionType,
                'resident_reason' => $residentReason,
                'admin_notes' => $adminNotes,
                'applied_by' => $administrator->id,
                'starts_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            $locked->update([
                'status' => 'action_taken',
                'admin_notes' => $adminNotes,
                'reviewed_by' => $administrator->id,
                'reviewed_at' => now(),
            ]);

            if ($actionType === ModerationAction::SUSPENSION) {
                $locked->reportedUser()->update(['banned_at' => now()]);
                $locked->reportedUser?->tokens()->delete();
            }

            return $action->fresh(['user', 'userReport.reporter', 'administrator']);
        });

        $this->notifyAffectedResident($action);
        $this->notifyReporter($action->userReport->fresh(['reporter', 'reportedUser', 'reviewer']));

        return $action;
    }

    public function lift(ModerationAction $action, User $administrator, ?string $adminNotes): ModerationAction
    {
        return DB::transaction(function () use ($action, $administrator, $adminNotes): ModerationAction {
            $locked = ModerationAction::query()->lockForUpdate()->findOrFail($action->id);

            if ($locked->lifted_at || ! in_array($locked->action_type, [
                ModerationAction::COMMUNITY_RESTRICTION,
                ModerationAction::REPORT_SUBMISSION_RESTRICTION,
                ModerationAction::SUSPENSION,
            ], true)) {
                throw ValidationException::withMessages([
                    'action' => 'This moderation action cannot be lifted.',
                ]);
            }

            $locked->update([
                'lifted_at' => now(),
                'lifted_by' => $administrator->id,
            ]);

            $liftType = $locked->action_type === ModerationAction::SUSPENSION
                ? ModerationAction::UNSUSPENDED
                : ModerationAction::RESTRICTION_LIFTED;

            if ($liftType === ModerationAction::UNSUSPENDED) {
                $locked->user()->update(['banned_at' => null]);
            }

            return ModerationAction::query()->create([
                'user_id' => $locked->user_id,
                'related_action_id' => $locked->id,
                'action_type' => $liftType,
                'admin_notes' => $adminNotes,
                'applied_by' => $administrator->id,
                'starts_at' => now(),
            ]);
        });
    }

    public function suspendUser(User $user, User $administrator): ModerationAction
    {
        $action = DB::transaction(function () use ($user, $administrator): ModerationAction {
            $user->update(['banned_at' => now()]);
            $user->tokens()->delete();

            return ModerationAction::query()->create([
                'user_id' => $user->id,
                'action_type' => ModerationAction::SUSPENSION,
                'applied_by' => $administrator->id,
                'starts_at' => now(),
            ])->load(['user', 'administrator']);
        });

        $this->notifyAffectedResident($action);

        return $action;
    }

    public function unsuspendUser(User $user, User $administrator): ModerationAction
    {
        $suspension = $user->moderationActions()
            ->where('action_type', ModerationAction::SUSPENSION)
            ->whereNull('lifted_at')
            ->latest('starts_at')
            ->first();

        if ($suspension) {
            return $this->lift($suspension, $administrator, null);
        }

        $user->update(['banned_at' => null]);

        return ModerationAction::query()->create([
            'user_id' => $user->id,
            'action_type' => ModerationAction::UNSUSPENDED,
            'applied_by' => $administrator->id,
            'starts_at' => now(),
        ]);
    }

    private function ensurePending(UserReport $userReport): void
    {
        if ($userReport->status !== 'pending') {
            throw ValidationException::withMessages([
                'decision' => 'This user report has already received a final review decision.',
            ]);
        }
    }

    private function notifyAffectedResident(ModerationAction $action): void
    {
        try {
            $action->user?->notify(new ModerationActionNotification($action));
        } catch (\Throwable $exception) {
            Log::error('Failed to notify resident about moderation action.', [
                'moderation_action_id' => $action->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function notifyReporter(UserReport $userReport): void
    {
        try {
            $userReport->reporter?->notify(new UserReportReviewedNotification($userReport));
        } catch (\Throwable $exception) {
            Log::error('Failed to notify reporter about user report review.', [
                'user_report_id' => $userReport->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
