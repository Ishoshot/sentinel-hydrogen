<?php

declare(strict_types=1);

use App\Actions\Notifications\MarkNotificationAsRead;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

it('marks a notification as read', function (): void {
    $user = User::factory()->create();
    $notificationId = Illuminate\Support\Str::uuid()->toString();

    DatabaseNotification::query()->insert([
        'id' => $notificationId,
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => '{}',
        'read_at' => null,
        'created_at' => now(),
    ]);

    $notification = DatabaseNotification::query()->find($notificationId);

    $action = new MarkNotificationAsRead;
    $result = $action->handle($notification);

    expect($result->read_at)->not->toBeNull();
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
        'read_at' => null,
        'created_at' => now(),
    ]);

    $notification = DatabaseNotification::query()->find($notificationId);

    $action = new MarkNotificationAsRead;
    $result = $action->handle($notification);

    expect($result)->toBeInstanceOf(DatabaseNotification::class);
    expect($result->id)->toBe($notificationId);
});
