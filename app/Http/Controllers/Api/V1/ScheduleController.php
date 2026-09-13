<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\ServiceZone;
use App\Services\ScheduleReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleReminderService $schedules)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $serviceArea = $this->validServiceArea($request);
        $schedules = $serviceArea
            ? Schedule::where('status', 'active')->where('area', $serviceArea)->orderBy('area')->get()
            : collect();

        return response()->json([
            'service_area' => $serviceArea,
            'schedules' => $schedules->map(fn (Schedule $schedule) => $this->schedules->schedulePayload($schedule))->values(),
            'upcoming_pickups' => $this->schedules->upcomingForArea($serviceArea),
        ]);
    }

    public function next(Request $request): JsonResponse
    {
        $serviceArea = $this->validServiceArea($request);

        return response()->json([
            'service_area' => $serviceArea,
            'next_collection' => $this->schedules->upcomingForArea($serviceArea, 1)->first(),
        ]);
    }

    private function validServiceArea(Request $request): ?string
    {
        $serviceArea = $request->user()->service_area;

        if (!$serviceArea) {
            return null;
        }

        return ServiceZone::where('status', 'active')
            ->get()
            ->contains(fn (ServiceZone $zone) => $zone->display_name === $serviceArea)
                ? $serviceArea
                : null;
    }
}
