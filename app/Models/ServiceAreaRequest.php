<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class ServiceAreaRequest extends Model
{
    protected $attributes = [
        'status' => 'pending',
    ];

    protected $fillable = [
        'area_name',
        'normalized_area_name',
        'barangay',
        'address',
        'latitude',
        'longitude',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'reviewed_at' => 'datetime',
        ];
    }

    public static function normalizeAreaName(string $areaName): string
    {
        return Str::lower(preg_replace('/\s+/u', ' ', trim($areaName)) ?? trim($areaName));
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'approved' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
            default => 'bg-yellow-100 text-yellow-800',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status === 'pending' ? 'Pending Review' : ucfirst($this->status);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'model');
    }
}
