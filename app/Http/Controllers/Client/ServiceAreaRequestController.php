<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceAreaRequest;
use App\Services\ServiceAreaRequestSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ServiceAreaRequestController extends Controller
{
    public function store(
        StoreServiceAreaRequest $request,
        ServiceAreaRequestSubmissionService $submissionService
    ): JsonResponse|RedirectResponse {
        $serviceAreaRequest = $submissionService->submit($request->user(), $request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Service area request submitted successfully.',
                'request' => [
                    'id' => $serviceAreaRequest->id,
                    'area_name' => $serviceAreaRequest->area_name,
                    'status' => $serviceAreaRequest->status,
                ],
            ], 201);
        }

        return to_route('settings')->with('success', 'Service area request submitted successfully.');
    }
}
