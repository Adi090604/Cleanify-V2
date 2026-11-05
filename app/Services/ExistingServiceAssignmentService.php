<?php

namespace App\Services;

use App\Models\ServiceAreaRequest;
use App\Models\User;
use App\Notifications\ServiceAreaRequestStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExistingServiceAssignmentService
{
    public function __construct(private readonly ExistingServiceCoverageDetector $coverageDetector) {}

    public function assign(
        ServiceAreaRequest $serviceAreaRequest,
        int $serviceZoneId,
        User $administrator,
        ?string $adminNotes = null
    ): ServiceAreaRequest {
        if (! $administrator->isAdmin()) {
            abort(403);
        }

        $assignedRequest = DB::transaction(function () use ($serviceAreaRequest, $serviceZoneId, $administrator, $adminNotes): ServiceAreaRequest {
            $lockedRequest = ServiceAreaRequest::query()
                ->lockForUpdate()
                ->findOrFail($serviceAreaRequest->getKey());

            if ($lockedRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'assignment' => 'This service area request has already been reviewed.',
                ]);
            }

            $coverage = $this->coverageDetector->detect($lockedRequest, $serviceZoneId, true);
            $candidate = $coverage['candidates']->first();

            if (! $candidate) {
                throw ValidationException::withMessages([
                    'service_zone_id' => 'The selected service is no longer an eligible coverage match. Review the latest truck location and try again.',
                ]);
            }

            $resident = User::query()->lockForUpdate()->find($lockedRequest->user_id);
            if (! $resident) {
                throw ValidationException::withMessages([
                    'assignment' => 'The requesting resident is no longer available.',
                ]);
            }

            $lockedRequest->forceFill([
                'status' => 'approved',
                'service_zone_id' => $candidate['service_zone_id'],
                'admin_notes' => $adminNotes,
                'reviewed_by' => $administrator->id,
                'reviewed_at' => now(),
            ])->save();
            $resident->forceFill(['service_area' => $candidate['service_zone_name']])->save();

            ActivityLogService::log(
                'service_area_request.existing_service_assigned',
                $lockedRequest,
                'Resident assigned to an existing Service Zone after coverage review.',
                [
                    'request_id' => $lockedRequest->id,
                    'service_zone_id' => $candidate['service_zone_id'],
                    'truck_id' => $candidate['truck_id'],
                    'distance_meters' => $candidate['distance_meters'],
                    'has_active_schedule' => $candidate['has_active_schedule'],
                    'location_was_stale' => $candidate['is_location_stale'],
                ]
            );

            $lockedRequest->load(['user', 'serviceZone']);
            $lockedRequest->setAttribute('assignment_has_active_schedule', $candidate['has_active_schedule']);

            return $lockedRequest;
        });

        DB::afterCommit(function () use ($assignedRequest): void {
            try {
                $assignedRequest->user?->notify(new ServiceAreaRequestStatusNotification(
                    $assignedRequest,
                    ServiceAreaRequestStatusNotification::EVENT_EXISTING_SERVICE_ASSIGNED,
                    $assignedRequest->serviceZone,
                    true
                ));
            } catch (Throwable $exception) {
                Log::error('Unable to notify resident about an existing service assignment.', [
                    'service_area_request_id' => $assignedRequest->id,
                    'exception' => $exception,
                ]);
            }
        });

        return $assignedRequest;
    }
}
