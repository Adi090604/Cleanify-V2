<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\ServiceZone;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    /**
     * Display the garbage schedule page with enhanced data.
     */
    public function index(): View
    {
        $schedules = Schedule::where('status', 'active')
            ->orderBy('area')
            ->get();

        $user = $this->currentUser();
        $availableAreas = ServiceZone::where('status', 'active')->orderBy('name')->get()->map->display_name
            ->merge($schedules->pluck('area'))->unique()->values();
        $userArea = $user->service_area ?: ($availableAreas->first() ?? null);

        // Generate zone colors
        $zoneColors = [];
        foreach ($availableAreas as $area) {
            $zoneColors[$area] = $this->getZoneColor($area);
        }

        return view('garbage-schedule', [
            'activePage' => 'schedule',
            'schedules' => $schedules,
            'availableAreas' => $availableAreas,
            'userArea' => $userArea,
            'nextPickup' => $this->getNextPickupForArea($schedules, $userArea),
            'upcomingPickups' => $this->getUpcomingPickups($schedules, 5),
            'notificationSettings' => [
                'email' => (bool) $user->email_notifications,
                'sms' => (bool) $user->sms_notifications,
                'push' => (bool) $user->push_notifications,
            ],
            'zoneColors' => $zoneColors,
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
        if (!$area) {
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
            $scheduleDate = $this->calculateNextOccurrence($schedule, $reference);
            if ($scheduleDate && (!$nextDate || $scheduleDate->lessThan($nextDate))) {
                $nextDate = $scheduleDate;
                $nextPickup = $schedule;
            }
        }

        if (!$nextDate || !$nextPickup) {
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
        $entries = [];

        foreach ($schedules as $schedule) {
            if ($schedule->schedule_type === 'specific_date') {
                // Handle specific date schedules
                if ($schedule->specific_date && $schedule->specific_date->greaterThanOrEqualTo($reference->startOfDay())) {
                    $nextDate = Carbon::parse($schedule->specific_date);
                    if ($schedule->time_start) {
                        $nextDate->setTimeFromTimeString($schedule->time_start);
                    }
                    if ($nextDate->greaterThanOrEqualTo($reference)) {
                        $wasteType = $this->determineWasteType($schedule);
                        $entries[] = [
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
                    }
                }
            } else {
                // Handle recurring schedules
                if ($schedule->days) {
                    $dayIndexes = $this->parseDayIndexes($schedule->days);
                    foreach ($dayIndexes as $dayIndex) {
                        $nextDate = $this->calculateNextOccurrence($schedule, $reference, $dayIndex);
                        if ($nextDate) {
                            $wasteType = $this->determineWasteType($schedule);
                            $entries[] = [
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
                        }
                    }
                }
            }
        }

        return collect($entries)
            ->sortBy('datetime_iso')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Calculate the next occurrence for a schedule and optional specific day.
     */
    protected function calculateNextOccurrence(Schedule $schedule, ?Carbon $reference = null, ?int $specificDayIndex = null): ?Carbon
    {
        $reference = $reference ? $reference->copy() : Carbon::now();

        // Handle specific date schedules
        if ($schedule->schedule_type === 'specific_date' && $schedule->specific_date) {
            $target = Carbon::parse($schedule->specific_date);
            
            // Only return if the date is today or in the future
            if ($target->lessThan($reference->startOfDay())) {
                return null; // Past date, skip
            }

            if ($schedule->time_start) {
                $target->setTimeFromTimeString($schedule->time_start);
            }

            // Only return if the datetime is in the future
            if ($target->greaterThanOrEqualTo($reference)) {
                return $target;
            }

            return null;
        }

        // Handle recurring schedules
        if (!$schedule->days) {
            return null;
        }

        $dayIndexes = $specificDayIndex !== null ? [$specificDayIndex] : $this->parseDayIndexes($schedule->days);

        $possible = collect($dayIndexes)
            ->map(function ($dayIndex) use ($reference, $schedule) {
                $target = $this->carbonForDayIndex($dayIndex, $reference);
                if (!$target) {
                    return null;
                }

                if ($schedule->time_start) {
                    $target->setTimeFromTimeString($schedule->time_start);
                }

                if ($target->lessThanOrEqualTo($reference)) {
                    $target->addWeek();
                }

                return $target;
            })
            ->filter()
            ->sort()
            ->values();

        return $possible->first();
    }

    /**
     * Convert schedule days string to an array of week day indexes.
     */
    protected function parseDayIndexes(?string $days): array
    {
        if (!$days) {
            return [];
        }

        $dayMap = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        return collect(explode(',', $days))
            ->map(function ($day) use ($dayMap) {
                $normalized = strtolower(trim($day));
                return $dayMap[$normalized] ?? null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Create a Carbon instance for the next occurrence of a day index.
     */
    protected function carbonForDayIndex(int $dayIndex, Carbon $reference): ?Carbon
    {
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        if (!isset($dayNames[$dayIndex])) {
            return null;
        }

        $target = Carbon::parse("this {$dayNames[$dayIndex]}", $reference->timezone);
        if ($target->lessThan($reference->copy()->startOfDay())) {
            $target->addWeek();
        }

        return $target;
    }

    /**
     * Infer waste type (for color coding) from schedule text.
     */
    protected function determineWasteType(Schedule $schedule): string
    {
        $text = strtolower(($schedule->days ?? '') . ' ' . $schedule->area);

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
     * Get a consistent color for a zone based on its name.
     */
    protected function getZoneColor(string $zoneName): array
    {
        // Predefined color palette - 15 distinct colors for zones
        $colors = [
            ['bg' => '#EF4444', 'text' => '#FFFFFF', 'border' => '#DC2626'], // Red
            ['bg' => '#F97316', 'text' => '#FFFFFF', 'border' => '#EA580C'], // Orange
            ['bg' => '#F59E0B', 'text' => '#FFFFFF', 'border' => '#D97706'], // Amber
            ['bg' => '#EAB308', 'text' => '#FFFFFF', 'border' => '#CA8A04'], // Yellow
            ['bg' => '#84CC16', 'text' => '#FFFFFF', 'border' => '#65A30D'], // Lime
            ['bg' => '#22C55E', 'text' => '#FFFFFF', 'border' => '#16A34A'], // Green
            ['bg' => '#10B981', 'text' => '#FFFFFF', 'border' => '#059669'], // Emerald
            ['bg' => '#14B8A6', 'text' => '#FFFFFF', 'border' => '#0D9488'], // Teal
            ['bg' => '#06B6D4', 'text' => '#FFFFFF', 'border' => '#0891B2'], // Cyan
            ['bg' => '#3B82F6', 'text' => '#FFFFFF', 'border' => '#2563EB'], // Blue
            ['bg' => '#6366F1', 'text' => '#FFFFFF', 'border' => '#4F46E5'], // Indigo
            ['bg' => '#8B5CF6', 'text' => '#FFFFFF', 'border' => '#7C3AED'], // Violet
            ['bg' => '#A855F7', 'text' => '#FFFFFF', 'border' => '#9333EA'], // Purple
            ['bg' => '#D946EF', 'text' => '#FFFFFF', 'border' => '#C026D3'], // Fuchsia
            ['bg' => '#EC4899', 'text' => '#FFFFFF', 'border' => '#DB2777'], // Pink
        ];

        // Use hash of zone name to get consistent color
        $hash = crc32($zoneName);
        $index = abs($hash) % count($colors);
        
        return $colors[$index];
    }

    /**
     * Ensure we always get an App\Models\User instance.
     */
    protected function currentUser(): User
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        return $user;
    }
}
