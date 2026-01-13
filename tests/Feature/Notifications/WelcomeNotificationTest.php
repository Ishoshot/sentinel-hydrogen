<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Support\Facades\Notification;

it('sends welcome notification to new users', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $user->notify(new WelcomeNotification);

    Notification::assertSentTo($user, WelcomeNotification::class);
});

it('sends welcome notification via mail channel', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $user->notify(new WelcomeNotification);

    Notification::assertSentTo($user, WelcomeNotification::class, function ($notification, $channels): bool {
        return in_array('mail', $channels, true);
    });
});

it('renders the welcome email with correct content', function (): void {
    $user = User::factory()->create(['name' => 'John Doe']);

    $notification = new WelcomeNotification;
    $mailMessage = $notification->toMail($user);

    expect($mailMessage->subject)->toBe('Welcome to Sentinel');
});

it('includes user name in email data', function (): void {
    $user = User::factory()->create(['name' => 'Jane Smith']);

    $notification = new WelcomeNotification;
    $mailMessage = $notification->toMail($user);

    expect($mailMessage->viewData['userName'])->toBe('Jane Smith');
});

it('includes dashboard url in email data', function (): void {
    config()->set('app.frontend_url', 'https://app.sentinel.dev');

    $user = User::factory()->create();

    $notification = new WelcomeNotification;
    $mailMessage = $notification->toMail($user);

    expect($mailMessage->viewData['dashboardUrl'])->toBe('https://app.sentinel.dev');
});

it('returns array representation for database storage', function (): void {
    $user = User::factory()->create();

    $notification = new WelcomeNotification;
    $array = $notification->toArray($user);

    expect($array)->toBe(['type' => 'welcome']);
});
