<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ScheduleReminderService
{
    /** @return Collection<int, Schedule> */
    public function schedulesForDate(CarbonInterface $date): Collection
    {
        return Schedule::where('status', 'active')
            ->get()
            ->filter(fn (Schedule $schedule) => $this->occursOn($schedule, $date))
            ->values();
    }

    public function occursOn(Schedule $schedule, CarbonInterface $date): bool
    {
        if ($schedule->schedule_type === 'specific_date') {
            return $schedule->specific_date?->isSameDay($date) ?? false;
        }

        $days = preg_split('/\s*(?:,|&|and)\s*/i', (string) $schedule->days) ?: [];

        return collect($days)
            ->map(fn (string $day) => strtolower(trim($day)))
            ->contains(strtolower($date->englishDayOfWeek));
    }
}
