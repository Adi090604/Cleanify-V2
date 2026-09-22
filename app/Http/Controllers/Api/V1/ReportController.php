<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReportCommentResource;
use App\Http\Resources\Api\V1\ReportResource;
use App\Http\Requests\StoreReportRequest;
use App\Models\Report;
use App\Services\ReportCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $reports = Report::publiclyVisible()
            ->with([
                'user:id,name',
                'likes' => fn ($query) => $query->where('user_id', $request->user()->id),
            ])
            ->withCount(['likes', 'comments', 'followers'])
            ->latest()
            ->paginate(6);

        return ReportResource::collection($reports);
    }

    public function store(StoreReportRequest $request, ReportCreator $creator): JsonResponse
    {
        $report = $creator->create($request->user(), $request->validated(), $request->file('image'));
        $report->load('user:id,name')->loadCount(['likes', 'comments', 'followers']);
        $report->setRelation('likes', collect());

        return (new ReportResource($report))
            ->additional(['message' => 'Report submitted successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        $reports = Report::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'user:id,name',
                'likes' => fn ($query) => $query->where('user_id', $request->user()->id),
            ])
            ->withCount(['likes', 'comments', 'followers'])
            ->latest()
            ->paginate(10);

        return ReportResource::collection($reports);
    }

    public function toggleLike(Request $request, Report $report): JsonResponse
    {
        $result = DB::transaction(function () use ($request, $report): array {
            Report::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();

            $existingLike = $report->likes()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if ($existingLike) {
                $existingLike->delete();
                $liked = false;
            } else {
                $report->likes()->create(['user_id' => $request->user()->id]);
                $liked = true;
            }

            return [
                'liked' => $liked,
                'likes_count' => $report->likes()->count(),
            ];
        });

        return response()->json($result);
    }

    public function comments(Report $report): AnonymousResourceCollection
    {
        $comments = $report->comments()
            ->with('user:id,name,profile_photo_path')
            ->latest()
            ->get();

        return ReportCommentResource::collection($comments);
    }

    public function storeComment(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:500'],
        ]);

        $comment = $report->comments()->create([
            'user_id' => $request->user()->id,
            'comment' => $validated['comment'],
        ])->load('user:id,name,profile_photo_path');

        return response()->json([
            'comment' => ReportCommentResource::make($comment)->resolve($request),
            'comments_count' => $report->comments()->count(),
        ], 201);
    }
}
