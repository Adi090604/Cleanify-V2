<?php

namespace App\Services;

use App\Exceptions\DuplicateUserReportException;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class UserReportSubmissionService
{
    /**
     * @param  array{reason: string, description?: ?string, report_id?: ?int}  $attributes
     */
    public function submit(User $reporter, User $reportedUser, array $attributes): UserReport
    {
        $reportId = $attributes['report_id'] ?? null;

        try {
            return DB::transaction(function () use ($reporter, $reportedUser, $attributes, $reportId): UserReport {
                // Serialize this reporter's submissions so legacy NULL evidence keeps
                // its application-level duplicate guarantee under concurrent requests.
                User::query()->whereKey($reporter->id)->lockForUpdate()->firstOrFail();

                $existingReport = $this->findExisting($reporter, $reportedUser, $reportId);

                if ($existingReport && $existingReport->status !== 'dismissed') {
                    throw new DuplicateUserReportException($existingReport, $reportId !== null);
                }

                if ($existingReport) {
                    $existingReport->update([
                        'reason' => $attributes['reason'],
                        'description' => $attributes['description'] ?? null,
                        'report_id' => $reportId,
                        'status' => 'pending',
                        'admin_notes' => null,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                    ]);

                    return $existingReport;
                }

                return UserReport::query()->create([
                    'reporter_id' => $reporter->id,
                    'reported_user_id' => $reportedUser->id,
                    'report_id' => $reportId,
                    'reason' => $attributes['reason'],
                    'description' => $attributes['description'] ?? null,
                    'status' => 'pending',
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $duplicate = $reportId === null
                ? null
                : UserReport::query()
                    ->where('reporter_id', $reporter->id)
                    ->where('report_id', $reportId)
                    ->first();

            if ($duplicate) {
                throw new DuplicateUserReportException($duplicate, true);
            }

            throw $exception;
        }
    }

    private function findExisting(User $reporter, User $reportedUser, ?int $reportId): ?UserReport
    {
        if ($reportId !== null) {
            return UserReport::query()
                ->where('reporter_id', $reporter->id)
                ->where('report_id', $reportId)
                ->first();
        }

        $legacyScope = UserReport::query()
            ->where('reporter_id', $reporter->id)
            ->where('reported_user_id', $reportedUser->id);

        $activeReport = (clone $legacyScope)
            ->where('status', '!=', 'dismissed')
            ->oldest('id')
            ->first();

        if ($activeReport) {
            return $activeReport;
        }

        return (clone $legacyScope)
            ->whereNull('report_id')
            ->where('status', 'dismissed')
            ->latest('id')
            ->first();
    }
}
