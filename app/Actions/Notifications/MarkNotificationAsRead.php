<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Mark a notification as read.
 */
final class MarkNotificationAsRead
{
    /**
     * Mark the notification as read.
     */
    public function handle(DatabaseNotification $notification): DatabaseNotification
    {
        $notification->markAsRead();

        return $notification;
    }
}
