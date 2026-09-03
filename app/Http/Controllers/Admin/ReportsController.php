<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectReportRequest;
use App\Http\Requests\Admin\ResolveReportRequest;
use App\Models\Report;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        $query = Report::with(['user', 'resolver']);
        $selectedReportId = $request->integer('report');

        // Search functionality
        if (!$selectedReportId && $request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('location', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filter out reports with deleted users (optional - uncomment if you want to hide orphaned reports)
        // $query->whereHas('user');

        // Filter by status
        if (!$selectedReportId && $request->has('status') && $request->status && in_array($request->status, ['pending', 'resolved', 'rejected'])) {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if (!$selectedReportId && $request->has('priority') && $request->priority && in_array($request->priority, ['low', 'medium', 'high', 'critical'])) {
            $query->where('priority', $request->priority);
        }

        // Order by priority (critical first) then by created_at
        if ($selectedReportId) {
            $query->orderByRaw('id = ? desc', [$selectedReportId]);
        }

        $reports = $query->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Statistics
        $pendingReports = Report::where('status', 'pending')->count();
        $resolvedReports = Report::where('status', 'resolved')->count();
        $rejectedReports = Report::where('status', 'rejected')->count();
        $thisMonthReports = Report::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return view('admin.reports', [
            'activePage' => 'reports',
            'reports' => $reports,
            'pendingReports' => $pendingReports,
            'resolvedReports' => $resolvedReports,
            'rejectedReports' => $rejectedReports,
            'thisMonthReports' => $thisMonthReports,
            'search' => $request->search ?? '',
            'statusFilter' => $request->status ?? '',
            'priorityFilter' => $request->priority ?? '',
            'selectedReportId' => $selectedReportId && Report::whereKey($selectedReportId)->exists() ? $selectedReportId : null,
        ]);
    }

    /**
     * Bulk resolve reports.
     */
    public function bulkResolve(Request $request): RedirectResponse
    {
        $request->validate([
            'report_ids' => ['required', 'array'],
            'report_ids.*' => ['exists:reports,id'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $reports = Report::whereIn('id', $request->report_ids)->get();
        $count = 0;

        foreach ($reports as $report) {
            $report->update([
                'status' => 'resolved',
                'admin_notes' => $request->admin_notes,
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
            ]);

            ActivityLogService::logReportResolved($report, $request->admin_notes);
            $this->notifyReportOwner($report, 'resolved');
            $count++;
        }

        return redirect()->route('admin.reports')
            ->with('success', "{$count} report(s) resolved successfully!");
    }

    /**
     * Bulk reject reports.
     */
    public function bulkReject(Request $request): RedirectResponse
    {
        $request->validate([
            'report_ids' => ['required', 'array'],
            'report_ids.*' => ['exists:reports,id'],
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $reports = Report::whereIn('id', $request->report_ids)->get();
        $count = 0;

        foreach ($reports as $report) {
            $report->update([
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
            ]);

            ActivityLogService::logReportRejected($report, $request->rejection_reason);
            $this->notifyReportOwner($report, 'rejected');
            $count++;
        }

        return redirect()->route('admin.reports')
            ->with('success', "{$count} report(s) rejected successfully!");
    }

    /**
     * Update report priority.
     */
    public function updatePriority(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'priority' => ['required', 'in:low,medium,high,critical'],
        ]);

        $report = Report::findOrFail($id);
        $oldPriority = $report->priority;
        $report->update(['priority' => $request->priority]);

        ActivityLogService::log(
            'report.priority_updated',
            $report,
            "Report priority changed from {$oldPriority} to {$request->priority}",
            ['old_priority' => $oldPriority, 'new_priority' => $request->priority]
        );

        return redirect()->route('admin.reports')
            ->with('success', 'Report priority updated successfully!');
    }

    public function resolve(ResolveReportRequest $request, string $id): RedirectResponse
    {
        $report = Report::findOrFail($id);

        if ($report->status !== 'pending') {
            return redirect()->route('admin.reports')->with('error', 'Only pending reports can be resolved.');
        }

        $report->update([
            'status' => 'resolved',
            'admin_notes' => $request->admin_notes,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        // Log activity
        ActivityLogService::logReportResolved($report, $request->admin_notes);

        $this->notifyReportOwner($report, 'resolved');
        $this->notifyFollowers($report, 'resolved');

        return redirect()->route('admin.reports')
            ->with('success', 'Report marked as resolved successfully!');
    }

    public function reject(RejectReportRequest $request, string $id): RedirectResponse
    {
        $report = Report::findOrFail($id);

        if ($report->status !== 'pending') {
            return redirect()->route('admin.reports')->with('error', 'Only pending reports can be rejected.');
        }

        $report->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        // Log activity
        ActivityLogService::logReportRejected($report, $request->rejection_reason);

        $this->notifyReportOwner($report, 'rejected');
        $this->notifyFollowers($report, 'rejected');

        return redirect()->route('admin.reports')
            ->with('success', 'Report rejected successfully!');
    }

    protected function notifyReportOwner(Report $report, string $type): void
    {
        if (!$report->user) {
            return;
        }

        try {
            if ($type === 'resolved') {
                $report->user->notify(new \App\Notifications\ReportResolvedNotification($report));
            } else {
                $report->user->notify(new \App\Notifications\ReportRejectedNotification($report));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to notify report owner: ' . $e->getMessage());
        }
    }

    protected function notifyFollowers(Report $report, string $type): void
    {
        // Get all followers (notification class will handle email preferences)
        $followers = $report->followers()
            ->where('user_id', '!=', $report->user_id)
            ->get();

        foreach ($followers as $follower) {
            try {
                // Check if user has reports notifications enabled
                $preferences = $follower->notification_preferences ?? [];
                $reportsEnabled = $preferences['reports'] ?? true;

                if ($reportsEnabled) {
                    if ($type === 'resolved') {
                        $follower->notify(new \App\Notifications\ReportResolvedNotification($report, true));
                    } else {
                        $follower->notify(new \App\Notifications\ReportRejectedNotification($report, true));
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Failed to notify follower: ' . $e->getMessage());
            }
        }
    }
}
