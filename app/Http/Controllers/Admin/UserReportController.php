<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationAction;
use App\Models\UserReport;
use App\Services\ModerationService;
use App\Services\ReportDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class UserReportController extends Controller
{
    /**
     * Display a listing of user reports.
     */
    public function index(Request $request): View
    {
        $query = UserReport::with([
            'reporter',
            'reportedUser.moderationActions.administrator',
            'reviewer',
            'report.user',
            'moderationAction',
        ]);

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('reporter', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                    ->orWhereHas('reportedUser', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status && in_array($request->status, ['pending', 'reviewed', 'dismissed', 'action_taken'])) {
            $query->where('status', $request->status);
        }

        // Filter by reason
        if ($request->has('reason') && $request->reason && in_array($request->reason, ['spam', 'harassment', 'inappropriate_content', 'fake_account', 'other'])) {
            $query->where('reason', $request->reason);
        }

        $reports = $query->latest()->paginate(15)->withQueryString();

        // Statistics
        $totalReports = UserReport::count();
        $pendingReports = UserReport::where('status', 'pending')->count();
        $dismissedReports = UserReport::where('status', 'dismissed')->count();
        $actionTakenReports = UserReport::where('status', 'action_taken')->count();

        return view('admin.user-reports', [
            'activePage' => 'user-reports',
            'reports' => $reports,
            'totalReports' => $totalReports,
            'pendingReports' => $pendingReports,
            'dismissedReports' => $dismissedReports,
            'actionTakenReports' => $actionTakenReports,
            'search' => $request->search ?? '',
            'statusFilter' => $request->status ?? 'all',
            'reasonFilter' => $request->reason ?? 'all',
        ]);
    }

    /**
     * Update the status of a user report.
     */
    public function update(Request $request, string $id, ModerationService $moderation): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:dismiss,warning,community_restriction,report_submission_restriction,suspension'],
            'duration' => [
                'nullable',
                'required_if:decision,community_restriction,report_submission_restriction',
                'in:3,7,custom',
            ],
            'custom_until' => ['nullable', 'required_if:duration,custom', 'date', 'after:now'],
            'resident_reason' => ['nullable', 'string', 'max:500'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = UserReport::findOrFail($id);

        try {
            if ($validated['decision'] === 'dismiss') {
                $moderation->dismiss($report, $request->user(), $validated['admin_notes'] ?? null);

                return back()->with('success', 'User report dismissed successfully.');
            }

            $expiresAt = null;
            if (in_array($validated['decision'], [
                ModerationAction::COMMUNITY_RESTRICTION,
                ModerationAction::REPORT_SUBMISSION_RESTRICTION,
            ], true)) {
                $expiresAt = $validated['duration'] === 'custom'
                    ? Carbon::parse($validated['custom_until'])
                    : now()->addDays((int) $validated['duration']);
            }

            $action = $moderation->apply(
                $report,
                $request->user(),
                $validated['decision'],
                $validated['resident_reason'] ?? null,
                $validated['admin_notes'] ?? null,
                $expiresAt,
            );

            $message = match ($action->action_type) {
                ModerationAction::WARNING => 'Warning issued successfully.',
                ModerationAction::COMMUNITY_RESTRICTION => 'Community restriction applied successfully.',
                ModerationAction::REPORT_SUBMISSION_RESTRICTION => 'Report submission restriction applied successfully.',
                ModerationAction::SUSPENSION => 'Account suspended successfully.',
                default => 'Moderation action applied successfully.',
            };

            return back()->with('success', $message);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Something went wrong while processing the moderation action. Please try again.');
        }
    }

    public function lift(Request $request, ModerationAction $moderationAction, ModerationService $moderation): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = $moderation->lift($moderationAction, $request->user(), $validated['admin_notes'] ?? null);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Something went wrong while processing the moderation action. Please try again.');
        }

        $message = $result->action_type === ModerationAction::UNSUSPENDED
            ? 'Account unsuspended successfully.'
            : 'Restriction lifted successfully.';

        return back()->with('success', $message);
    }

    /**
     * Permanently delete the originating Community Report without changing moderation state.
     */
    public function destroyCommunityReport(
        UserReport $userReport,
        ReportDeletionService $deletionService
    ): RedirectResponse {
        $report = $userReport->report;

        if (! $report) {
            return back()->with('error', 'The original Community Report is no longer available.');
        }

        if (! $deletionService->delete($report)) {
            return back()->with('error', 'The report image could not be removed, so the report was not deleted.');
        }

        return back()->with('success', 'Community Report permanently deleted. The user report was retained.');
    }
}
