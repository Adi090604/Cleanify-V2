<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceAreaRequest;
use App\Services\ExistingServiceCoverageDetector;
use App\Services\ServiceAreaRequestSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ServiceAreaRequestController extends Controller
{
    public function store(
        StoreServiceAreaRequest $request,
        ServiceAreaRequestSubmissionService $submissionService,
        ExistingServiceCoverageDetector $coverageDetector
    ): JsonResponse|RedirectResponse {
        $serviceAreaRequest = $submissionService->submit($request->user(), $request->validated());
        $coverage = $coverageDetector->detect($serviceAreaRequest);
        $hasCoverageMatch = $coverage['candidates']->isNotEmpty();
        $message = $hasCoverageMatch
            ? 'Your requested location may already be covered by an existing collection service. Your request is awaiting administrator confirmation.'
            : 'Service area request submitted successfully.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'request' => [
                    'id' => $serviceAreaRequest->id,
                    'area_name' => $serviceAreaRequest->area_name,
                    'status' => $serviceAreaRequest->status,
                ],
            ], 201);
        }

        return to_route('settings')->with('success', $message);
    }
}
