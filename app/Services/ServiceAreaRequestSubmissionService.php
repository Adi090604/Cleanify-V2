<?php

namespace App\Services;

use App\Models\ServiceAreaRequest;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceAreaRequestSubmissionService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function submit(User $user, array $attributes): ServiceAreaRequest
    {
        $safeAttributes = Arr::only($attributes, [
            'area_name',
            'barangay',
            'address',
            'latitude',
            'longitude',
            'details',
        ]);
        $normalizedAreaName = ServiceAreaRequest::normalizeAreaName($safeAttributes['area_name']);
        $normalizedBarangay = ServiceAreaRequest::normalizeBarangay($safeAttributes['barangay']);

        return DB::transaction(function () use ($user, $safeAttributes, $normalizedAreaName, $normalizedBarangay): ServiceAreaRequest {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            $duplicateExists = $lockedUser->serviceAreaRequests()
                ->where('status', 'pending')
                ->where('normalized_area_name', $normalizedAreaName)
                ->get(['barangay'])
                ->contains(fn (ServiceAreaRequest $request): bool => is_string($request->barangay)
                    && ServiceAreaRequest::normalizeBarangay($request->barangay) === $normalizedBarangay);

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'area_name' => 'You already have a pending request for this service area.',
                ]);
            }

            return $lockedUser->serviceAreaRequests()->create([
                ...$safeAttributes,
                'normalized_area_name' => $normalizedAreaName,
            ]);
        });
    }
}
