<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// User pages - protected: requires authentication and user must NOT be admin
Route::middleware(['auth', 'not.admin', 'not.banned'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Client\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/garbage-schedule', [App\Http\Controllers\Client\ScheduleController::class, 'index'])->name('garbage-schedule');
    Route::post('/garbage-schedule/service-area', [App\Http\Controllers\Client\ScheduleController::class, 'updateServiceArea'])->name('garbage-schedule.service-area');
    Route::post('/garbage-schedule/notifications', [App\Http\Controllers\Client\ScheduleController::class, 'updateNotifications'])->name('garbage-schedule.notifications');

    Route::get('/tracker', [App\Http\Controllers\Client\TrackerController::class, 'index'])->name('tracker');
    Route::get('/tracker/data', [App\Http\Controllers\Client\TrackerController::class, 'getData'])->name('tracker.data.client');
    Route::get('/tracker/{truck}/route-history', [App\Http\Controllers\Client\TrackerController::class, 'routeHistory'])->name('tracker.route-history.client');

    Route::get('/notifications', [App\Http\Controllers\Client\NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/mark-all-read', [App\Http\Controllers\Client\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::post('/notifications/preferences', [App\Http\Controllers\Client\NotificationController::class, 'updatePreferences'])->name('notifications.preferences');
    Route::post('/notifications/{notification}/read', [App\Http\Controllers\Client\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::delete('/notifications/{notification}', [App\Http\Controllers\Client\NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('/community-reports', [App\Http\Controllers\Client\CommunityReportController::class, 'index'])->name('community-reports');

    Route::get('/settings', [App\Http\Controllers\Client\SettingsController::class, 'index'])->name('settings');
    Route::patch('/settings/account', [App\Http\Controllers\Client\SettingsController::class, 'updateAccount'])->name('settings.account');
    Route::patch('/settings/password', [App\Http\Controllers\Client\SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::patch('/settings/notifications', [App\Http\Controllers\Client\SettingsController::class, 'updateNotifications'])->name('settings.notifications');
    Route::patch('/settings/privacy', [App\Http\Controllers\Client\SettingsController::class, 'updatePrivacy'])->name('settings.privacy');
    Route::patch('/settings/preferences', [App\Http\Controllers\Client\SettingsController::class, 'updatePreferences'])->name('settings.preferences');
    Route::delete('/settings/sessions/{sessionId}/revoke', [App\Http\Controllers\Client\SettingsController::class, 'revokeSession'])->name('settings.revoke-session');
    Route::get('/settings/download-data', [App\Http\Controllers\Client\SettingsController::class, 'downloadData'])->name('settings.download-data');
    Route::delete('/settings/account', [App\Http\Controllers\Client\SettingsController::class, 'deleteAccount'])->name('settings.delete-account');

    Route::get('/profile', [App\Http\Controllers\Client\ProfileController::class, 'index'])->name('profile');
    Route::patch('/profile', [App\Http\Controllers\Client\ProfileController::class, 'update'])->name('profile.update');
    
    // Profile report management
    Route::patch('/profile/reports/{report}', [App\Http\Controllers\Client\ProfileController::class, 'updateReport'])->name('profile.reports.update');
    Route::delete('/profile/reports/{report}', [App\Http\Controllers\Client\ProfileController::class, 'deleteReport'])->name('profile.reports.delete');

    // Report feed interactions
    Route::post('/reports', [App\Http\Controllers\Client\ReportFeedController::class, 'store'])->middleware('throttle.reports')->name('reports.store');
    Route::post('/reports/{report}/like', [App\Http\Controllers\Client\ReportFeedController::class, 'toggleLike'])->middleware('throttle:60,1')->name('reports.like');
    Route::post('/reports/{report}/comment', [App\Http\Controllers\Client\ReportFeedController::class, 'storeComment'])->middleware('throttle:30,1')->name('reports.comment');
    Route::get('/reports/{report}/comments', [App\Http\Controllers\Client\ReportFeedController::class, 'getComments'])->name('reports.comments');
    Route::get('/reports/{report}', [App\Http\Controllers\Client\CommunityReportController::class, 'show'])->name('reports.show');
    Route::post('/reports/{report}/follow', [App\Http\Controllers\Client\ReportFeedController::class, 'toggleFollow'])->name('reports.follow');
    
    // User reporting
    Route::post('/users/{user}/report', [App\Http\Controllers\Client\UserReportController::class, 'store'])->name('users.report');
});

// Admin pages - protected: requires authentication and user MUST be admin
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

    // User Management
    Route::get('/users', [App\Http\Controllers\Admin\UserManagementController::class, 'index'])->name('users');
    Route::put('/users/{id}', [App\Http\Controllers\Admin\UserManagementController::class, 'update'])->name('users.update');
    Route::delete('/users/{id}', [App\Http\Controllers\Admin\UserManagementController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{id}/ban', [App\Http\Controllers\Admin\UserManagementController::class, 'ban'])->name('users.ban');
    Route::post('/users/{id}/unban', [App\Http\Controllers\Admin\UserManagementController::class, 'unban'])->name('users.unban');

    // Reports Management
    Route::get('/reports', [App\Http\Controllers\Admin\ReportsController::class, 'index'])->name('reports');
    Route::post('/reports/{id}/resolve', [App\Http\Controllers\Admin\ReportsController::class, 'resolve'])->name('reports.resolve');
    Route::post('/reports/{id}/reject', [App\Http\Controllers\Admin\ReportsController::class, 'reject'])->name('reports.reject');
    Route::post('/reports/bulk-resolve', [App\Http\Controllers\Admin\ReportsController::class, 'bulkResolve'])->name('reports.bulk-resolve');
    Route::post('/reports/bulk-reject', [App\Http\Controllers\Admin\ReportsController::class, 'bulkReject'])->name('reports.bulk-reject');
    Route::patch('/reports/{id}/priority', [App\Http\Controllers\Admin\ReportsController::class, 'updatePriority'])->name('reports.update-priority');
    Route::delete('/reports/{report}', [App\Http\Controllers\Admin\ReportsController::class, 'destroy'])->name('reports.destroy');

    // Schedule Management
    Route::get('/schedule', [App\Http\Controllers\Admin\ScheduleController::class, 'index'])->name('schedule');
    Route::post('/schedule', [App\Http\Controllers\Admin\ScheduleController::class, 'store'])->name('schedule.store');
    Route::put('/schedule/{id}', [App\Http\Controllers\Admin\ScheduleController::class, 'update'])->name('schedule.update');
    Route::delete('/schedule/{id}', [App\Http\Controllers\Admin\ScheduleController::class, 'destroy'])->name('schedule.destroy');

    // Tracker / Truck Management
    Route::get('/tracker', [App\Http\Controllers\Admin\TrackerController::class, 'index'])->name('tracker');
    Route::post('/tracker', [App\Http\Controllers\Admin\TrackerController::class, 'store'])->name('tracker.store');
    Route::put('/tracker/{id}', [App\Http\Controllers\Admin\TrackerController::class, 'update'])->name('tracker.update');
    Route::delete('/tracker/{id}', [App\Http\Controllers\Admin\TrackerController::class, 'destroy'])->name('tracker.destroy');
    Route::post('/tracker/{id}/location', [App\Http\Controllers\Admin\TrackerController::class, 'updateLocation'])->name('tracker.update-location');
    Route::get('/tracker/data', [App\Http\Controllers\Admin\TrackerController::class, 'getData'])->name('tracker.data');
    Route::get('/tracker/{id}/route-history', [App\Http\Controllers\Admin\TrackerController::class, 'getRouteHistory'])->name('tracker.route-history');

    // Service Zone Management
    Route::get('/service-zones', [App\Http\Controllers\Admin\ServiceZoneController::class, 'index'])->name('service-zones');
    Route::post('/service-zones', [App\Http\Controllers\Admin\ServiceZoneController::class, 'store'])->name('service-zones.store');
    Route::put('/service-zones/{id}', [App\Http\Controllers\Admin\ServiceZoneController::class, 'update'])->name('service-zones.update');
    Route::delete('/service-zones/{id}', [App\Http\Controllers\Admin\ServiceZoneController::class, 'destroy'])->name('service-zones.destroy');

    // Settings Management
    Route::get('/settings', [App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/profile', [App\Http\Controllers\Admin\SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::post('/settings/password', [App\Http\Controllers\Admin\SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/notifications', [App\Http\Controllers\Admin\SettingsController::class, 'updateNotifications'])->name('settings.notifications');

    // User Reports Management
    Route::get('/user-reports', [App\Http\Controllers\Admin\UserReportController::class, 'index'])->name('user-reports');
    Route::put('/user-reports/{id}', [App\Http\Controllers\Admin\UserReportController::class, 'update'])->name('user-reports.update');
});

require __DIR__.'/auth.php';
