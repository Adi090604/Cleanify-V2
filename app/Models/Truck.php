<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Truck extends Model
{
    protected $fillable = [
        'code',
        'driver',
        'route',
        'status',
        'latitude',
        'longitude',
        'last_updated',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'last_updated' => 'datetime',
    ];

    public static function suggestNextCode(iterable $codes): string
    {
        $numbers = [];
        $widths = [];

        foreach ($codes as $code) {
            if (!is_string($code) || !preg_match('/^TRK-(\d+)$/', $code, $matches)) {
                continue;
            }

            $numbers[] = (int) $matches[1];
            $widths[] = strlen($matches[1]);
        }

        if ($numbers === []) {
            return 'TRK-01';
        }

        $uniqueWidths = array_unique($widths);
        $padding = count($uniqueWidths) === 1 ? max(2, $uniqueWidths[0]) : 2;

        return 'TRK-' . str_pad((string) (max($numbers) + 1), $padding, '0', STR_PAD_LEFT);
    }

    /**
     * Get the status badge class.
     */
    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            'active' => 'bg-green-100 text-green-800',
            'on_break' => 'bg-yellow-100 text-yellow-800',
            'offline' => 'bg-red-100 text-red-800',
            'maintenance' => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get the formatted status.
     */
    public function getFormattedStatusAttribute(): string
    {
        return match($this->status) {
            'active' => 'Active',
            'on_break' => 'On Break',
            'offline' => 'Offline',
            'maintenance' => 'Maintenance',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get human-readable time since last update.
     */
    public function getLastUpdatedHumanAttribute(): string
    {
        if (!$this->last_updated) {
            return 'Never';
        }
        return $this->last_updated->diffForHumans();
    }

    /**
     * Get the location history for this truck.
     */
    public function locations()
    {
        return $this->hasMany(TruckLocation::class)->orderBy('recorded_at', 'desc');
    }

    /**
     * Get recent locations (last 24 hours).
     */
    public function recentLocations()
    {
        return $this->hasMany(TruckLocation::class)
            ->where('recorded_at', '>=', now()->subDay())
            ->orderBy('recorded_at', 'asc');
    }

}
