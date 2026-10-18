<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    /**
     * Log an activity.
     */
    public static function log(
        string $action,
        ?Model $model = null,
        ?string $description = null,
        ?array $changes = null
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->id,
            'description' => $description,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Log report resolution.
     */
    public static function logReportResolved(Model $report, ?string $adminNotes = null): ActivityLog
    {
        return self::log(
            'report.resolved',
            $report,
            'Report resolved by admin'.($adminNotes ? ": {$adminNotes}" : ''),
            ['status' => 'resolved', 'admin_notes' => $adminNotes]
        );
    }

    public static function logReportInProgress(Model $report): ActivityLog
    {
        return self::log(
            'report.in_progress',
            $report,
            "Report #{$report->getKey()} marked in progress by admin",
            ['status' => 'in_progress']
        );
    }

    /**
     * Log report rejection.
     */
    public static function logReportRejected(Model $report, ?string $reason = null): ActivityLog
    {
        return self::log(
            'report.rejected',
            $report,
            'Report rejected by admin'.($reason ? ": {$reason}" : ''),
            ['status' => 'rejected', 'rejection_reason' => $reason]
        );
    }

    /**
     * Log user deletion.
     */
    public static function logUserDeleted(Model $user): ActivityLog
    {
        return self::log(
            'user.deleted',
            $user,
            'User deleted by admin',
            ['deleted_user' => $user->name, 'email' => $user->email]
        );
    }

    /**
     * Log user update.
     */
    public static function logUserUpdated(Model $user, array $changes): ActivityLog
    {
        return self::log(
            'user.updated',
            $user,
            'User updated by admin',
            $changes
        );
    }

    /**
     * Log schedule creation/update.
     */
    public static function logScheduleAction(string $action, Model $schedule, ?array $changes = null): ActivityLog
    {
        return self::log(
            "schedule.{$action}",
            $schedule,
            "Schedule {$action} by admin",
            $changes
        );
    }

    /**
     * Log truck action.
     */
    public static function logTruckAction(string $action, Model $truck, ?array $changes = null): ActivityLog
    {
        return self::log(
            "truck.{$action}",
            $truck,
            "Truck {$action} by admin",
            $changes
        );
    }

    /**
     * Log user ban.
     */
    public static function logUserBanned(Model $user): ActivityLog
    {
        return self::log(
            'user.banned',
            $user,
            'User banned by admin',
            ['banned_user' => $user->name, 'email' => $user->email]
        );
    }

    /**
     * Log user unban.
     */
    public static function logUserUnbanned(Model $user): ActivityLog
    {
        return self::log(
            'user.unbanned',
            $user,
            'User unbanned by admin',
            ['unbanned_user' => $user->name, 'email' => $user->email]
        );
    }
}
