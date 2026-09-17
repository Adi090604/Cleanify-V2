<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceZone extends Model
{
    protected $fillable = ['name', 'barangay', 'status', 'latitude', 'longitude'];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public static function suggestNextName(iterable $names): string
    {
        $highestNumber = 0;

        foreach ($names as $name) {
            if (is_string($name) && preg_match('/^Zone (\d+)$/', $name, $matches)) {
                $highestNumber = max($highestNumber, (int) $matches[1]);
            }
        }

        return 'Zone ' . ($highestNumber + 1);
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function getDisplayNameAttribute(): string
    {
        if (!$this->barangay || str_contains($this->name, $this->barangay)) {
            return $this->name;
        }

        return "{$this->name} - {$this->barangay}";
    }

}
