<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Notifications\MarkAllNotificationsAsRead;
use App\Actions\Notifications\MarkNotificationAsRead;
use App\Actions\Notifications\MarkNotificationAsUnread;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationController
{
    /**
     * List all notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * List unread notifications for the authenticated user.
     */
    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $notifications = $user->unreadNotifications()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(
        Request $request,
        string $id,
        MarkNotificationAsRead $markAsRead,
    ): JsonResponse {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        /** @var DatabaseNotification|null $notification */
        $notification = $user->notifications()->find($id);

        if ($notification === null) {
            abort(404, 'Notification not found.');
        }

        $notification = $markAsRead->handle($notification);

        return response()->json([
            'data' => new NotificationResource($notification),
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * Mark a notification as unread.
     */
    public function markAsUnread(
        Request $request,
        string $id,
        MarkNotificationAsUnread $markAsUnread,
    ): JsonResponse {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        /** @var DatabaseNotification|null $notification */
        $notification = $user->notifications()->find($id);

        if ($notification === null) {
            abort(404, 'Notification not found.');
        }

        $notification = $markAsUnread->handle($notification);

        return response()->json([
            'data' => new NotificationResource($notification),
            'message' => 'Notification marked as unread.',
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(
        Request $request,
        MarkAllNotificationsAsRead $markAllAsRead,
    ): JsonResponse {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $markAllAsRead->handle($user);

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }
}
