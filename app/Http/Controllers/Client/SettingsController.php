<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Schedule;
use App\Models\ServiceZone;
use App\Services\ProfilePhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Display the settings page.
     */
    public function index(): View
    {
        $user = Auth::user();
        
        // Get available service areas
        $availableAreas = ServiceZone::where('status', 'active')->orderBy('name')->get()->map->display_name
            ->merge(Schedule::where('status', 'active')->orderBy('area')->pluck('area'))
            ->unique()->values();
        
        // Get user statistics for deletion confirmation
        $userStats = [
            'reports_count' => Report::where('user_id', $user->id)->count(),
            'likes_count' => \App\Models\ReportLike::where('user_id', $user->id)->count(),
            'comments_count' => \App\Models\ReportComment::where('user_id', $user->id)->count(),
        ];
        
        // Get notification preferences
        $notificationPrefs = $user->notification_preferences ?? [];
        $defaultPrefs = [
            'report_updates' => true,
            'schedule_reminders' => true,
            'community_posts' => true,
            'truck_tracking' => true,
        ];
        $notificationPrefs = array_merge($defaultPrefs, $notificationPrefs);
        
        // Get active sessions (from Laravel's sessions table)
        $activeSessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderBy('last_activity', 'desc')
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'last_activity' => date('Y-m-d H:i:s', $session->last_activity),
                    'is_current' => $session->id === session()->getId(),
                ];
            });

        return view('settings', [
            'activePage' => 'settings',
            'user' => $user,
            'userStats' => $userStats,
            'availableAreas' => $availableAreas,
            'notificationPrefs' => $notificationPrefs,
            'activeSessions' => $activeSessions,
        ]);
    }

    /**
     * Update account information (email, phone, service area).
     */
    public function updateAccount(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . Auth::id()],
            'phone' => ['nullable', 'string', 'max:20'],
            'service_area' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::user();
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        $user->service_area = $validated['service_area'] ?? null;
        
        // If email changed, unverify it
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        
        $user->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Account information updated successfully',
            ]);
        }

        return redirect()->route('settings')->with('success', 'Account information updated successfully');
    }

    /**
     * Update password.
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully',
            ]);
        }

        return redirect()->route('settings')->with('success', 'Password updated successfully');
    }

    /**
     * Update notification preferences.
     */
    public function updateNotifications(Request $request)
    {
        $validated = $request->validate([
            'email_notifications' => ['nullable', 'boolean'],
            'sms_notifications' => ['nullable', 'boolean'],
            'push_notifications' => ['nullable', 'boolean'],
            'preferences' => ['nullable', 'array'],
        ]);

        $user = Auth::user();
        
        // Update global notification toggles
        $user->email_notifications = $request->has('email_notifications');
        $user->sms_notifications = $request->has('sms_notifications');
        $user->push_notifications = $request->has('push_notifications');
        
        // Update category-specific preferences
        if ($request->has('preferences')) {
            $preferences = [];
            foreach ($request->input('preferences', []) as $key => $value) {
                $preferences[$key] = (bool) $value;
            }
            
            $currentPrefs = $user->notification_preferences ?? [];
            $user->notification_preferences = array_merge($currentPrefs, $preferences);
        }
        
        $user->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Notification preferences updated successfully',
            ]);
        }

        return redirect()->route('settings')->with('success', 'Notification preferences updated successfully');
    }

    /**
     * Update privacy settings.
     */
    public function updatePrivacy(Request $request)
    {
        $validated = $request->validate([
            'show_email' => ['nullable', 'boolean'],
            'location_sharing' => ['nullable', 'boolean'],
            'profile_visibility' => ['nullable', 'string', 'in:public,private'],
        ]);

        $user = Auth::user();
        $user->show_email = $request->has('show_email');
        $user->location_sharing = $request->has('location_sharing');
        $user->profile_visibility = $validated['profile_visibility'] ?? 'public';
        $user->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Privacy settings updated successfully',
            ]);
        }

        return redirect()->route('settings')->with('success', 'Privacy settings updated successfully');
    }

    /**
     * Delete user account with all associated data.
     */
    public function deleteAccount(Request $request, ProfilePhotoService $profilePhotos)
    {
        $validated = $request->validate([
            'password' => ['required', 'current_password'],
            'confirm_delete' => ['required', 'accepted'],
        ]);

        $user = Auth::user();
        $profilePhotoPath = $user->profile_photo_path;
        
        // Delete all user's reports and associated images
        $reports = Report::where('user_id', $user->id)->get();
        foreach ($reports as $report) {
            if ($report->image_path && Storage::disk('public')->exists($report->image_path)) {
                Storage::disk('public')->delete($report->image_path);
            }
            
            // Delete associated likes, comments, and followers
            $report->likes()->delete();
            $report->comments()->delete();
            $report->followers()->detach();
        }
        Report::where('user_id', $user->id)->delete();
        
        // Delete user's likes on other reports
        \App\Models\ReportLike::where('user_id', $user->id)->delete();
        
        // Delete user's comments on other reports
        \App\Models\ReportComment::where('user_id', $user->id)->delete();
        
        // Delete user's report follows
        \DB::table('report_followers')->where('user_id', $user->id)->delete();
        
        // Delete user's notifications
        \DB::table('notifications')->where('notifiable_type', 'App\Models\User')
            ->where('notifiable_id', $user->id)
            ->delete();
        
        // Logout the user before deleting
        Auth::logout();
        
        // Delete the user account
        $user->delete();
        $profilePhotos->delete($profilePhotoPath);
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully',
            ]);
        }

        return redirect()->route('welcome')->with('success', 'Your account has been permanently deleted.');
    }

    /**
     * Update application preferences.
     */
    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'tracker_refresh_interval' => ['nullable', 'integer', 'in:15,30,60,300'], // 15s, 30s, 1min, 5min
            'language' => ['nullable', 'string', 'in:en,fil'],
        ]);

        $user = Auth::user();
        if (isset($validated['tracker_refresh_interval'])) {
            $user->tracker_refresh_interval = $validated['tracker_refresh_interval'];
        }
        if (isset($validated['language'])) {
            $user->language = $validated['language'];
        }
        $user->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Application preferences updated successfully',
            ]);
        }

        return redirect()->route('settings')->with('success', 'Application preferences updated successfully');
    }

    /**
     * Revoke a session.
     */
    public function revokeSession(Request $request, string $sessionId)
    {
        $user = Auth::user();
        
        // URL decode the session ID
        $sessionId = urldecode($sessionId);
        
        // Prevent revoking current session
        if ($sessionId === session()->getId()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot revoke your current session',
            ], 400);
        }

        // Verify the session belongs to the user
        $session = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found or you do not have permission to revoke it',
            ], 404);
        }

        // Delete the session
        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Session revoked successfully',
        ]);
    }

    /**
     * Download user data (GDPR compliance).
     */
    public function downloadData()
    {
        $user = Auth::user();
        
        $data = [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'service_area' => $user->service_area,
                'created_at' => $user->created_at,
            ],
            'reports' => Report::where('user_id', $user->id)
                ->get()
                ->map(function ($report) {
                    return [
                        'id' => $report->id,
                        'location' => $report->location,
                        'description' => $report->description,
                        'status' => $report->status,
                        'created_at' => $report->created_at,
                    ];
                }),
            'likes' => \App\Models\ReportLike::where('user_id', $user->id)->count(),
            'comments' => \App\Models\ReportComment::where('user_id', $user->id)
                ->get()
                ->map(function ($comment) {
                    return [
                        'report_id' => $comment->report_id,
                        'comment' => $comment->comment,
                        'created_at' => $comment->created_at,
                    ];
                }),
        ];

        return response()->json($data, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="cleanify-data-' . date('Y-m-d') . '.json"',
        ]);
    }
}
