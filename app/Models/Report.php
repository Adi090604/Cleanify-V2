<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    public const HOME_FEED_RESOLVED_RETENTION_HOURS = 72;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'location',
        'barangay',
        'latitude',
        'longitude',
        'description',
        'image_path',
        'status',
        'priority',
        'admin_notes',
        'rejection_reason',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Report $report) {
            if (! $report->isDirty('status')) {
                return;
            }

            $report->resolved_at = $report->status === self::STATUS_RESOLVED ? now() : null;
        });
    }

    /**
     * Reports visible in the resident Home feed.
     */
    public function scopeVisibleOnHomeFeed(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('status', '!=', self::STATUS_RESOLVED)
                ->orWhere(function (Builder $query) {
                    $query->where('status', self::STATUS_RESOLVED)
                        ->where('resolved_at', '>', now()->subHours(self::HOME_FEED_RESOLVED_RETENTION_HOURS));
                });
        });
    }

    /**
     * Get the user who created the report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who resolved the report.
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Get the status badge color class.
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'text-yellow-600',
            self::STATUS_IN_PROGRESS => 'text-blue-600',
            self::STATUS_RESOLVED => 'text-green-600',
            self::STATUS_REJECTED => 'text-red-600',
            default => 'text-gray-600',
        };
    }

    /**
     * Get the status badge background class.
     */
    public function getStatusBadgeBgClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            self::STATUS_IN_PROGRESS => 'bg-blue-100 text-blue-800',
            self::STATUS_RESOLVED => 'bg-green-100 text-green-800',
            self::STATUS_REJECTED => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_RESOLVED => 'Resolved',
            self::STATUS_REJECTED => 'Rejected',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Get the priority badge color class.
     */
    public function getPriorityBadgeClass(): string
    {
        return match ($this->priority ?? 'medium') {
            'critical' => 'bg-red-100 text-red-800',
            'high' => 'bg-orange-100 text-orange-800',
            'medium' => 'bg-yellow-100 text-yellow-800',
            'low' => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get priority label.
     */
    public function getPriorityLabel(): string
    {
        return ucfirst($this->priority ?? 'medium');
    }

    /**
     * Likes associated with this report.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(ReportLike::class);
    }

    /**
     * Comments associated with this report.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ReportComment::class);
    }

    /**
     * Users following this report.
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'report_followers')->withTimestamps();
    }
}
