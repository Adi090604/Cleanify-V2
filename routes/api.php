<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfilePhotoController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\TruckController;
use App\Http\Controllers\Api\V1\UserReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/me/profile-photo', [ProfilePhotoController::class, 'store']);
        Route::delete('/me/profile-photo', [ProfilePhotoController::class, 'destroy']);
        Route::get('/trucks', [TruckController::class, 'index']);
        Route::get('/trucks/{truck}/route-history', [TruckController::class, 'routeHistory']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
        Route::get('/reports', [ReportController::class, 'index']);
        Route::post('/reports', [ReportController::class, 'store']);
        Route::post('/reports/{report}/like', [ReportController::class, 'toggleLike']);
        Route::get('/reports/{report}/comments', [ReportController::class, 'comments']);
        Route::post('/reports/{report}/comment', [ReportController::class, 'storeComment']);
        Route::post('/users/{user}/report', [UserReportController::class, 'store']);
        Route::get('/me/reports', [ReportController::class, 'mine']);
        Route::get('/schedules', [ScheduleController::class, 'index']);
        Route::get('/schedules/next', [ScheduleController::class, 'next']);
        Route::get('/settings', [SettingsController::class, 'show']);
        Route::patch('/settings/account', [SettingsController::class, 'updateAccount']);
        Route::patch('/settings/password', [SettingsController::class, 'updatePassword']);
        Route::patch('/settings/notifications', [SettingsController::class, 'updateNotifications']);
    });
});
