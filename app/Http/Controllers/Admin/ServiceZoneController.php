<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServiceZoneRequest;
use App\Http\Requests\Admin\UpdateServiceZoneRequest;
use App\Models\ServiceZone;
use App\Models\Schedule;
use App\Models\Truck;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServiceZoneController extends Controller
{
    public function index(): View
    {
        return view('admin.service-zones', [
            'activePage' => 'service-zones',
            'zones' => ServiceZone::orderBy('name')->get(),
        ]);
    }

    public function store(StoreServiceZoneRequest $request): RedirectResponse
    {
        ServiceZone::create($request->validated());

        return to_route('admin.service-zones')->with('success', 'Service zone created successfully.');
    }

    public function update(UpdateServiceZoneRequest $request, string $id): RedirectResponse
    {
        ServiceZone::findOrFail($id)->update($request->validated());

        return to_route('admin.service-zones')->with('success', 'Service zone updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $zone = ServiceZone::findOrFail($id);
        $displayName = $zone->display_name;

        if (Truck::where('route', $displayName)->exists()) {
            return to_route('admin.service-zones')
                ->with('error', 'This Service Zone is assigned to a truck and cannot be deleted. Reassign the truck first.');
        }

        if (Schedule::where('area', $displayName)->exists()) {
            return to_route('admin.service-zones')
                ->with('error', 'This Service Zone is used by a schedule and cannot be deleted. Reassign the schedule first.');
        }

        $zone->delete();

        return to_route('admin.service-zones')->with('success', 'Service zone deleted successfully.');
    }
}
