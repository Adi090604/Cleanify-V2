<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'area',
        'schedule_type',
        'specific_date',
        'recurrence_start_date',
        'recurrence_interval_days',
        'days',
        'time_start',
        'time_end',
        'truck',
        'status',
    ];

    protected $casts = [
        'specific_date' => 'date',
        'recurrence_start_date' => 'date',
        'recurrence_interval_days' => 'integer',
        'time_start' => 'datetime:H:i',
        'time_end' => 'datetime:H:i',
    ];

    public function usesIntervalRecurrence(): bool
    {
        return $this->schedule_type === 'recurring'
            && $this->recurrence_start_date !== null
            && ($this->recurrence_interval_days ?? 0) > 0;
    }

    public function getCollectionLabelAttribute(): string
    {
        if ($this->schedule_type === 'specific_date') {
            return $this->specific_date?->format('M d, Y') ?? 'Date not set';
        }

        if ($this->usesIntervalRecurrence()) {
            $unit = $this->recurrence_interval_days === 1 ? 'day' : 'days';

            return "Every {$this->recurrence_interval_days} {$unit} from {$this->recurrence_start_date->format('M d, Y')}";
        }

        return $this->days ?: 'Days not set';
    }

    /**
     * Get the formatted time range.
     */
    public function getTimeRangeAttribute(): string
    {
        if (! $this->time_start || ! $this->time_end) {
            return 'All day';
        }

        $start = $this->time_start instanceof \DateTime ? $this->time_start : new \DateTime($this->time_start);
        $end = $this->time_end instanceof \DateTime ? $this->time_end : new \DateTime($this->time_end);

        return $start->format('g:i A').' - '.$end->format('g:i A');
    }

    /**
     * Get the status badge class.
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'active' => 'bg-green-100 text-green-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'inactive' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get the formatted status.
     */
    public function getFormattedStatusAttribute(): string
    {
        return ucfirst($this->status);
    }
}
