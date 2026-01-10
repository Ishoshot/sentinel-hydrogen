<?php

declare(strict_types=1);

use App\Models\User;

it('can list notifications for authenticated user', function (): void {
    $user = User::factory()->create();

    // Create some notifications
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\TestNotification',
        'data' => ['message' => 'Test notification 1'],
    ]);
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\TestNotification',
        'data' => ['message' => 'Test notification 2'],
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('notifications.index'));

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'type', 'data', 'read_at', 'is_read', 'created_at'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'unread_count'],
        ]);
});

it('can list unread notifications only', function (): void {
    $user = User::factory()->create();

    // Create read notification
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\TestNotification',
        'data' => ['message' => 'Read notification'],
        'read_at' => now(),
    ]);

    // Create unread notification
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\TestNotification',
        'data' => ['message' => 'Unread notification'],
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('notifications.unread'));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.data.message', 'Unread notification');
});

it('can mark a notification as read', function (): void {
    $user = User::factory()->create();

    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\TestNotification',
        'data' => ['message' => 'Test notification'],
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('notifications.read', $notification->id));

    $response->assertOk()
        ->assertJsonPath('message', 'Notification marked as read.')
        ->assertJsonPath('data.is_read', true);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('can mark a notification as unread', function (): void {
    $user = User::factory()->create();

    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\TestNotification',
        'data' => ['message' => 'Test notification'],
        'read_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('notifications.mark-unread', $notification->id));

    $response->assertOk()
        ->assertJsonPath('message', 'Notification marked as unread.')
        ->assertJsonPath('data.is_read', false);

    expect($notification->fresh()->read_at)->toBeNull();
});

it('can mark all notifications as read', function (): void {
    $user = User::factory()->create();

    // Create multiple unread notifications
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\TestNotification',
        'data' => ['message' => 'Notification 1'],
    ]);
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\TestNotification',
        'data' => ['message' => 'Notification 2'],
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('notifications.read-all'));

    $response->assertOk()
        ->assertJsonPath('message', 'All notifications marked as read.');

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('returns correct unread count in meta', function (): void {
    $user = User::factory()->create();

    // Create 2 read and 3 unread notifications
    for ($i = 0; $i < 2; $i++) {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'data' => ['message' => "Read $i"],
            'read_at' => now(),
        ]);
    }
    for ($i = 0; $i < 3; $i++) {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'data' => ['message' => "Unread $i"],
        ]);
    }

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('notifications.index'));

    $response->assertOk()
        ->assertJsonPath('meta.unread_count', 3)
        ->assertJsonPath('meta.total', 5);
});

it('cannot access another users notification', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $notification = $user1->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\TestNotification',
        'data' => ['message' => 'Private notification'],
    ]);

    $response = $this->actingAs($user2, 'sanctum')
        ->postJson(route('notifications.read', $notification->id));

    $response->assertNotFound();
});

it('requires authentication to access notifications', function (): void {
    $response = $this->getJson(route('notifications.index'));

    $response->assertUnauthorized();
});
