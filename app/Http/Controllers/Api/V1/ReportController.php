<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReportResource;
use App\Models\Report;
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
}
