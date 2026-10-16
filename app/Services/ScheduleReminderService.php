<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\Carbon;
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

        if ($schedule->usesIntervalRecurrence()) {
            $candidate = Carbon::instance($date)->copy()->startOfDay();
            $start = Carbon::createFromFormat(
                'Y-m-d',
                $schedule->recurrence_start_date->format('Y-m-d'),
                $candidate->timezone
            )->startOfDay();

            return $candidate->greaterThanOrEqualTo($start)
                && (int) $start->diffInDays($candidate) % $schedule->recurrence_interval_days === 0;
        }

        $days = preg_split('/\s*(?:,|&|and)\s*/i', (string) $schedule->days) ?: [];

        return collect($days)
            ->map(fn (string $day) => strtolower(trim($day)))
            ->contains(strtolower($date->englishDayOfWeek));
    }

    public function nextOccurrence(Schedule $schedule, ?CarbonInterface $reference = null): ?Carbon
    {
        return $this->nextOccurrences($schedule, $reference)->first();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function upcomingForArea(?string $area, int $limit = 5, ?CarbonInterface $reference = null): Collection
    {
        if (! $area) {
            return collect();
        }

        $reference = $reference ? Carbon::instance($reference)->copy() : Carbon::now();
        $schedules = Schedule::where('status', 'active')->where('area', $area)->get();

        return $schedules
            ->flatMap(function (Schedule $schedule) use ($reference): Collection {
                return $this->nextOccurrences($schedule, $reference)
                    ->map(fn (CarbonInterface $collectionAt) => $this->pickupPayload($schedule, $collectionAt));
            })
            ->sortBy('collection_at')
            ->take($limit)
            ->values();
    }

    /** @return array<string, mixed> */
    public function schedulePayload(Schedule $schedule, ?CarbonInterface $reference = null): array
    {
        $nextOccurrence = $this->nextOccurrence($schedule, $reference);

        return [
            'id' => $schedule->id,
            'area' => $schedule->area,
            'schedule_type' => $schedule->schedule_type,
            'specific_date' => $schedule->specific_date?->format('Y-m-d'),
            'recurrence_start_date' => $schedule->recurrence_start_date?->format('Y-m-d'),
            'recurrence_interval_days' => $schedule->recurrence_interval_days,
            'days' => $schedule->days,
            'time_start' => $schedule->time_start?->format('H:i'),
            'time_end' => $schedule->time_end?->format('H:i'),
            'time_range' => $schedule->time_range,
            'truck' => $schedule->truck,
            'status' => $schedule->status,
            'next_collection_at' => $nextOccurrence?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function pickupPayload(Schedule $schedule, CarbonInterface $collectionAt): array
    {
        return [
            'schedule_id' => $schedule->id,
            'area' => $schedule->area,
            'schedule_type' => $schedule->schedule_type,
            'collection_at' => $collectionAt->toIso8601String(),
            'date_display' => $collectionAt->format('l, F j'),
            'time_display' => $collectionAt->format('g:i A'),
            'time_range' => $schedule->time_range,
            'truck' => $schedule->truck,
            'status' => $schedule->status,
        ];
    }

    /** @return Collection<int, Carbon> */
    public function nextOccurrences(Schedule $schedule, ?CarbonInterface $reference = null, int $limit = 5): Collection
    {
        $reference = $reference ? Carbon::instance($reference)->copy() : Carbon::now();

        if ($limit < 1) {
            return collect();
        }

        if ($schedule->usesIntervalRecurrence()) {
            $interval = $schedule->recurrence_interval_days;
            $start = Carbon::createFromFormat(
                'Y-m-d',
                $schedule->recurrence_start_date->format('Y-m-d'),
                $reference->timezone
            )->startOfDay();
            $elapsedDays = $start->greaterThan($reference->copy()->startOfDay())
                ? 0
                : (int) $start->diffInDays($reference->copy()->startOfDay());
            $candidate = $start->copy()->addDays(intdiv($elapsedDays, $interval) * $interval);

            if ($schedule->time_start) {
                $candidate->setTimeFromTimeString($schedule->time_start->format('H:i:s'));
            }

            while ($this->occurrenceHasFinished($schedule, $candidate, $reference)) {
                $candidate->addDays($interval);
            }

            return collect(range(0, $limit - 1))
                ->map(fn (int $offset) => $candidate->copy()->addDays($offset * $interval));
        }

        $occurrences = collect();
        $includedDays = [];

        for ($offset = 0; $offset <= 7; $offset++) {
            $candidate = $reference->copy()->startOfDay()->addDays($offset);

            if (! $this->occursOn($schedule, $candidate)) {
                continue;
            }

            $dayKey = strtolower($candidate->englishDayOfWeek);
            if ($schedule->schedule_type !== 'specific_date' && isset($includedDays[$dayKey])) {
                continue;
            }

            if ($schedule->time_start) {
                $candidate->setTimeFromTimeString($schedule->time_start->format('H:i:s'));
            }

            if (! $this->occurrenceHasFinished($schedule, $candidate, $reference)) {
                $occurrences->push($candidate);
                $includedDays[$dayKey] = true;
            }

            if ($schedule->schedule_type === 'specific_date') {
                break;
            }
        }

        return $occurrences->take($limit)->values();
    }

    private function occurrenceHasFinished(
        Schedule $schedule,
        CarbonInterface $occurrence,
        CarbonInterface $reference
    ): bool {
        $end = Carbon::instance($occurrence)->copy();

        if ($schedule->time_end) {
            $end->setTimeFromTimeString($schedule->time_end->format('H:i:s'));
        }

        return $end->lessThan($reference);
    }
}
