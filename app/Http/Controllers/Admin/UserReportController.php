<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserReport;
use App\Services\ReportDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserReportController extends Controller
{
    /**
     * Display a listing of user reports.
     */
    public function index(Request $request): View
    {
        $query = UserReport::with(['reporter', 'reportedUser', 'reviewer', 'report.user']);

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
        $reviewedReports = UserReport::where('status', 'reviewed')->count();
        $actionTakenReports = UserReport::where('status', 'action_taken')->count();

        return view('admin.user-reports', [
            'activePage' => 'user-reports',
            'reports' => $reports,
            'totalReports' => $totalReports,
            'pendingReports' => $pendingReports,
            'reviewedReports' => $reviewedReports,
            'actionTakenReports' => $actionTakenReports,
            'search' => $request->search ?? '',
            'statusFilter' => $request->status ?? 'all',
            'reasonFilter' => $request->reason ?? 'all',
        ]);
    }

    /**
     * Update the status of a user report.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,reviewed,dismissed,action_taken'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = UserReport::findOrFail($id);
        $oldStatus = $report->status;

        $report->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // If action taken, you might want to ban the user or take other actions
        if ($validated['status'] === 'action_taken' && $report->reportedUser) {
            // Optionally ban the user if multiple reports or serious violation
            // $report->reportedUser->update(['banned_at' => now()]);
        }

        // Notify the reporter if status changed (and is not pending anymore)
        if ($oldStatus !== $validated['status'] && $validated['status'] !== 'pending' && $report->reporter) {
            try {
                $report->reporter->notify(new \App\Notifications\UserReportReviewedNotification($report));
            } catch (\Exception $e) {
                \Log::error('Failed to notify user about report review: '.$e->getMessage());
            }
        }

        return back()->with('success', 'User report updated successfully!');
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
