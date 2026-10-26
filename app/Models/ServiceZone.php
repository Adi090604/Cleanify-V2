<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ServiceZone extends Model
{
    protected $fillable = ['name', 'barangay', 'status', 'latitude', 'longitude'];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public static function normalizeForComparison(string $value): string
    {
        return Str::lower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));
    }

    public static function duplicateExists(string $name, string $barangay, int|string|null $exceptId = null): bool
    {
        $normalizedName = self::normalizeForComparison($name);
        $normalizedBarangay = self::normalizeForComparison($barangay);

        return self::query()
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->get(['name', 'barangay'])
            ->contains(fn (self $zone): bool => $zone->barangay !== null
                && self::normalizeForComparison($zone->name) === $normalizedName
                && self::normalizeForComparison($zone->barangay) === $normalizedBarangay);
    }

    public static function suggestNextName(iterable $names): string
    {
        $highestNumber = 0;

        foreach ($names as $name) {
            if (is_string($name) && preg_match('/^Zone (\d+)$/', $name, $matches)) {
                $highestNumber = max($highestNumber, (int) $matches[1]);
            }
        }

        return 'Zone '.($highestNumber + 1);
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function getDisplayNameAttribute(): string
    {
        if (! $this->barangay || str_contains($this->name, $this->barangay)) {
            return $this->name;
        }

        return "{$this->name} - {$this->barangay}";
    }

    public function serviceAreaRequests(): HasMany
    {
        return $this->hasMany(ServiceAreaRequest::class);
    }
}
