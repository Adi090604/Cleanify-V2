<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTruckRequest;
use App\Http\Requests\Admin\UpdateTruckRequest;
use App\Models\Truck;
use App\Models\TruckLocation;
use App\Models\ServiceZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $trucks = Truck::orderBy('code', 'asc')->get();
        
        // Service-zone assignments are sourced exclusively from active database records.
        $serviceZones = ServiceZone::where('status', 'active')->orderBy('name')->get();
        $managedRoutes = $serviceZones->map->display_name->all();
        $allRoutes = array_unique($managedRoutes);
        sort($allRoutes);
        $serviceZoneCoordinates = $serviceZones->filter->hasCoordinates()->mapWithKeys(fn (ServiceZone $zone) => [$zone->display_name => [
            'lat' => (float) $zone->latitude,
            'lng' => (float) $zone->longitude,
        ]])->all();

        return view('admin.tracker', [
            'activePage' => 'tracker',
            'trucks' => $trucks,
            'routes' => $allRoutes,
            'serviceZoneCoordinates' => $serviceZoneCoordinates,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTruckRequest $request): RedirectResponse
    {
        Truck::create([
            'code' => $request->code,
            'driver' => $request->driver,
            'route' => $request->route,
            'status' => $request->status,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'last_updated' => $request->latitude && $request->longitude ? now() : null,
        ]);

        return redirect()->route('admin.tracker')
            ->with('success', 'Truck added successfully!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTruckRequest $request, string $id): RedirectResponse
    {
        $truck = Truck::findOrFail($id);

        $truck->update([
            'code' => $request->code,
            'driver' => $request->driver,
            'route' => $request->route,
            'status' => $request->status,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'last_updated' => ($request->latitude && $request->longitude) ? now() : $truck->last_updated,
        ]);

        return redirect()->route('admin.tracker')
            ->with('success', 'Truck updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        $truck = Truck::findOrFail($id);
        $truck->delete();

        return redirect()->route('admin.tracker')
            ->with('success', 'Truck deleted successfully!');
    }

    /**
     * Update truck location.
     */
    public function updateLocation(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $truck = Truck::findOrFail($id);
        
        $latitude = round($request->latitude, 8);
        $longitude = round($request->longitude, 8);
        $now = now();
        
        // Update truck location
        $truck->update([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'last_updated' => $now,
        ]);

        // Store location history (only if location actually changed)
        $lastLocation = $truck->locations()->latest('recorded_at')->first();
        if (!$lastLocation || 
            abs($lastLocation->latitude - $latitude) > 0.0001 || 
            abs($lastLocation->longitude - $longitude) > 0.0001) {
            TruckLocation::create([
                'truck_id' => $truck->id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'recorded_at' => $now,
            ]);
        }

        return redirect()->route('admin.tracker')
            ->with('success', 'Truck location updated successfully!');
    }

    /**
     * Get truck data for auto-refresh (API endpoint).
     */
    public function getData(): JsonResponse
    {
        $trucks = Truck::orderBy('code', 'asc')->get()->map(function ($truck) {
            return [
                'id' => $truck->id,
                'code' => $truck->code,
                'driver' => $truck->driver,
                'route' => $truck->route,
                'status' => $truck->status,
                'formatted_status' => $truck->formatted_status,
                'latitude' => $truck->latitude,
                'longitude' => $truck->longitude,
                'last_updated' => $truck->last_updated ? $truck->last_updated->toIso8601String() : null,
                'last_updated_iso' => $truck->last_updated ? $truck->last_updated->toIso8601String() : null,
                'last_updated_human' => $truck->last_updated ? $truck->last_updated->diffForHumans() : 'Never',
            ];
        });

        return response()->json([
            'trucks' => $trucks,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get route history for a truck.
     */
    public function getRouteHistory(string $id): JsonResponse
    {
        $truck = Truck::findOrFail($id);
        
        // Get locations from last 24 hours
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
