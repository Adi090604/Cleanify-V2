<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveServiceAreaRequest;
use App\Http\Requests\Admin\CreateServiceZoneForAreaRequest;
use App\Http\Requests\Admin\RejectServiceAreaRequest;
use App\Models\ServiceAreaRequest;
use App\Models\ServiceZone;
use App\Notifications\ServiceAreaRequestStatusNotification;
use App\Services\ActivityLogService;
use App\Services\ServiceZoneCreator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ServiceAreaRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->string('status')->toString(), ['pending', 'approved', 'rejected'], true)
            ? $request->string('status')->toString()
            : 'all';
        $search = trim($request->string('search')->toString());

        $query = ServiceAreaRequest::query()
            ->with(['user', 'reviewer', 'serviceZone'])
            ->withExists([
                'activityLogs as has_zone_setup_history' => fn ($activityQuery) => $activityQuery->whereIn('action', [
                    'service_area_request.zone_created',
                    'service_area_request.zone_linked',
                    'service_area_request.zone_relinked',
                ]),
            ]);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('area_name', 'like', "%{$search}%")
                    ->orWhere('barangay', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return view('admin.service-area-requests', [
            'activePage' => 'service-area-requests',
            'requests' => $query->latest()->paginate(15)->withQueryString(),
            'search' => $search,
            'statusFilter' => $status,
            'totalRequests' => ServiceAreaRequest::count(),
            'pendingRequests' => ServiceAreaRequest::where('status', 'pending')->count(),
            'approvedRequests' => ServiceAreaRequest::where('status', 'approved')->count(),
            'rejectedRequests' => ServiceAreaRequest::where('status', 'rejected')->count(),
        ]);
    }

    public function approve(
        ApproveServiceAreaRequest $request,
        ServiceAreaRequest $serviceAreaRequest
    ): RedirectResponse {
        $reviewedRequest = $this->review($serviceAreaRequest, 'approved', $request->validated('admin_notes'));
        $this->notifyRequester($reviewedRequest, ServiceAreaRequestStatusNotification::EVENT_APPROVED);

        return back()->with('success', 'Service area request approved for consideration.');
    }

    public function reject(
        RejectServiceAreaRequest $request,
        ServiceAreaRequest $serviceAreaRequest
    ): RedirectResponse {
        $reviewedRequest = $this->review($serviceAreaRequest, 'rejected', $request->validated('admin_notes'));
        $this->notifyRequester($reviewedRequest, ServiceAreaRequestStatusNotification::EVENT_REJECTED);

        return back()->with('success', 'Service area request rejected.');
    }

    public function createZone(
        CreateServiceZoneForAreaRequest $request,
        ServiceAreaRequest $serviceAreaRequest,
        ServiceZoneCreator $creator
    ): RedirectResponse {
        $validated = $request->validated();

        $conversion = DB::transaction(function () use ($request, $serviceAreaRequest, $creator, $validated): array {
            $lockedRequest = $this->lockForZoneCreation($serviceAreaRequest);
            $serviceZone = $creator->create($validated);

            $lockedRequest->forceFill(['service_zone_id' => $serviceZone->id])->save();
            $assignToRequester = $request->boolean('assign_to_requester');
            $this->assignRequesterIfRequested($lockedRequest, $serviceZone, $assignToRequester);
            $this->logZoneCreation($lockedRequest, $serviceZone, $assignToRequester);

            return [$lockedRequest, $serviceZone, $assignToRequester];
        });

        $this->notifyRequester(
            $conversion[0],
            ServiceAreaRequestStatusNotification::EVENT_ZONE_CREATED,
            $conversion[1],
            $conversion[2]
        );

        return back()->with('success', 'Service Zone created for the approved request.');
    }

    private function review(
        ServiceAreaRequest $serviceAreaRequest,
        string $status,
        ?string $adminNotes
    ): ServiceAreaRequest {
        return DB::transaction(function () use ($serviceAreaRequest, $status, $adminNotes): ServiceAreaRequest {
            $lockedRequest = ServiceAreaRequest::query()
                ->lockForUpdate()
                ->findOrFail($serviceAreaRequest->getKey());

            if ($lockedRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'review' => 'This service area request has already been reviewed.',
                ]);
            }

            $lockedRequest->forceFill([
                'status' => $status,
                'admin_notes' => $adminNotes,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ])->save();

            ActivityLogService::log(
                "service_area_request.{$status}",
                $lockedRequest,
                'Service Area Request '.($status === 'approved' ? 'approved' : 'rejected').' by admin.',
                ['status' => $status]
            );

            return $lockedRequest;
        });
    }

    private function notifyRequester(
        ServiceAreaRequest $serviceAreaRequest,
        string $eventType,
        ?ServiceZone $serviceZone = null,
        bool $requesterAssigned = false
    ): void {
        $serviceAreaRequest->loadMissing('user');

        if (! $serviceAreaRequest->user) {
            return;
        }

        try {
            $serviceAreaRequest->user->notify(new ServiceAreaRequestStatusNotification(
                $serviceAreaRequest,
                $eventType,
                $serviceZone,
                $requesterAssigned
            ));
        } catch (Throwable $exception) {
            Log::error('Unable to notify resident about a Service Area Request update.', [
                'service_area_request_id' => $serviceAreaRequest->id,
                'event_type' => $eventType,
                'exception' => $exception,
            ]);
        }
    }

    private function lockForZoneCreation(ServiceAreaRequest $serviceAreaRequest): ServiceAreaRequest
    {
        $lockedRequest = ServiceAreaRequest::query()
            ->lockForUpdate()
            ->findOrFail($serviceAreaRequest->getKey());

        if ($lockedRequest->status !== 'approved') {
            throw ValidationException::withMessages([
                'conversion' => 'Only approved service area requests can create a Service Zone.',
            ]);
        }

        if ($lockedRequest->service_zone_id !== null) {
            $linkedZoneStillExists = ServiceZone::whereKey($lockedRequest->service_zone_id)->exists();

            if ($linkedZoneStillExists) {
                throw ValidationException::withMessages([
                    'conversion' => 'This service area request already has a Service Zone.',
                ]);
            }

            // Recover legacy/dangling associations when the database did not
            // enforce nullOnDelete for a Service Zone that no longer exists.
            $lockedRequest->forceFill(['service_zone_id' => null])->save();
        }

        return $lockedRequest;
    }

    private function assignRequesterIfRequested(
        ServiceAreaRequest $serviceAreaRequest,
        ServiceZone $serviceZone,
        bool $assignToRequester
    ): void {
        if ($assignToRequester) {
            $serviceAreaRequest->user()->update(['service_area' => $serviceZone->display_name]);
        }
    }

    private function logZoneCreation(
        ServiceAreaRequest $serviceAreaRequest,
        ServiceZone $serviceZone,
        bool $requesterAssigned
    ): void {
        ActivityLogService::log(
            'service_area_request.zone_created',
            $serviceAreaRequest,
            'Service Area Request converted to a new Service Zone.',
            [
                'request_id' => $serviceAreaRequest->id,
                'service_zone_id' => $serviceZone->id,
                'action_type' => 'created',
                'requester_assigned' => $requesterAssigned,
            ]
        );
    }
}
