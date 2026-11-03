<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationAction extends Model
{
    public const WARNING = 'warning';

    public const COMMUNITY_RESTRICTION = 'community_restriction';

    public const REPORT_SUBMISSION_RESTRICTION = 'report_submission_restriction';

    public const SUSPENSION = 'suspension';

    public const RESTRICTION_LIFTED = 'restriction_lifted';

    public const UNSUSPENDED = 'unsuspended';

    public const CONFIRMED_VIOLATION_ACTIONS = [
        self::WARNING,
        self::COMMUNITY_RESTRICTION,
        self::REPORT_SUBMISSION_RESTRICTION,
        self::SUSPENSION,
    ];

    protected $fillable = [
        'user_id',
        'user_report_id',
        'related_action_id',
        'action_type',
        'resident_reason',
        'admin_notes',
        'applied_by',
        'starts_at',
        'expires_at',
        'lifted_at',
        'lifted_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userReport(): BelongsTo
    {
        return $this->belongsTo(UserReport::class);
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    public function relatedAction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_action_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('lifted_at')
            ->where('starts_at', '<=', now())
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function label(): string
    {
        return match ($this->action_type) {
            self::WARNING => 'Warning',
            self::COMMUNITY_RESTRICTION => 'Community Restriction',
            self::REPORT_SUBMISSION_RESTRICTION => 'Report Submission Restriction',
            self::SUSPENSION => 'Suspension',
            self::RESTRICTION_LIFTED => 'Restriction Lifted',
            self::UNSUSPENDED => 'Unsuspended',
            default => str($this->action_type)->replace('_', ' ')->title()->toString(),
        };
    }

    public function statusLabel(): string
    {
        if ($this->lifted_at) {
            return 'Lifted';
        }

        if ($this->expires_at?->isPast()) {
            return 'Expired';
        }

        return in_array($this->action_type, [self::WARNING, self::RESTRICTION_LIFTED, self::UNSUSPENDED], true)
            ? 'Recorded'
            : 'Active';
    }
}
