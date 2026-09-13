<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/schedules', [ScheduleController::class, 'index']);
        Route::get('/schedules/next', [ScheduleController::class, 'next']);
        Route::get('/settings', [SettingsController::class, 'show']);
        Route::patch('/settings/account', [SettingsController::class, 'updateAccount']);
        Route::patch('/settings/password', [SettingsController::class, 'updatePassword']);
        Route::patch('/settings/notifications', [SettingsController::class, 'updateNotifications']);
    });
});
