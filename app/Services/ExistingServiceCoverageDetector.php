<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ServiceAreaRequest;
use App\Models\ServiceZone;
use App\Models\Truck;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ExistingServiceCoverageDetector
{
    private const EARTH_RADIUS_METERS = 6371008.8;

    /**
     * @return array{status: string, candidates: Collection<int, array<string, mixed>>}
     */
    public function detect(ServiceAreaRequest $request, ?int $serviceZoneId = null, bool $lockForUpdate = false): array
    {
        if (! config('cleanify.smart_service_coverage.enabled', false)) {
            return ['status' => 'disabled', 'candidates' => collect()];
        }

        $zonesQuery = ServiceZone::query()
            ->where('status', 'active')
            ->when($serviceZoneId !== null, fn (Builder $query) => $query->whereKey($serviceZoneId));
        $this->applyLock($zonesQuery, $lockForUpdate);
        $zones = $zonesQuery->get()->keyBy(fn (ServiceZone $zone) => $zone->display_name);

        $trucksQuery = Truck::query()
            ->whereIn('route', $zones->keys())
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotNull('coverage_radius_meters');
        $this->applyLock($trucksQuery, $lockForUpdate);
        $trucks = $trucksQuery->get();

        $schedulesQuery = Schedule::query()
            ->where('status', 'active')
            ->whereIn('area', $zones->keys());
        $this->applyLock($schedulesQuery, $lockForUpdate);
        $schedules = $schedulesQuery->get()->groupBy('area');

        return $this->detectAgainst($request, $zones, $trucks, $schedules);
    }

    /**
     * Calculate recommendations for a page of requests with three shared queries.
     *
     * @param  Collection<int, ServiceAreaRequest>  $requests
     * @return array<int, array{status: string, candidates: Collection<int, array<string, mixed>>}>
     */
    public function detectMany(Collection $requests): array
    {
        if (! config('cleanify.smart_service_coverage.enabled', false)) {
            return $requests->mapWithKeys(fn (ServiceAreaRequest $request) => [
                $request->id => ['status' => 'disabled', 'candidates' => collect()],
            ])->all();
        }

        $zones = ServiceZone::query()->where('status', 'active')->get()
            ->keyBy(fn (ServiceZone $zone) => $zone->display_name);
        $trucks = $zones->isEmpty()
            ? collect()
            : Truck::query()
                ->whereIn('route', $zones->keys())
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereNotNull('coverage_radius_meters')
                ->get();
        $schedules = $zones->isEmpty()
            ? collect()
            : Schedule::query()
                ->where('status', 'active')
                ->whereIn('area', $zones->keys())
                ->get()
                ->groupBy('area');

        return $requests->mapWithKeys(fn (ServiceAreaRequest $request) => [
            $request->id => $this->detectAgainst($request, $zones, $trucks, $schedules),
        ])->all();
    }

    /**
     * @param  Collection<string, ServiceZone>  $zones
     * @param  Collection<int, Truck>  $trucks
     * @param  Collection<string, Collection<int, Schedule>>  $schedules
     * @return array{status: string, candidates: Collection<int, array<string, mixed>>}
     */
    private function detectAgainst(
        ServiceAreaRequest $request,
        Collection $zones,
        Collection $trucks,
        Collection $schedules
    ): array {
        if (! $this->validCoordinates($request->latitude, $request->longitude)) {
            return ['status' => 'location_unavailable', 'candidates' => collect()];
        }

        if ($zones->isEmpty()) {
            return ['status' => 'no_match', 'candidates' => collect()];
        }

        $candidates = $trucks
            ->filter(fn (Truck $truck): bool => $this->validTruck($truck))
            ->map(function (Truck $truck) use ($request, $zones, $schedules): ?array {
                /** @var ServiceZone|null $zone */
                $zone = $zones->get($truck->route);
                if (! $zone) {
                    return null;
                }

                $distance = $this->distanceMeters(
                    (float) $request->latitude,
                    (float) $request->longitude,
                    (float) $truck->latitude,
                    (float) $truck->longitude
                );

                if ($distance > $truck->coverage_radius_meters) {
                    return null;
                }

                $activeSchedules = $schedules->get($zone->display_name, collect());
                $truckSchedule = $activeSchedules->first(fn (Schedule $schedule): bool => $schedule->truck === $truck->code);
                $hasActiveSchedule = $truckSchedule !== null;
                $isStale = $truck->last_updated === null
                    || $truck->last_updated->lt(now()->subHours(max(1, (int) config('cleanify.smart_service_coverage.stale_after_hours', 24))));

                return [
                    'service_zone_id' => $zone->id,
                    'service_zone_name' => $zone->display_name,
                    'barangay' => $zone->barangay,
                    'truck_id' => $truck->id,
                    'truck_code' => $truck->code,
                    'distance_meters' => round($distance, 2),
                    'coverage_radius_meters' => $truck->coverage_radius_meters,
                    'location_updated_at' => $truck->last_updated,
                    'is_location_stale' => $isStale,
                    'has_active_schedule' => $hasActiveSchedule,
                    'schedule_id' => $truckSchedule?->id,
                    'recommendation_status' => $hasActiveSchedule
                        ? ($isStale ? 'review_stale_location' : 'recommended')
                        : 'review_no_active_schedule',
                ];
            })
            ->filter()
            ->sort(function (array $left, array $right): int {
                return [
                    $left['has_active_schedule'] ? 0 : 1,
                    $left['distance_meters'],
                    $left['service_zone_id'],
                    $left['truck_id'],
                ] <=> [
                    $right['has_active_schedule'] ? 0 : 1,
                    $right['distance_meters'],
                    $right['service_zone_id'],
                    $right['truck_id'],
                ];
            })
            ->values();

        return [
            'status' => $candidates->isEmpty() ? 'no_match' : 'matches_found',
            'candidates' => $candidates,
        ];
    }

    public function distanceMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);
        $latitudeARadians = deg2rad($latitudeA);
        $latitudeBRadians = deg2rad($latitudeB);

        $haversine = sin($latitudeDelta / 2) ** 2
            + cos($latitudeARadians) * cos($latitudeBRadians) * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($haversine), sqrt(max(0.0, 1 - $haversine)));
    }

    private function validTruck(Truck $truck): bool
    {
        return $this->validCoordinates($truck->latitude, $truck->longitude)
            && is_int($truck->coverage_radius_meters)
            && $truck->coverage_radius_meters >= Truck::MIN_COVERAGE_RADIUS_METERS
            && $truck->coverage_radius_meters <= Truck::MAX_COVERAGE_RADIUS_METERS
            && is_string($truck->route)
            && $truck->route !== '';
    }

    private function validCoordinates(mixed $latitude, mixed $longitude): bool
    {
        return is_numeric($latitude)
            && is_numeric($longitude)
            && (float) $latitude >= -90
            && (float) $latitude <= 90
            && (float) $longitude >= -180
            && (float) $longitude <= 180;
    }

    private function applyLock(Builder $query, bool $lockForUpdate): void
    {
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }
    }
}
