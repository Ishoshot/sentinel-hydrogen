<?php

declare(strict_types=1);

use App\Actions\Briefings\Handlers\EmailBriefingDeliveryHandler;
use App\Models\BriefingGeneration;
use App\Models\BriefingSubscription;
use App\Models\User;
use App\Notifications\Briefings\BriefingDeliveryNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

it('sends email notification to subscriber with a user', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'subscriber@example.com']);
    $subscription = BriefingSubscription::factory()->forUser($user)->create();
    $generation = BriefingGeneration::factory()->completed()->create();

    $handler = new EmailBriefingDeliveryHandler;
    $handler->deliver($generation, $subscription);

    Notification::assertSentTo($user, BriefingDeliveryNotification::class);
});

it('logs a warning and does not send when subscription user is missing', function (): void {
    Notification::fake();
    Log::spy();

    $subscription = BriefingSubscription::factory()->create();
    $generation = BriefingGeneration::factory()->completed()->create();

    // Simulate a deleted user by forcing the relationship to null
    $subscription->setRelation('user', null);

    $handler = new EmailBriefingDeliveryHandler;
    $handler->deliver($generation, $subscription);

    Notification::assertNothingSent();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'Cannot deliver briefing via email'));
});

it('logs a warning and does not send when user has no email', function (): void {
    Notification::fake();
    Log::spy();

    $user = User::factory()->create();
    $subscription = BriefingSubscription::factory()->forUser($user)->create();
    $generation = BriefingGeneration::factory()->completed()->create();

    // Simulate a user with null email by mutating the model attribute
    $user->email = null;
    $subscription->setRelation('user', $user);

    $handler = new EmailBriefingDeliveryHandler;
    $handler->deliver($generation, $subscription);

    Notification::assertNothingSent();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'Cannot deliver briefing via email'));
});

it('logs info on successful email delivery', function (): void {
    Notification::fake();
    Log::spy();

    $user = User::factory()->create(['email' => 'test@example.com']);
    $subscription = BriefingSubscription::factory()->forUser($user)->create();
    $generation = BriefingGeneration::factory()->completed()->create();

    $handler = new EmailBriefingDeliveryHandler;
    $handler->deliver($generation, $subscription);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message): bool => str_contains($message, 'Briefing delivered via email'));
});

it('re-throws exceptions when notification fails', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'fail@example.com']);
    $subscription = BriefingSubscription::factory()->forUser($user)->create();
    $generation = BriefingGeneration::factory()->completed()->create();

    Notification::shouldReceive('sendNow')->andThrow(new RuntimeException('Mail transport error'));
    Notification::shouldReceive('send')->andThrow(new RuntimeException('Mail transport error'));

    $handler = new EmailBriefingDeliveryHandler;

    expect(fn () => $handler->deliver($generation, $subscription))
        ->toThrow(RuntimeException::class, 'Mail transport error');
});

it('loads the user relationship if not already loaded', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'lazy@example.com']);
    $subscription = BriefingSubscription::factory()->forUser($user)->create();
    $generation = BriefingGeneration::factory()->completed()->create();

    // Fetch a fresh subscription without eager-loaded user
    $freshSubscription = BriefingSubscription::query()->find($subscription->id);

    $handler = new EmailBriefingDeliveryHandler;
    $handler->deliver($generation, $freshSubscription);

    Notification::assertSentTo($user, BriefingDeliveryNotification::class);
});
