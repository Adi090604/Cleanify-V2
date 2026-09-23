<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Report;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportDeletionService
{
    public function delete(Report $report): bool
    {
        $imagePath = $report->image_path;
        $hasSafeImagePath = $imagePath
            && preg_match('/\Areports\/[^\/\\\\]+\z/', $imagePath) === 1;

        if ($hasSafeImagePath
            && Storage::disk('public')->exists($imagePath)
            && ! Storage::disk('public')->delete($imagePath)) {
            return false;
        }

        DB::transaction(function () use ($report): void {
            DatabaseNotification::query()
                ->where('data->report_id', $report->id)
                ->delete();

            ActivityLog::query()
                ->where('model_type', Report::class)
                ->where('model_id', $report->id)
                ->delete();

            $report->delete();
        });

        return true;
    }
}
