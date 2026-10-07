<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceAreaRequest;
use App\Http\Resources\Api\V1\ServiceAreaRequestResource;
use App\Services\ServiceAreaRequestSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceAreaRequestController extends Controller
{
    public function latest(Request $request): JsonResponse|ServiceAreaRequestResource
    {
        $serviceAreaRequest = $request->user()->serviceAreaRequests()
            ->with('serviceZone')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (! $serviceAreaRequest) {
            return response()->json(['data' => null]);
        }

        return ServiceAreaRequestResource::make($serviceAreaRequest);
    }

    public function store(
        StoreServiceAreaRequest $request,
        ServiceAreaRequestSubmissionService $submissionService
    ): JsonResponse {
        $serviceAreaRequest = $submissionService->submit($request->user(), $request->validated());
        $serviceAreaRequest->load('serviceZone');

        return ServiceAreaRequestResource::make($serviceAreaRequest)
            ->additional(['message' => 'Service area request submitted successfully.'])
            ->response()
            ->setStatusCode(201);
    }
}
