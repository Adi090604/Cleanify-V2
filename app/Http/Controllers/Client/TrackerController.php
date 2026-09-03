<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Truck;
use App\Models\ServiceZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackerController extends Controller
{
    /**
     * Show the client tracker page.
     */
    public function index(): View
    {
        $trucks = Truck::orderBy('code')->get();
        $activeTrucks = $trucks->whereNotNull('latitude')->whereNotNull('longitude');
        // Highlighting is sourced exclusively from active Service Zone database records.
        $zones = ServiceZone::query()->where('status', 'active')->whereNotNull('latitude')->whereNotNull('longitude')->get()
            ->mapWithKeys(fn (ServiceZone $zone) => [$zone->display_name => [
                'name' => $zone->name,
                'barangay' => $zone->barangay,
                'status' => $zone->status,
                'lat' => (float) $zone->latitude,
                'lng' => (float) $zone->longitude,
            ]])->all();

        $centerLat = $activeTrucks->avg('latitude') ?? 9.7870;
        $centerLng = $activeTrucks->avg('longitude') ?? 125.4928;
        $statusCounts = $trucks->groupBy('status')->map->count();

        return view('tracker', [
            'activePage' => 'tracker',
            'trucks' => $trucks,
            'statusCounts' => $statusCounts,
            'centerLat' => $centerLat,
            'centerLng' => $centerLng,
            'zones' => $zones,
        ]);
    }

    /**
     * Provide truck data for client auto-refresh.
     */
    public function getData(): JsonResponse
    {
        $trucks = Truck::orderBy('code')->get()->map(function (Truck $truck) {
            return [
                'id' => $truck->id,
                'code' => $truck->code,
                'driver' => $truck->driver,
                'route' => $truck->route,
                'status' => $truck->status,
                'formatted_status' => $truck->formatted_status,
                'status_badge' => $truck->getStatusBadgeClass(),
                'latitude' => $truck->latitude,
                'longitude' => $truck->longitude,
                'last_updated' => $truck->last_updated ? $truck->last_updated->toIso8601String() : null,
                'last_updated_human' => $truck->last_updated ? $truck->last_updated->diffForHumans() : 'Never',
            ];
        });

        return response()->json([
            'trucks' => $trucks,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Provide route history points for a truck (24-hour window).
     */
    public function routeHistory(Truck $truck): JsonResponse
    {
        $locations = $truck->recentLocations()->get()->map(function ($location) {
            return [
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'recorded_at' => $location->recorded_at->toIso8601String(),
            ];
        });

        return response()->json([
            'truck_id' => $truck->id,
            'truck_code' => $truck->code,
            'locations' => $locations,
        ]);
    }
}
