<?php

declare(strict_types=1);

use App\Actions\Notifications\MarkAllNotificationsAsRead;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

it('marks all unread notifications as read', function (): void {
    $user = User::factory()->create();

    // Create unread notifications
    DatabaseNotification::query()->insert([
        ['id' => Illuminate\Support\Str::uuid(), 'type' => 'App\Notifications\TestNotification', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'data' => '{}', 'read_at' => null, 'created_at' => now()],
        ['id' => Illuminate\Support\Str::uuid(), 'type' => 'App\Notifications\TestNotification', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'data' => '{}', 'read_at' => null, 'created_at' => now()],
        ['id' => Illuminate\Support\Str::uuid(), 'type' => 'App\Notifications\TestNotification', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'data' => '{}', 'read_at' => null, 'created_at' => now()],
    ]);

    $action = new MarkAllNotificationsAsRead;
    $count = $action->handle($user);

    expect($count)->toBe(3);
    expect($user->unreadNotifications()->count())->toBe(0);
});

it('returns zero when no unread notifications exist', function (): void {
    $user = User::factory()->create();

    $action = new MarkAllNotificationsAsRead;
    $count = $action->handle($user);

    expect($count)->toBe(0);
});

it('only marks unread notifications', function (): void {
    $user = User::factory()->create();

    // Create mixed notifications
    DatabaseNotification::query()->insert([
        ['id' => Illuminate\Support\Str::uuid(), 'type' => 'App\Notifications\TestNotification', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'data' => '{}', 'read_at' => null, 'created_at' => now()],
        ['id' => Illuminate\Support\Str::uuid(), 'type' => 'App\Notifications\TestNotification', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'data' => '{}', 'read_at' => now(), 'created_at' => now()],
    ]);

    $action = new MarkAllNotificationsAsRead;
    $count = $action->handle($user);

    expect($count)->toBe(1);
});

it('does not affect other users notifications', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    DatabaseNotification::query()->insert([
        ['id' => Illuminate\Support\Str::uuid(), 'type' => 'App\Notifications\TestNotification', 'notifiable_type' => User::class, 'notifiable_id' => $user1->id, 'data' => '{}', 'read_at' => null, 'created_at' => now()],
        ['id' => Illuminate\Support\Str::uuid(), 'type' => 'App\Notifications\TestNotification', 'notifiable_type' => User::class, 'notifiable_id' => $user2->id, 'data' => '{}', 'read_at' => null, 'created_at' => now()],
    ]);

    $action = new MarkAllNotificationsAsRead;
    $action->handle($user1);

    expect($user2->unreadNotifications()->count())->toBe(1);
});
