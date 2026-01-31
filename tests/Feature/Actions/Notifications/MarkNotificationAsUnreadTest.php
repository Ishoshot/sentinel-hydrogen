<?php

declare(strict_types=1);

use App\Actions\Notifications\MarkNotificationAsUnread;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

it('marks a notification as unread', function (): void {
    $user = User::factory()->create();
    $notificationId = Illuminate\Support\Str::uuid()->toString();

    DatabaseNotification::query()->insert([
        'id' => $notificationId,
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => '{}',
        'read_at' => now(),
        'created_at' => now(),
    ]);

    $notification = DatabaseNotification::query()->find($notificationId);

    $action = new MarkNotificationAsUnread;
    $result = $action->handle($notification);

    expect($result->read_at)->toBeNull();
});

it('returns the notification', function (): void {
    $user = User::factory()->create();
    $notificationId = Illuminate\Support\Str::uuid()->toString();

    DatabaseNotification::query()->insert([
        'id' => $notificationId,
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => '{}',
        'read_at' => now(),
        'created_at' => now(),
    ]);

    $notification = DatabaseNotification::query()->find($notificationId);

    $action = new MarkNotificationAsUnread;
    $result = $action->handle($notification);

    expect($result)->toBeInstanceOf(DatabaseNotification::class);
    expect($result->id)->toBe($notificationId);
});
