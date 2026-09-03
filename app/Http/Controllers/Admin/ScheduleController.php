<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreScheduleRequest;
use App\Http\Requests\Admin\UpdateScheduleRequest;
use App\Models\Schedule;
use App\Models\ServiceZone;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $query = Schedule::query();

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('area', 'like', "%{$search}%")
                  ->orWhere('days', 'like', "%{$search}%")
                  ->orWhere('truck', 'like', "%{$search}%")
                  ->orWhere('schedule_type', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status && in_array($request->status, ['active', 'pending', 'inactive'])) {
            $query->where('status', $request->status);
        }

        $schedules = $query->orderBy('area', 'asc')->paginate(10);

        // Statistics
        $activeSchedules = Schedule::where('status', 'active')->count();
        $pendingSchedules = Schedule::where('status', 'pending')->count();
        $inactiveSchedules = Schedule::where('status', 'inactive')->count();
        $trucksAssigned = Schedule::where('status', 'active')->distinct('truck')->count('truck');

        // New schedule assignments use active Service Zone database records only.
        $zones = ServiceZone::where('status', 'active')->orderBy('name')->get()->map->display_name
            ->values()->all();

        // Get trucks from database
        $trucks = Truck::orderBy('code', 'asc')->get();

        return view('admin.schedule', [
            'activePage' => 'schedule',
            'schedules' => $schedules,
            'activeSchedules' => $activeSchedules,
            'pendingSchedules' => $pendingSchedules,
            'inactiveSchedules' => $inactiveSchedules,
            'trucksAssigned' => $trucksAssigned,
            'search' => $request->search ?? '',
            'statusFilter' => $request->status ?? '',
            'zones' => $zones,
            'trucks' => $trucks,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        $schedule = Schedule::create([
            'area' => $request->area,
            'schedule_type' => $request->schedule_type,
            'specific_date' => $request->schedule_type === 'specific_date' ? $request->specific_date : null,
            'days' => $request->schedule_type === 'recurring' ? $request->days : null,
            'time_start' => $request->time_start,
            'time_end' => $request->time_end,
            'truck' => $request->truck,
            'status' => $request->status,
        ]);

        // Notify users in the schedule area
        $this->notifyUsersAboutSchedule($schedule);

        return redirect()->route('admin.schedule')
            ->with('success', 'Schedule created successfully!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateScheduleRequest $request, string $id): RedirectResponse
    {
        $schedule = Schedule::findOrFail($id);
        $oldArea = $schedule->area;
        $areaChanged = $oldArea !== $request->area;

        $schedule->update([
            'area' => $request->area,
            'schedule_type' => $request->schedule_type,
            'specific_date' => $request->schedule_type === 'specific_date' ? $request->specific_date : null,
            'days' => $request->schedule_type === 'recurring' ? $request->days : null,
            'time_start' => $request->time_start,
            'time_end' => $request->time_end,
            'truck' => $request->truck,
            'status' => $request->status,
        ]);

        // Notify users if area changed or schedule was activated
        if ($areaChanged || ($schedule->status === 'active' && $schedule->wasChanged('status'))) {
            $this->notifyUsersAboutSchedule($schedule);
        }

        return redirect()->route('admin.schedule')
            ->with('success', 'Schedule updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        $schedule = Schedule::findOrFail($id);
        $schedule->delete();

        return redirect()->route('admin.schedule')
            ->with('success', 'Schedule deleted successfully!');
    }

    /**
     * Notify users about a new or updated schedule.
     */
    protected function notifyUsersAboutSchedule(Schedule $schedule): void
    {
        if ($schedule->status !== 'active') {
            return; // Only notify for active schedules
        }

        // Get users in the schedule area
        $users = User::where('service_area', $schedule->area)
            ->where('is_admin', false)
            ->get();

        foreach ($users as $user) {
            // Check if user has schedule notifications enabled
            $preferences = $user->notification_preferences ?? [];
            $scheduleEnabled = $preferences['schedule'] ?? true;

            if ($scheduleEnabled) {
                try {
                    $user->notify(new \App\Notifications\ScheduleCreatedNotification($schedule));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to notify user about schedule: ' . $e->getMessage());
                }
            }
        }
    }
}
