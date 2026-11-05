<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceAreaRequest;
use App\Http\Resources\Api\V1\ServiceAreaRequestResource;
use App\Services\ExistingServiceCoverageDetector;
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
        ServiceAreaRequestSubmissionService $submissionService,
        ExistingServiceCoverageDetector $coverageDetector
    ): JsonResponse {
        $serviceAreaRequest = $submissionService->submit($request->user(), $request->validated());
        $serviceAreaRequest->load('serviceZone');
        $coverage = $coverageDetector->detect($serviceAreaRequest);
        $bestCandidate = $coverage['candidates']->first();
        $message = $bestCandidate
            ? 'Your requested location may already be covered by an existing collection service. Your request is awaiting administrator confirmation.'
            : 'Service area request submitted successfully.';

        return ServiceAreaRequestResource::make($serviceAreaRequest)
            ->additional(array_filter([
                'message' => $message,
                'coverage_recommendation' => $bestCandidate ? [
                    'possible_existing_service' => true,
                    'status' => $bestCandidate['recommendation_status'],
                    'message' => $message,
                ] : null,
            ], fn ($value) => $value !== null))
            ->response()
            ->setStatusCode(201);
    }
}
