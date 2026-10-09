<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    protected array $categories = [
        'schedule' => 'Schedule & Routes',
        'tracker' => 'Truck Tracker',
        'reports' => 'Reports & Community',
        'community' => 'Community Activity',
        'system' => 'System Alerts',
    ];

    /**
     * Display the notification inbox for the authenticated user.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = $request->get('filter', 'all');
        $categoryFilter = $request->get('category', 'all');
        $includeMuted = $request->boolean('include_muted', false);
        $preferences = $user->notification_preferences ?? [];

        $notificationsQuery = $user->notifications()->latest();

        if ($filter === 'unread') {
            $notificationsQuery->whereNull('read_at');
        }

        if ($categoryFilter !== 'all' && isset($this->categories[$categoryFilter])) {
            $notificationsQuery->whereJsonContains('data->category', $categoryFilter);
        }

        if (! $includeMuted) {
            $mutedCategories = $user->mutedNotificationCategories();

            if (! empty($mutedCategories)) {
                foreach ($mutedCategories as $mutedCategory) {
                    $notificationsQuery->whereJsonDoesntContain('data->category', $mutedCategory);
                }
            }
        }

        $notifications = $notificationsQuery->paginate(10);

        return view('notifications', [
            'activePage' => 'notifications',
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
            'filter' => $filter,
            'categoryFilter' => $categoryFilter,
            'includeMuted' => $includeMuted,
            'categories' => $this->categories,
            'preferences' => $this->buildPreferenceState($preferences),
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        return back()->with('success', 'Notification updated.');
    }

    /**
     * Delete (dismiss) a notification.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->delete();

        return back()->with('success', 'Notification removed.');
    }

    /**
     * Update category notification preferences.
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
        ]);

        $prefs = collect($validated['preferences'])
            ->mapWithKeys(function ($value, $key) {
                return [$key => (bool) $value];
            })
            ->only(array_keys($this->categories))
            ->toArray();

        $currentPreferences = $request->user()->notification_preferences ?? [];
        $request->user()->update([
            'notification_preferences' => array_merge($currentPreferences, $prefs, [
                'report_updates' => $prefs['reports'] ?? true,
                'schedule_reminders' => $prefs['schedule'] ?? true,
            ]),
        ]);

        return back()->with('success', 'Notification preferences updated.');
    }

    protected function buildPreferenceState(array $prefs): array
    {
        $defaults = collect($this->categories)
            ->mapWithKeys(fn ($label, $key) => [$key => true])
            ->toArray();

        $state = array_merge($defaults, $prefs);

        if (array_key_exists('report_updates', $prefs)) {
            $state['reports'] = $prefs['report_updates'];
        }

        if (array_key_exists('schedule_reminders', $prefs)) {
            $state['schedule'] = $prefs['schedule_reminders'];
        }

        return $state;
    }
}
