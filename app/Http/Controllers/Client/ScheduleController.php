<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\ScheduleReminderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleReminderService $scheduleReminders) {}

    /**
     * Display the garbage schedule page with enhanced data.
     */
    public function index(): View
    {
        $user = $this->currentUser();
        $userArea = $user->service_area && ServiceZone::where('status', 'active')
            ->get()
            ->contains(fn (ServiceZone $zone) => $zone->display_name === $user->service_area)
                ? $user->service_area
                : null;
        $schedules = $userArea
            ? Schedule::where('status', 'active')
                ->where('area', $userArea)
                ->orderBy('schedule_type')
                ->orderBy('specific_date')
                ->get()
            : collect();

        return view('garbage-schedule', [
            'activePage' => 'schedule',
            'schedules' => $schedules,
            'userArea' => $userArea,
            'nextPickup' => $this->getNextPickupForArea($schedules, $userArea),
            'upcomingPickups' => $this->getUpcomingPickups($schedules, 5),
            'scheduleRemindersEnabled' => $user->notificationPreferenceEnabled('schedule_reminders', 'schedule'),
            'emailNotificationsEnabled' => (bool) $user->email_notifications,
        ]);
    }

    /**
     * Update the user's preferred service area.
     */
    public function updateServiceArea(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_area' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $this->currentUser();
        $user->service_area = $validated['service_area'] ?: null;
        $user->save();

        $schedules = Schedule::where('status', 'active')->orderBy('area')->get();

        return response()->json([
            'service_area' => $user->service_area,
            'next_pickup' => $this->getNextPickupForArea($schedules, $user->service_area),
        ]);
    }

    /**
     * Update reminder notification preferences.
     */
    public function updateNotifications(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_notifications' => ['required', 'boolean'],
            'sms_notifications' => ['required', 'boolean'],
            'push_notifications' => ['required', 'boolean'],
        ]);

        $user = $this->currentUser();
        $user->fill($validated)->save();

        return response()->json([
            'message' => 'Notification preferences updated.',
        ]);
    }

    /**
     * Determine the next pickup for a specific area.
     */
    protected function getNextPickupForArea($schedules, ?string $area): ?array
    {
        if (! $area) {
            return null;
        }

        // Get all matching schedules for the area (both recurring and specific date)
        $matchingSchedules = $schedules->where('area', $area);
        if ($matchingSchedules->isEmpty()) {
            return null;
        }

        $reference = Carbon::now();
        $nextPickup = null;
        $nextDate = null;

        foreach ($matchingSchedules as $schedule) {
            $scheduleDate = $this->scheduleReminders->nextOccurrence($schedule, $reference);
            if ($scheduleDate && (! $nextDate || $scheduleDate->lessThan($nextDate))) {
                $nextDate = $scheduleDate;
                $nextPickup = $schedule;
            }
        }

        if (! $nextDate || ! $nextPickup) {
            return null;
        }

        $wasteType = $this->determineWasteType($nextPickup);

        return [
            'area' => $nextPickup->area,
            'datetime_iso' => $nextDate->toIso8601String(),
            'date_display' => $nextDate->format('l, F j'),
            'time_display' => $nextDate->format('g:i A'),
            'time_range' => $nextPickup->time_range,
            'truck' => $nextPickup->truck,
            'waste_type' => $wasteType,
            'badge' => $this->wasteBadgeClass($wasteType),
        ];
    }

    /**
     * Build a list of the next N pickups across all schedules.
     */
    protected function getUpcomingPickups($schedules, int $limit = 5): array
    {
        $reference = Carbon::now();
        $entries = $schedules->flatMap(function (Schedule $schedule) use ($reference, $limit) {
            return $this->scheduleReminders->nextOccurrences($schedule, $reference, $limit)
                ->map(function (Carbon $nextDate) use ($schedule): array {
                    $wasteType = $this->determineWasteType($schedule);

                    return [
                        'area' => $schedule->area,
                        'day_label' => $nextDate->format('l'),
                        'date_display' => $nextDate->format('M d'),
                        'time_display' => $nextDate->format('g:i A'),
                        'time_range' => $schedule->time_range,
                        'truck' => $schedule->truck,
                        'waste_type' => $wasteType,
                        'badge' => $this->wasteBadgeClass($wasteType),
                        'datetime_iso' => $nextDate->toIso8601String(),
                    ];
                });
        });

        return $entries
            ->sortBy('datetime_iso')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Infer waste type (for color coding) from schedule text.
     */
    protected function determineWasteType(Schedule $schedule): string
    {
        $text = strtolower(($schedule->days ?? '').' '.$schedule->area);

        return match (true) {
            str_contains($text, 'biodegradable') => 'Biodegradable',
            str_contains($text, 'non') => 'Non-biodegradable',
            str_contains($text, 'recycl') => 'Recyclables',
            default => 'General',
        };
    }

    protected function wasteBadgeClass(string $type): string
    {
        return match ($type) {
            'Biodegradable' => 'bg-lime-100 text-lime-800',
            'Non-biodegradable' => 'bg-amber-100 text-amber-800',
            'Recyclables' => 'bg-cyan-100 text-cyan-800',
            default => 'bg-gray-100 text-gray-700',
        };
    }

    /**
     * Ensure we always get an App\Models\User instance.
     */
    protected function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        return $user;
    }
}
