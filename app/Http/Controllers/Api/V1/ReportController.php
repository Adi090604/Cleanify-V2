<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReportResource;
use App\Http\Requests\StoreReportRequest;
use App\Models\Report;
use App\Services\ReportCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $reports = Report::query()
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
}
