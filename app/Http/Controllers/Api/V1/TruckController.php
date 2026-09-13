<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ServiceZone;
use App\Models\Truck;
use Illuminate\Http\JsonResponse;

class TruckController extends Controller
{
    public function index(): JsonResponse
    {
        $trucks = Truck::query()->orderBy('code')->get();
        $located = $trucks->filter(fn (Truck $truck) => $truck->latitude !== null && $truck->longitude !== null);
        $zones = ServiceZone::query()
            ->where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->get();

        return response()->json([
            'trucks' => $trucks->map(fn (Truck $truck) => [
                'id' => $truck->id,
                'code' => $truck->code,
                'driver' => $truck->driver,
                'route' => $truck->route,
                'status' => $truck->status,
                'formatted_status' => $truck->formatted_status,
                'latitude' => $truck->latitude !== null ? (float) $truck->latitude : null,
                'longitude' => $truck->longitude !== null ? (float) $truck->longitude : null,
                'last_updated' => $truck->last_updated?->toIso8601String(),
                'last_updated_human' => $truck->last_updated_human,
            ])->values(),
            'map' => [
                'center' => [
                    'latitude' => $located->isNotEmpty() ? (float) $located->avg('latitude') : 9.7870,
                    'longitude' => $located->isNotEmpty() ? (float) $located->avg('longitude') : 125.4928,
                ],
                'zoom' => 13,
            ],
            'zones' => $zones->map(fn (ServiceZone $zone) => [
                'id' => $zone->id,
                'name' => $zone->display_name,
                'latitude' => (float) $zone->latitude,
                'longitude' => (float) $zone->longitude,
            ])->values(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function routeHistory(Truck $truck): JsonResponse
    {
        return response()->json([
            'truck_id' => $truck->id,
            'truck_code' => $truck->code,
            'locations' => $truck->recentLocations()->get()->map(fn ($location) => [
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'recorded_at' => $location->recorded_at->toIso8601String(),
            ])->values(),
        ]);
    }
}
