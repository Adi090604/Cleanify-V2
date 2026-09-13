<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private const CATEGORIES = [
        'schedule' => 'Schedule & Routes',
        'tracker' => 'Truck Tracker',
        'reports' => 'Reports & Community',
        'community' => 'Community Activity',
        'system' => 'System Alerts',
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filter' => ['nullable', 'in:all,unread'],
            'category' => ['nullable', 'in:all,'.implode(',', array_keys(self::CATEGORIES))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $user = $request->user();
        $query = $user->notifications()->latest();

        if (($validated['filter'] ?? 'all') === 'unread') {
            $query->whereNull('read_at');
        }
        $category = $validated['category'] ?? 'all';
        if ($category !== 'all') {
            $query->whereJsonContains('data->category', $category);
        }

        foreach (collect($user->notification_preferences ?? [])->filter(fn ($enabled) => $enabled === false)->keys() as $muted) {
            $query->whereJsonDoesntContain('data->category', $muted);
        }

        $notifications = $query->paginate(10);

        return response()->json([
            'notifications' => collect($notifications->items())->map(fn ($notification) => $this->serialize($notification))->values(),
            'unread_count' => $user->unreadNotifications()->count(),
            'categories' => collect(self::CATEGORIES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();
        return response()->json(['notification' => $this->serialize($item->fresh())]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->findOrFail($notification)->delete();
        return response()->json(['message' => 'Notification removed.']);
    }

    private function serialize($notification): array
    {
        $data = $notification->data ?? [];
        $category = $data['category'] ?? null;
        $action = match ($category) {
            'schedule' => '/tabs/schedule',
            'reports', 'community' => '/tabs/report',
            'tracker' => '/tabs/tracker',
            default => null,
        };

        return [
            'id' => $notification->id,
            'title' => $data['title'] ?? 'Notification',
            'message' => $data['message'] ?? $notification->type,
            'category' => $category,
            'is_read' => $notification->read_at !== null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at->toIso8601String(),
            'created_at_human' => $notification->created_at->diffForHumans(),
            'action_route' => $action,
        ];
    }
}
