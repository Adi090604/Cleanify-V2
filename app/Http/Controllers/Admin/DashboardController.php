<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Schedule;
use App\Models\Truck;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $currentDate = now();
        $weekStart = $currentDate->copy()->startOfWeek();

        // Statistics
        $totalUsers = User::count();
        $totalReports = Report::count();
        $activeSchedules = Schedule::where('status', 'active')->count();
        $activeTrucks = Truck::where('status', 'active')->count();

        // Current-week creation activity (in the configured application timezone)
        $usersThisWeek = User::whereBetween('created_at', [$weekStart, $currentDate])->count();
        $reportsThisWeek = Report::whereBetween('created_at', [$weekStart, $currentDate])->count();
        $activeSchedulesThisWeek = Schedule::where('status', 'active')
            ->whereBetween('created_at', [$weekStart, $currentDate])
            ->count();
        $activeTrucksThisWeek = Truck::where('status', 'active')
            ->whereBetween('created_at', [$weekStart, $currentDate])
            ->count();

        // Reports by status for chart
        $pendingReports = Report::where('status', 'pending')->count();
        $resolvedReports = Report::where('status', 'resolved')->count();
        $rejectedReports = Report::where('status', 'rejected')->count();

        // Reports overview chart data (by status)
        $reportsChartData = [
            'labels' => ['Pending', 'Resolved', 'Rejected'],
            'data' => [$pendingReports, $resolvedReports, $rejectedReports],
            'colors' => ['#eab308', '#16a34a', '#dc2626'],
        ];

        // User roles chart data
        $totalAdmins = User::where('is_admin', true)->count();
        $totalRegularUsers = User::where('is_admin', false)->count();
        $regularUserPercentage = $totalUsers > 0 ? round(($totalRegularUsers / $totalUsers) * 100, 1) : 0.0;
        $adminPercentage = $totalUsers > 0 ? round(($totalAdmins / $totalUsers) * 100, 1) : 0.0;
        
        $usersChartData = [
            'labels' => ['Regular Users', 'Admins'],
            'data' => [$totalRegularUsers, $totalAdmins],
            'colors' => ['#16a34a', '#2563eb'],
        ];

        // Recent reports (last 5)
        $recentReports = Report::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'activePage' => 'dashboard',
            'totalUsers' => $totalUsers,
            'totalReports' => $totalReports,
            'activeSchedules' => $activeSchedules,
            'activeTrucks' => $activeTrucks,
            'usersThisWeek' => $usersThisWeek,
            'reportsThisWeek' => $reportsThisWeek,
            'activeSchedulesThisWeek' => $activeSchedulesThisWeek,
            'activeTrucksThisWeek' => $activeTrucksThisWeek,
            'reportsChartData' => $reportsChartData,
            'usersChartData' => $usersChartData,
            'regularUserPercentage' => $regularUserPercentage,
            'adminPercentage' => $adminPercentage,
            'recentReports' => $recentReports,
            'admin' => auth()->user(),
            'currentDate' => $currentDate,
        ]);
    }
}
