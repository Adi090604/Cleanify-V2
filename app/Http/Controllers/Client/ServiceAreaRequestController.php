<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceAreaRequest;
use App\Models\ServiceAreaRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceAreaRequestController extends Controller
{
    public function store(StoreServiceAreaRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $normalizedAreaName = ServiceAreaRequest::normalizeAreaName($validated['area_name']);

        $serviceAreaRequest = DB::transaction(function () use ($request, $validated, $normalizedAreaName) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());

            $duplicateExists = $user->serviceAreaRequests()
                ->where('status', 'pending')
                ->where('normalized_area_name', $normalizedAreaName)
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'area_name' => 'You already have a pending request for this service area.',
                ]);
            }

            return $user->serviceAreaRequests()->create([
                ...$validated,
                'normalized_area_name' => $normalizedAreaName,
            ]);
        });

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
