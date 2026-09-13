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
