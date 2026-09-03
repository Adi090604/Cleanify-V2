<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportFeedController extends Controller
{
    /**
     * Store a newly created report/update from the feed.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'description' => ['required', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:4096'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            
            // Additional MIME type validation
            $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return redirect()->back()
                    ->withErrors(['image' => 'Invalid image type. Only JPEG, PNG, GIF, and WebP are allowed.'])
                    ->withInput();
            }

            $imagePath = $file->store('reports', 'public');
            if (!$imagePath) {
                return back()->withErrors(['image' => 'The image could not be saved. Please try another file.'])->withInput();
            }
        }

        Report::create([
            'user_id' => Auth::id(),
            'location' => $validated['location'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'description' => $validated['description'],
            'image_path' => $imagePath,
            'status' => 'pending',
            'priority' => 'medium', // Default priority
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Your update has been shared! Our team will take a look soon.');
    }

    /**
     * Toggle like for a report.
     */
    public function toggleLike(Report $report): JsonResponse
    {
        $user = Auth::user();

        $existingLike = $report->likes()->where('user_id', $user->id)->first();

        if ($existingLike) {
            $existingLike->delete();
            $liked = false;
        } else {
            $report->likes()->create(['user_id' => $user->id]);
            $liked = true;
        }

        $likesCount = $report->likes()->count();

        return response()->json([
            'liked' => $liked,
            'likes_count' => $likesCount,
        ]);
    }

    /**
     * Store a new comment for the report.
     */
    public function storeComment(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:500'],
        ]);

        $comment = $report->comments()->create([
            'user_id' => Auth::id(),
            'comment' => $validated['comment'],
        ])->load('user');

        $commentsCount = $report->comments()->count();

        return response()->json([
            'comment' => [
                'id' => $comment->id,
                'author' => $comment->user?->name ?? 'Cleanify User',
                'avatar' => $comment->user?->getAvatarBgClasses(),
                'initial' => $comment->user?->getAvatarInitial(),
                'comment' => $comment->comment,
                'timestamp' => $comment->created_at?->diffForHumans() ?? 'Just now',
            ],
            'comments_count' => $commentsCount,
        ]);
    }

    /**
     * Get comments for a report.
     */
    public function getComments(Report $report): JsonResponse
    {
        $comments = $report->comments()
            ->with('user')
            ->latest()
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'author' => $comment->user?->name ?? 'Cleanify User',
                    'avatar' => $comment->user?->getAvatarBgClasses() ?? 'bg-gray-400',
                    'initial' => $comment->user?->getAvatarInitial() ?? '?',
                    'comment' => $comment->comment,
                    'timestamp' => $comment->created_at?->diffForHumans() ?? 'Just now',
                ];
            });

        return response()->json([
            'comments' => $comments,
        ]);
    }

    /**
     * Toggle follow state for a report.
     */
    public function toggleFollow(Report $report): JsonResponse
    {
        $user = Auth::user();

        $isFollowing = $report->followers()->where('user_id', $user->id)->exists();

        if ($isFollowing) {
            $report->followers()->detach($user->id);
            $isFollowing = false;
        } else {
            $report->followers()->attach($user->id);
            $isFollowing = true;
        }

        return response()->json([
            'following' => $isFollowing,
            'followers_count' => $report->followers()->count(),
        ]);
    }
}
