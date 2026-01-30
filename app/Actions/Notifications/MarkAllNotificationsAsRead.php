<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;

/**
 * Mark all notifications as read for a user.
 */
final class MarkAllNotificationsAsRead
{
    /**
     * Mark all unread notifications as read.
     */
    public function handle(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
