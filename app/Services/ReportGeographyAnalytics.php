<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportGeographyAnalytics
{
    public const HOTSPOT_RADIUS_METERS = 100.0;

    public const FREQUENT_HOTSPOT_MINIMUM = 2;

    private const EARTH_RADIUS_METERS = 6371000.0;

    public function barangayInsightsQuery(): Builder
    {
        $barangaySql = $this->normalizedSql('barangay');

        return Report::query()
            ->whereNotNull('barangay')
            ->whereRaw("TRIM(barangay) <> ''")
            ->selectRaw("{$barangaySql} as barangay_key")
            ->selectRaw('MIN(TRIM(barangay)) as barangay_name')
            ->selectRaw('COUNT(*) as total_reports')
            ->selectRaw("SUM(CASE WHEN status <> 'rejected' THEN 1 ELSE 0 END) as qualifying_reports")
            ->selectRaw('COUNT(DISTINCT user_id) as unique_reporters')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reports")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_reports")
            ->selectRaw("SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_reports")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_reports")
            ->selectRaw('MAX(created_at) as latest_report_at')
            ->groupByRaw($barangaySql)
            ->orderByDesc('qualifying_reports')
            ->orderByDesc('latest_report_at')
            ->orderBy('barangay_key');
    }

    public function reportsForBarangay(string $barangayKey): Collection
    {
        return Report::query()
            ->whereRaw($this->normalizedSql('barangay').' = ?', [$barangayKey])
            ->get([
                'id',
                'user_id',
                'barangay',
                'location',
                'latitude',
                'longitude',
                'status',
                'created_at',
            ]);
    }

    /**
     * Derive deterministic connected components for valid GPS reports, then
     * exact normalized-landmark groups for every report without valid GPS.
     * Each input report is assigned to exactly one returned hotspot.
     */
    public function hotspots(Collection $reports): Collection
    {
        $ordered = $reports->sortBy('id')->values();
        [$gpsReports, $fallbackReports] = $ordered->partition(fn (Report $report) => $this->hasValidCoordinates($report));
        $hotspots = collect();

        if ($gpsReports->isNotEmpty()) {
            $parents = range(0, $gpsReports->count() - 1);

            for ($left = 0; $left < $gpsReports->count(); $left++) {
                for ($right = $left + 1; $right < $gpsReports->count(); $right++) {
                    if ($this->distanceInMeters($gpsReports[$left], $gpsReports[$right]) <= self::HOTSPOT_RADIUS_METERS) {
                        $this->union($parents, $left, $right);
                    }
                }
            }

            $components = [];
            foreach ($gpsReports as $index => $report) {
                $root = $this->find($parents, $index);
                $components[$root][] = $report;
            }

            ksort($components);
            foreach ($components as $members) {
                $hotspots->push($this->makeHotspot('gps', collect($members)));
            }
        }

        $fallbackReports
            ->groupBy(fn (Report $report) => $this->normalize($report->location) ?? '')
            ->sortKeys()
            ->each(fn (Collection $members) => $hotspots->push($this->makeHotspot('landmark', $members)));

        return $hotspots->sort(function (object $left, object $right): int {
            return ($right->is_frequent <=> $left->is_frequent)
                ?: ($right->qualifying_reports <=> $left->qualifying_reports)
                ?: ($right->latest_report_at->getTimestamp() <=> $left->latest_report_at->getTimestamp())
                ?: strcmp($left->label_key, $right->label_key)
                ?: strcmp($left->id, $right->id);
        })->values();
    }

    public function normalize(mixed $value, int $maximumLength = 255): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($value));

        return filled($normalized) ? mb_strtolower(mb_substr($normalized, 0, $maximumLength)) : null;
    }

    public function normalizedSql(string $column): string
    {
        if (! in_array($column, ['barangay', 'location'], true)) {
            throw new \InvalidArgumentException('Unsupported report geography column.');
        }

        $expression = $column;
        for ($pass = 0; $pass < 8; $pass++) {
            $expression = "REPLACE({$expression}, '  ', ' ')";
        }

        return "LOWER(TRIM({$expression}))";
    }

    private function makeHotspot(string $basis, Collection $reports): object
    {
        $reports = $reports->sortBy('id')->values();
        $qualifyingReports = $reports->where('status', '!=', Report::STATUS_REJECTED)->count();
        [$label, $labelKey] = $this->hotspotLabel($reports);
        $latest = $reports->max('created_at');
        $lowestId = (int) $reports->min('id');
        $identifier = $basis === 'gps'
            ? "gps-{$lowestId}"
            : 'landmark-'.hash('sha256', $labelKey);

        $gpsReports = $reports->filter(fn (Report $report) => $this->hasValidCoordinates($report));

        return (object) [
            'id' => $identifier,
            'basis' => $basis,
            'basis_label' => $basis === 'gps' ? 'GPS' : 'Landmark',
            'label' => $label,
            'label_key' => $labelKey,
            'report_ids' => $reports->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'reports' => $reports,
            'total_reports' => $reports->count(),
            'qualifying_reports' => $qualifyingReports,
            'unique_reporters' => $reports->pluck('user_id')->filter()->unique()->count(),
            'pending_reports' => $reports->where('status', Report::STATUS_PENDING)->count(),
            'in_progress_reports' => $reports->where('status', Report::STATUS_IN_PROGRESS)->count(),
            'resolved_reports' => $reports->where('status', Report::STATUS_RESOLVED)->count(),
            'rejected_reports' => $reports->where('status', Report::STATUS_REJECTED)->count(),
            'latest_report_at' => $latest,
            'is_frequent' => $qualifyingReports >= self::FREQUENT_HOTSPOT_MINIMUM,
            'latitude' => $gpsReports->isEmpty() ? null : $gpsReports->avg(fn (Report $report) => (float) $report->latitude),
            'longitude' => $gpsReports->isEmpty() ? null : $gpsReports->avg(fn (Report $report) => (float) $report->longitude),
        ];
    }

    /** @return array{0: string, 1: string} */
    private function hotspotLabel(Collection $reports): array
    {
        $candidates = $reports
            ->filter(fn (Report $report) => $this->normalize($report->location) !== null)
            ->groupBy(fn (Report $report) => $this->normalize($report->location))
            ->map(function (Collection $matches, string $key): object {
                $newest = $matches->sort(function (Report $left, Report $right): int {
                    return ($right->created_at->getTimestamp() <=> $left->created_at->getTimestamp())
                        ?: ($right->id <=> $left->id);
                })->first();

                return (object) [
                    'key' => $key,
                    'label' => trim($newest->location),
                    'count' => $matches->count(),
                    'latest' => $matches->max('created_at'),
                ];
            })
            ->sort(function (object $left, object $right): int {
                return ($right->count <=> $left->count)
                    ?: ($right->latest->getTimestamp() <=> $left->latest->getTimestamp())
                    ?: strcmp($left->key, $right->key);
            });

        $selected = $candidates->first();

        return $selected
            ? [$selected->label, $selected->key]
            : ['Location not recorded', ''];
    }

    private function hasValidCoordinates(Report $report): bool
    {
        if (! is_numeric($report->latitude) || ! is_numeric($report->longitude)) {
            return false;
        }

        $latitude = (float) $report->latitude;
        $longitude = (float) $report->longitude;

        return $latitude >= -90.0 && $latitude <= 90.0
            && $longitude >= -180.0 && $longitude <= 180.0;
    }

    private function distanceInMeters(Report $left, Report $right): float
    {
        $leftLatitude = deg2rad((float) $left->latitude);
        $rightLatitude = deg2rad((float) $right->latitude);
        $latitudeDelta = $rightLatitude - $leftLatitude;
        $longitudeDelta = deg2rad((float) $right->longitude - (float) $left->longitude);

        $haversine = sin($latitudeDelta / 2) ** 2
            + cos($leftLatitude) * cos($rightLatitude) * sin($longitudeDelta / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt($haversine)));
    }

    /** @param array<int, int> $parents */
    private function find(array &$parents, int $index): int
    {
        if ($parents[$index] !== $index) {
            $parents[$index] = $this->find($parents, $parents[$index]);
        }

        return $parents[$index];
    }

    /** @param array<int, int> $parents */
    private function union(array &$parents, int $left, int $right): void
    {
        $leftRoot = $this->find($parents, $left);
        $rightRoot = $this->find($parents, $right);

        if ($leftRoot !== $rightRoot) {
            $parents[max($leftRoot, $rightRoot)] = min($leftRoot, $rightRoot);
        }
    }
}
