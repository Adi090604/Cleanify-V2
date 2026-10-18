<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the client dashboard with personalized data.
     */
    public function index(): View
    {
        $user = Auth::user();

        $recentReports = Report::visibleOnHomeFeed()
            ->with([
                'user',
                'likes:id,report_id,user_id',
                'comments' => function ($query) {
                    $query->latest()->take(3)->with('user');
                },
            ])
            ->withCount(['likes', 'comments'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $userReportCount = Report::where('user_id', $user->id)->count();
        $pendingReports = Report::where('user_id', $user->id)->where('status', 'pending')->count();
        $resolvedReports = Report::where('user_id', $user->id)->where('status', 'resolved')->count();

        return view('dashboard', [
            'activePage' => 'home',
            'user' => $user,
            'recentReports' => $recentReports,
            'userReportCount' => $userReportCount,
            'pendingReports' => $pendingReports,
            'resolvedReports' => $resolvedReports,
        ]);
    }
}
