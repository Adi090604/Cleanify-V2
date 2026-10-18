<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectReportRequest;
use App\Http\Requests\Admin\ResolveReportRequest;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportInProgressNotification;
use App\Notifications\ReportRejectedNotification;
use App\Notifications\ReportResolvedNotification;
use App\Services\ActivityLogService;
use App\Services\ReportDeletionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        $query = Report::with(['user', 'resolver']);
        $selectedReportId = $request->integer('report');
        $reporterId = $this->validReporterId($request->query('reporter_id'));
        $locationFilter = $this->normalizeLocation($request->query('location'));
        $activeReporter = $reporterId
            ? $this->reporterInsightsQuery()->whereKey($reporterId)->first()
            : null;

        if (! $selectedReportId && $activeReporter) {
            $query->where('user_id', $activeReporter->id);
        }

        if (! $selectedReportId && $locationFilter) {
            $query->whereRaw($this->normalizedLocationSql().' = ?', [$locationFilter]);
        }

        // Search functionality
        if (! $selectedReportId && $request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('location', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Filter out reports with deleted users (optional - uncomment if you want to hide orphaned reports)
        // $query->whereHas('user');

        // Filter by status
        if (! $selectedReportId && $request->has('status') && $request->status && in_array($request->status, ['pending', 'in_progress', 'resolved', 'rejected'])) {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if (! $selectedReportId && $request->has('priority') && $request->priority && in_array($request->priority, ['low', 'medium', 'high', 'critical'])) {
            $query->where('priority', $request->priority);
        }

        // Order by priority (critical first) then by created_at
        if ($selectedReportId) {
            $query->orderByRaw('id = ? desc', [$selectedReportId]);
        }

        $reports = $query->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        $topReporters = $this->reporterInsightsQuery()
            ->orderByDesc('reports_count')
            ->orderByDesc('latest_report_at')
            ->orderBy('id')
            ->limit(5)
            ->get();
        $mostReportedLocations = $this->mostReportedLocationsFor(
            $topReporters->pluck('id')->when($activeReporter, fn ($ids) => $ids->push($activeReporter->id))->unique()
        );
        $frequentLocations = $this->locationInsightsQuery()->limit(5)->get();
        $activeLocationSummary = $locationFilter
            ? $this->locationSummary($locationFilter)
            : null;

        // Statistics
        $pendingReports = Report::where('status', 'pending')->count();
        $inProgressReports = Report::where('status', 'in_progress')->count();
        $resolvedReports = Report::where('status', 'resolved')->count();
        $rejectedReports = Report::where('status', 'rejected')->count();
        $thisMonthReports = Report::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return view('admin.reports', [
            'activePage' => 'reports',
            'reports' => $reports,
            'pendingReports' => $pendingReports,
            'inProgressReports' => $inProgressReports,
            'resolvedReports' => $resolvedReports,
            'rejectedReports' => $rejectedReports,
            'thisMonthReports' => $thisMonthReports,
            'search' => $request->search ?? '',
            'statusFilter' => $request->status ?? '',
            'priorityFilter' => $request->priority ?? '',
            'selectedReportId' => $selectedReportId && Report::whereKey($selectedReportId)->exists() ? $selectedReportId : null,
            'topReporters' => $topReporters,
            'mostReportedLocations' => $mostReportedLocations,
            'frequentLocations' => $frequentLocations,
            'activeReporter' => $activeReporter,
            'activeLocationSummary' => $activeLocationSummary,
            'reporterFilter' => $activeReporter?->id,
            'locationFilter' => $locationFilter,
        ]);
    }

    public function reporters(Request $request): View
    {
        $search = $this->analyticsSearch($request->query('search'));
        $query = $this->reporterInsightsQuery();

        if ($search) {
            $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $reporters = $query
            ->orderByDesc('reports_count')
            ->orderByDesc('latest_report_at')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();
        $mostReportedLocations = $this->mostReportedLocationsFor($reporters->getCollection()->pluck('id'));

        return view('admin.reporters', [
            'activePage' => 'reports',
            'reporters' => $reporters,
            'mostReportedLocations' => $mostReportedLocations,
            'search' => $search ?? '',
        ]);
    }

    public function locations(Request $request): View
    {
        $search = $this->analyticsSearch($request->query('search'));
        $query = $this->locationInsightsQuery();

        if ($search) {
            $normalizedSearch = $this->normalizeLocation($search);
            $query->whereRaw($this->normalizedLocationSql().' LIKE ?', ["%{$normalizedSearch}%"]);
        }

        $locations = $query->paginate(20)->withQueryString();

        return view('admin.locations', [
            'activePage' => 'reports',
            'locations' => $locations,
            'search' => $search ?? '',
        ]);
    }

    private function reporterInsightsQuery(): Builder
    {
        return User::query()
            ->whereHas('reports')
            ->withCount([
                'reports',
                'reports as pending_reports_count' => fn (Builder $query) => $query->where('status', 'pending'),
                'reports as in_progress_reports_count' => fn (Builder $query) => $query->where('status', 'in_progress'),
                'reports as resolved_reports_count' => fn (Builder $query) => $query->where('status', 'resolved'),
                'reports as rejected_reports_count' => fn (Builder $query) => $query->where('status', 'rejected'),
            ])
            ->withMax('reports as latest_report_at', 'created_at');
    }

    private function locationInsightsQuery(): Builder
    {
        $locationSql = $this->normalizedLocationSql();

        return Report::query()
            ->whereNotNull('location')
            ->whereRaw("TRIM(location) <> ''")
            ->selectRaw("{$locationSql} as location_key")
            ->selectRaw('MIN(TRIM(location)) as location_name')
            ->selectRaw('COUNT(*) as total_reports')
            ->selectRaw('COUNT(DISTINCT user_id) as unique_reporters')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reports")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_reports")
            ->selectRaw("SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_reports")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_reports")
            ->selectRaw('MAX(created_at) as latest_report_at')
            ->groupByRaw($locationSql)
            ->orderByDesc('total_reports')
            ->orderByDesc('latest_report_at')
            ->orderBy('location_key');
    }

    /** @param Collection<int, int|string> $reporterIds */
    private function mostReportedLocationsFor(Collection $reporterIds): Collection
    {
        if ($reporterIds->isEmpty()) {
            return collect();
        }

        $locationSql = $this->normalizedLocationSql();

        return Report::query()
            ->whereIn('user_id', $reporterIds)
            ->whereNotNull('location')
            ->whereRaw("TRIM(location) <> ''")
            ->selectRaw('user_id')
            ->selectRaw("{$locationSql} as location_key")
            ->selectRaw('MIN(TRIM(location)) as location_name')
            ->selectRaw('COUNT(*) as total_reports')
            ->selectRaw('MAX(created_at) as latest_report_at')
            ->groupBy('user_id')
            ->groupByRaw($locationSql)
            ->orderBy('user_id')
            ->orderByDesc('total_reports')
            ->orderByDesc('latest_report_at')
            ->orderBy('location_key')
            ->get()
            ->groupBy('user_id')
            ->map->first();
    }

    private function locationSummary(string $locationFilter): ?object
    {
        $locationSql = $this->normalizedLocationSql();
        $summary = Report::query()
            ->whereRaw("{$locationSql} = ?", [$locationFilter])
            ->selectRaw('MIN(TRIM(location)) as location_name')
            ->selectRaw('COUNT(*) as total_reports')
            ->selectRaw('COUNT(DISTINCT user_id) as unique_reporters')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reports")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_reports")
            ->selectRaw("SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_reports")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_reports")
            ->selectRaw('MAX(created_at) as latest_report_at')
            ->first();

        return $summary && (int) $summary->total_reports > 0 ? $summary : null;
    }

    private function validReporterId(mixed $value): ?int
    {
        if (! is_scalar($value) || ! ctype_digit((string) $value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function normalizeLocation(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($value));

        return filled($normalized) ? mb_strtolower(mb_substr($normalized, 0, 255)) : null;
    }

    private function analyticsSearch(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $search = trim($value);

        return filled($search) ? mb_substr($search, 0, 100) : null;
    }

    private function normalizedLocationSql(): string
    {
        $expression = 'location';

        for ($pass = 0; $pass < 8; $pass++) {
            $expression = "REPLACE({$expression}, '  ', ' ')";
        }

        return "LOWER(TRIM({$expression}))";
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

        $reports = Report::whereIn('id', $request->report_ids)
            ->where('status', Report::STATUS_IN_PROGRESS)
            ->get();
        $count = 0;

        foreach ($reports as $report) {
            $report->update([
                'status' => 'resolved',
                'admin_notes' => $request->admin_notes,
                'resolved_by' => auth()->id(),
            ]);

            ActivityLogService::logReportResolved($report, $request->admin_notes);
            $this->notifyReportOwner($report, 'resolved');
            $this->notifyFollowers($report, 'resolved');
            $count++;
        }

        return $this->bulkTransitionResponse($request->report_ids, $count, 'resolved');
    }

    public function bulkMarkInProgress(Request $request): RedirectResponse
    {
        $request->validate([
            'report_ids' => ['required', 'array'],
            'report_ids.*' => ['exists:reports,id'],
        ]);

        $reports = Report::whereIn('id', $request->report_ids)
            ->where('status', Report::STATUS_PENDING)
            ->get();

        foreach ($reports as $report) {
            $report->update([
                'status' => Report::STATUS_IN_PROGRESS,
                'resolved_by' => auth()->id(),
            ]);

            ActivityLogService::logReportInProgress($report);
            $this->notifyReportOwner($report, 'in_progress');
            $this->notifyFollowers($report, 'in_progress');
        }

        return $this->bulkTransitionResponse($request->report_ids, $reports->count(), 'marked in progress');
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

        $reports = Report::whereIn('id', $request->report_ids)
            ->whereIn('status', [Report::STATUS_PENDING, Report::STATUS_IN_PROGRESS])
            ->get();
        $count = 0;

        foreach ($reports as $report) {
            $report->update([
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'resolved_by' => auth()->id(),
            ]);

            ActivityLogService::logReportRejected($report, $request->rejection_reason);
            $this->notifyReportOwner($report, 'rejected');
            $this->notifyFollowers($report, 'rejected');
            $count++;
        }

        return $this->bulkTransitionResponse($request->report_ids, $count, 'rejected');
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

        if (! $report->isInProgress()) {
            return redirect()->route('admin.reports')->with('error', 'Only reports in progress can be resolved.');
        }

        $report->update([
            'status' => 'resolved',
            'admin_notes' => $request->admin_notes,
            'resolved_by' => auth()->id(),
        ]);

        // Log activity
        ActivityLogService::logReportResolved($report, $request->admin_notes);

        $this->notifyReportOwner($report, 'resolved');
        $this->notifyFollowers($report, 'resolved');

        return redirect()->route('admin.reports')
            ->with('success', 'Report marked as resolved successfully!');
    }

    public function markInProgress(Request $request, Report $report): RedirectResponse
    {
        if (! $report->isPending()) {
            return redirect()->route('admin.reports')->with('error', 'Only pending reports can be marked in progress.');
        }

        $report->update([
            'status' => Report::STATUS_IN_PROGRESS,
            'resolved_by' => $request->user()->id,
        ]);

        ActivityLogService::logReportInProgress($report);
        $this->notifyReportOwner($report, 'in_progress');
        $this->notifyFollowers($report, 'in_progress');

        return redirect()->route('admin.reports')
            ->with('success', 'Report marked as in progress successfully!');
    }

    public function reject(RejectReportRequest $request, string $id): RedirectResponse
    {
        $report = Report::findOrFail($id);

        if (! in_array($report->status, [Report::STATUS_PENDING, Report::STATUS_IN_PROGRESS], true)) {
            return redirect()->route('admin.reports')->with('error', 'Only pending or in-progress reports can be rejected.');
        }

        $report->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'resolved_by' => auth()->id(),
        ]);

        // Log activity
        ActivityLogService::logReportRejected($report, $request->rejection_reason);

        $this->notifyReportOwner($report, 'rejected');
        $this->notifyFollowers($report, 'rejected');

        return redirect()->route('admin.reports')
            ->with('success', 'Report rejected successfully!');
    }

    public function destroy(Report $report, ReportDeletionService $deletionService): RedirectResponse
    {
        if (! $deletionService->delete($report)) {
            return redirect()->route('admin.reports')
                ->with('error', 'The report image could not be removed, so the report was not deleted.');
        }

        return redirect()->route('admin.reports')
            ->with('success', 'Report permanently deleted successfully.');
    }

    protected function notifyReportOwner(Report $report, string $type): void
    {
        if (! $report->user) {
            return;
        }

        try {
            $notification = match ($type) {
                'in_progress' => new ReportInProgressNotification($report),
                'resolved' => new ReportResolvedNotification($report),
                default => new ReportRejectedNotification($report),
            };
            $report->user->notify($notification);
        } catch (\Exception $e) {
            \Log::error('Failed to notify report owner: '.$e->getMessage());
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
                $notification = match ($type) {
                    'in_progress' => new ReportInProgressNotification($report, true),
                    'resolved' => new ReportResolvedNotification($report, true),
                    default => new ReportRejectedNotification($report, true),
                };
                $follower->notify($notification);
            } catch (\Exception $e) {
                \Log::error('Failed to notify follower: '.$e->getMessage());
            }
        }
    }

    /** @param array<int, int|string> $reportIds */
    private function bulkTransitionResponse(array $reportIds, int $processed, string $action): RedirectResponse
    {
        $skipped = collect($reportIds)->unique()->count() - $processed;

        if ($processed === 0) {
            return redirect()->route('admin.reports')
                ->with('error', "No selected reports were eligible to be {$action}.");
        }

        $message = "{$processed} report(s) {$action} successfully.";

        if ($skipped > 0) {
            $message .= " {$skipped} ineligible report(s) skipped.";
        }

        return redirect()->route('admin.reports')->with('success', $message);
    }
}
