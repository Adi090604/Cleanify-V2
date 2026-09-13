<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ServiceZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    private const DEFAULT_NOTIFICATION_PREFERENCES = [
        'report_updates' => true,
        'schedule_reminders' => true,
        'community_posts' => true,
        'truck_tracking' => true,
    ];

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $serviceAreas = $this->activeServiceAreas();

        return response()->json([
            'account' => [
                'email' => $user->email,
                'phone' => $user->phone,
                'service_area' => in_array($user->service_area, $serviceAreas, true)
                    ? $user->service_area
                    : null,
            ],
            'service_areas' => $serviceAreas,
            'notifications' => [
                'email_notifications' => (bool) $user->email_notifications,
                'sms_notifications' => (bool) $user->sms_notifications,
                'push_notifications' => (bool) $user->push_notifications,
                'preferences' => array_merge(
                    self::DEFAULT_NOTIFICATION_PREFERENCES,
                    $user->notification_preferences ?? [],
                ),
            ],
        ]);
    }

    public function updateAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'service_area' => ['nullable', 'string', Rule::in($this->activeServiceAreas())],
        ]);

        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        $user->service_area = $validated['service_area'] ?? null;

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json([
            'message' => 'Account information updated successfully',
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }

    public function updateNotifications(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_notifications' => ['required', 'boolean'],
            'sms_notifications' => ['required', 'boolean'],
            'push_notifications' => ['required', 'boolean'],
            'preferences' => ['required', 'array'],
            'preferences.report_updates' => ['required', 'boolean'],
            'preferences.schedule_reminders' => ['required', 'boolean'],
            'preferences.community_posts' => ['required', 'boolean'],
            'preferences.truck_tracking' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $user->email_notifications = $validated['email_notifications'];
        $user->sms_notifications = $validated['sms_notifications'];
        $user->push_notifications = $validated['push_notifications'];
        $user->notification_preferences = $validated['preferences'];
        $user->save();

        return response()->json([
            'message' => 'Notification preferences updated successfully',
        ]);
    }

    /** @return array<int, string> */
    private function activeServiceAreas(): array
    {
        return ServiceZone::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map->display_name
            ->values()
            ->all();
    }
}
