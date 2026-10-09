<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ServiceZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $serviceAreas = $this->activeServiceAreas();
        $reportUpdates = $user->notificationPreferenceEnabled('report_updates', 'reports');
        $scheduleReminders = $user->notificationPreferenceEnabled('schedule_reminders', 'schedule');

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
                'report_updates' => $reportUpdates,
                'schedule_reminders' => $scheduleReminders,
                'push_available' => false,
                // Temporary read alias for installed clients using the previous nested shape.
                'preferences' => [
                    'report_updates' => $reportUpdates,
                    'schedule_reminders' => $scheduleReminders,
                ],
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
        $canonicalPayload = $request->hasAny(['report_updates', 'schedule_reminders']);
        $rules = [
            'email_notifications' => ['required', 'boolean'],
            'sms_notifications' => ['required', 'boolean'],
        ];

        if ($canonicalPayload) {
            $rules['report_updates'] = ['required', 'boolean'];
            $rules['schedule_reminders'] = ['required', 'boolean'];
        } else {
            // Temporary compatibility for the installed app's previous nested payload.
            $rules['preferences'] = ['required', 'array'];
            $rules['preferences.report_updates'] = ['required', 'boolean'];
            $rules['preferences.schedule_reminders'] = ['required', 'boolean'];
        }

        $validated = $request->validate($rules);
        $reportUpdates = $canonicalPayload
            ? $validated['report_updates']
            : $validated['preferences']['report_updates'];
        $scheduleReminders = $canonicalPayload
            ? $validated['schedule_reminders']
            : $validated['preferences']['schedule_reminders'];

        $user = $request->user();
        $user->email_notifications = $validated['email_notifications'];
        $user->sms_notifications = $validated['sms_notifications'];
        $user->notification_preferences = array_merge(
            Arr::except($user->notification_preferences ?? [], ['community_posts', 'truck_tracking']),
            [
                'report_updates' => (bool) $reportUpdates,
                'schedule_reminders' => (bool) $scheduleReminders,
            ],
        );
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
