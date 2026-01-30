<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Mark a notification as unread.
 */
final class MarkNotificationAsUnread
{
    /**
     * Mark the notification as unread.
     */
    public function handle(DatabaseNotification $notification): DatabaseNotification
    {
        $notification->update(['read_at' => null]);

        return $notification->fresh() ?? $notification;
    }
}
