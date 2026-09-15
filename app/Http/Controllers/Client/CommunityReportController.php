<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CommunityReportController extends Controller
{
    /**
     * Show the community reports feed.
     */
    public function index(): View
    {
        $reports = Report::publiclyVisible()
            ->with(['user'])
            ->withCount(['likes', 'comments', 'followers'])
            ->latest()
            ->paginate(6);

        return view('community-reports', [
            'activePage' => 'reports',
            'reports' => $reports,
        ]);
    }

    /**
     * Provide details for a single report.
     */
    public function show(Report $report): JsonResponse
    {
        $report->load([
            'user',
            'comments.user' => fn ($query) => $query->latest()->limit(10),
            'likes',
            'followers',
        ])->loadCount(['likes', 'comments', 'followers']);

        $user = Auth::user();
        $isFollowing = $user ? $report->followers->contains('id', $user->id) : false;
        $coordinate = $report->latitude !== null && $report->longitude !== null ? [
            'lat' => (float) $report->latitude,
            'lng' => (float) $report->longitude,
        ] : null;
        $timeline = $this->buildTimeline($report);

        return response()->json([
            'id' => $report->id,
            'title' => $report->location,
            'author' => [
                'name' => $report->user->name ?? 'Cleanify User',
                'avatar_bg' => $report->user?->getAvatarBgClasses(),
                'initial' => $report->user?->getAvatarInitial(),
            ],
            'location' => $report->location,
            'description' => $report->description,
            'status' => $report->status,
            'status_badge' => $report->getStatusBadgeBgClass(),
            'admin_notes' => $report->admin_notes,
            'rejection_reason' => $report->rejection_reason,
            'image' => $report->image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($report->image_path)
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($report->image_path)
                : null,
            'likes_count' => $report->likes_count,
            'comments_count' => $report->comments_count,
            'followers_count' => $report->followers_count,
            'is_following' => $isFollowing,
            'timeline' => $timeline,
            'comments' => $report->comments->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'author' => $comment->user?->name ?? 'Cleanify User',
                    'avatar' => $comment->user?->getAvatarBgClasses(),
                    'initial' => $comment->user?->getAvatarInitial(),
                    'comment' => $comment->comment,
                    'timestamp' => $comment->created_at?->diffForHumans() ?? 'Just now',
                ];
            }),
            'coordinate' => $coordinate,
            'related' => $this->relatedReports($report),
        ]);
    }

    private function buildTimeline(Report $report): array
    {
        $timeline = [
            [
                'label' => 'Reported',
                'timestamp' => $report->created_at?->toIso8601String(),
                'meta' => $report->created_at?->diffForHumans(),
                'status' => 'created',
            ],
        ];

        if ($report->status === 'resolved' && $report->resolved_at) {
            $timeline[] = [
                'label' => 'Resolved',
                'timestamp' => $report->resolved_at->toIso8601String(),
                'meta' => $report->resolved_at->diffForHumans(),
                'status' => 'resolved',
            ];
        } elseif ($report->status === 'rejected' && $report->resolved_at) {
            $timeline[] = [
                'label' => 'Rejected',
                'timestamp' => $report->resolved_at->toIso8601String(),
                'meta' => $report->resolved_at->diffForHumans(),
                'status' => 'rejected',
            ];
        } else {
            $timeline[] = [
                'label' => ucfirst($report->status),
                'timestamp' => $report->updated_at?->toIso8601String(),
                'meta' => $report->updated_at?->diffForHumans(),
                'status' => $report->status,
            ];
        }

        return $timeline;
    }

    private function relatedReports(Report $report)
    {
        return Report::publiclyVisible()
            ->where('id', '!=', $report->id)
            ->where('location', $report->location)
            ->latest()
            ->take(3)
            ->get()
            ->map(function ($related) {
                return [
                    'id' => $related->id,
                    'location' => $related->location,
                    'status' => ucfirst($related->status),
                    'timestamp' => $related->created_at?->diffForHumans(),
                ];
            });
    }
}
